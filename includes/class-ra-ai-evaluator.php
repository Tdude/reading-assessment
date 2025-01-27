<?php
/**
 * includes/class-ra-ai-evaluator.php
 * Handles AI-powered evaluation of reading assessments
 *
 * @package ReadingAssessment
 * @subpackage ReadingAssessment/includes
 */

 class Reading_Assessment_AI_Evaluator {
     private $db;
     private $api_key;
     private $confidence_threshold = 0.85;
     private $batch_size = 10; // Process recordings in batches

     public function __construct() {
         global $wpdb;
         $this->db = $wpdb;
         $this->api_key = get_option('ra_openai_api_key');
     }

     public function test_api_connection() {
        if (!$this->api_key) {
            error_log('OpenAI API key not configured');
            return false;
        }

        try {
            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'model' => 'gpt-4',
                    'messages' => [
                        ['role' => 'user', 'content' => 'Test connection']
                    ],
                    'max_tokens' => 5
                ])
            ]);

            if (is_wp_error($response)) {
                error_log('API Test Error: ' . $response->get_error_message());
                return false;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            return $http_code === 200;

        } catch (Exception $e) {
            error_log('API Test Exception: ' . $e->getMessage());
            return false;
        }
    }

     public function process_recording($recording_id) {
         try {
             // Get recording
             $recording = $this->db->get_row($this->db->prepare(
                 "SELECT r.*, p.content as passage_content
                 FROM {$this->db->prefix}ra_recordings r
                 JOIN {$this->db->prefix}ra_passages p ON r.passage_id = p.id
                 WHERE r.id = %d",
                 $recording_id
             ));

             if (!$recording) {
                 return new WP_Error('invalid_recording', 'Recording not found');
             }

             // Schedule transcription if needed
             if (empty($recording->transcription)) {
                 wp_schedule_single_event(time(), 'ra_process_transcription', [$recording_id]);
                 return ['status' => 'scheduled_transcription'];
             }

             // Get evaluation if exists
             $evaluation = $this->db->get_row($this->db->prepare(
                 "SELECT * FROM {$this->db->prefix}ra_ai_evaluations
                 WHERE recording_id = %d",
                 $recording_id
             ));

             if ($evaluation) {
                 return [
                     'status' => 'complete',
                     'lus_score' => $evaluation->lus_score,
                     'confidence_score' => $evaluation->confidence_score
                 ];
             }

             // Evaluate reading
             $evaluation = $this->evaluate_reading($recording->transcription, $recording->passage_content);
             if (is_wp_error($evaluation)) {
                 return $evaluation;
             }

             // Calculate LUS score
             $lus_score = $this->calculate_lus_score($evaluation);

             // Store results
             $result = $this->store_ai_evaluation($recording_id, [
                 'evaluation' => $evaluation,
                 'lus_score' => $lus_score,
                 'confidence_score' => $evaluation['confidence']
             ]);

             return $result;

         } catch (Exception $e) {
             return new WP_Error('evaluation_error', $e->getMessage());
         }
     }

     public function process_transcription($recording_id) {
         try {
             $recording = $this->db->get_row($this->db->prepare(
                 "SELECT * FROM {$this->db->prefix}ra_recordings WHERE id = %d",
                 $recording_id
             ));

             if (!$recording) {
                 throw new Exception('Recording not found');
             }

             $transcription = $this->transcribe_audio($recording->audio_file_path);
             if (is_wp_error($transcription)) {
                 throw new Exception($transcription->get_error_message());
             }

             // Store transcription
             $this->db->update(
                 $this->db->prefix . 'ra_recordings',
                 ['transcription' => $transcription],
                 ['id' => $recording_id]
             );

             // Schedule evaluation
             wp_schedule_single_event(time(), 'ra_process_evaluation', [$recording_id]);

             return true;
         } catch (Exception $e) {
             error_log('Transcription error: ' . $e->getMessage());
             return false;
         }
     }

     private function transcribe_audio($file_path) {
         if (!$this->api_key) {
             return new WP_Error('missing_api_key', 'OpenAI API key not configured');
         }

         $full_path = wp_upload_dir()['basedir'] . $file_path;
         if (!file_exists($full_path)) {
             return new WP_Error('file_not_found', 'Audio file not found');
         }

         $response = wp_remote_post('https://api.openai.com/v1/audio/transcriptions', [
             'headers' => [
                 'Authorization' => 'Bearer ' . $this->api_key
             ],
             'timeout' => 60,
             'body' => [
                 'file' => new CURLFile($full_path),
                 'model' => 'whisper-1',
                 'language' => 'sv',
                 'response_format' => 'text'
             ]
         ]);

         if (is_wp_error($response)) {
             return $response;
         }

         $body = wp_remote_retrieve_body($response);
         $code = wp_remote_retrieve_response_code($response);

         if ($code !== 200) {
             return new WP_Error('transcription_error', 'Failed to transcribe audio');
         }

         return $body;
     }

     private function evaluate_reading($transcription, $expected_text) {
         $prompt = <<<EOT
 Du är en expert på att utvärdera svenska läsfärdigheter. Analysera följande högläsning och jämför med förväntad text.
 Fokusera på:
 1. Precision (ordidentifiering)
 2. Flyt (smidigt läsande)
 3. Uttal
 4. Läshastighet
 5. Förståelseindikatorer

 Förväntad text:
 $expected_text

 Faktisk transkription:
 $transcription

 Ge strukturerad utvärdering med poäng (0-100) för varje aspekt och total konfidensnivå.
 EOT;

         $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
             'headers' => [
                 'Authorization' => 'Bearer ' . $this->api_key,
                 'Content-Type' => 'application/json'
             ],
             'body' => json_encode([
                 'model' => 'gpt-4',
                 'messages' => [
                     ['role' => 'system', 'content' => 'Du är en expert på att utvärdera svenska läsfärdigheter.'],
                     ['role' => 'user', 'content' => $prompt]
                 ],
                 'temperature' => 0.3
             ])
         ]);

         if (is_wp_error($response)) {
             return $response;
         }

         $body = json_decode(wp_remote_retrieve_body($response), true);
         return $this->parse_gpt_response($body);
     }

     private function calculate_lus_score($evaluation) {
         $weights = [
             'accuracy' => 0.35,
             'fluency' => 0.25,
             'pronunciation' => 0.20,
             'speed' => 0.10,
             'comprehension' => 0.10
         ];

         $weighted_sum = 0;
         foreach ($weights as $metric => $weight) {
             $weighted_sum += $evaluation[$metric] * $weight;
         }

         // Convert to 1-20 scale using logarithmic scaling
         $normalized = $weighted_sum / 100;
         $lus_score = round(log10($normalized * 9 + 1) * 20);

         return max(1, min(20, $lus_score));
     }

     private function store_ai_evaluation($recording_id, $data) {
         $result = $this->db->insert(
             $this->db->prefix . 'ra_ai_evaluations',
             [
                 'recording_id' => $recording_id,
                 'evaluation_data' => json_encode($data['evaluation']),
                 'lus_score' => $data['lus_score'],
                 'confidence_score' => $data['confidence_score'],
                 'created_at' => current_time('mysql')
             ],
             ['%d', '%s', '%f', '%f', '%s']
         );

         if ($result === false) {
             return new WP_Error('db_error', 'Failed to store AI evaluation');
         }

         return [
             'evaluation_id' => $this->db->insert_id,
             'lus_score' => $data['lus_score'],
             'confidence_score' => $data['confidence_score']
         ];
     }

     private function parse_gpt_response($response) {
         if (empty($response['choices'][0]['message']['content'])) {
             return new WP_Error('invalid_response', 'Invalid GPT response');
         }

         $content = $response['choices'][0]['message']['content'];
         preg_match_all('/(\w+):\s*(\d+)/', $content, $matches);

         $evaluation = [
             'accuracy' => 0,
             'fluency' => 0,
             'pronunciation' => 0,
             'speed' => 0,
             'comprehension' => 0,
             'confidence' => 0
         ];

         for ($i = 0; $i < count($matches[1]); $i++) {
             $metric = strtolower($matches[1][$i]);
             $score = intval($matches[2][$i]);
             if (isset($evaluation[$metric])) {
                 $evaluation[$metric] = $score;
             }
         }

         return $evaluation;
     }
 }
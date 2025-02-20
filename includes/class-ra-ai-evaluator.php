<?php
/**
 * includes/class-ra-ai-evaluator.php
 * Handles AI-powered evaluation of reading assessments
 *
 * @package ReadingAssessment
 * @subpackage ReadingAssessment/includes
 */

 class RA_AI_Evaluator {
     private $db;
     private $api_key;
     private $confidence_threshold = 0.85;
     private $batch_size = 10; // Process recordings in batches

     public function __construct() {
         global $wpdb;
         $this->db = $wpdb;
         $this->api_key = get_option('ra_openai_api_key');
     }

    // Unified processing method
    public function process_recording($recording_id) {
        try {
            $recording = $this->db->get_row($this->db->prepare(
                "SELECT r.*, p.content as passage_content
                 FROM {$this->db->prefix}ra_recordings r
                 JOIN {$this->db->prefix}ra_passages p ON r.passage_id = p.id
                 WHERE r.id = %d",
                $recording_id
            ));

            if (!$recording) {
                error_log('Recording not found: ' . $recording_id);
                return new WP_Error('invalid_recording', 'Recording not found');
            }

            // Check for transcription
            if (empty($recording->transcription)) {
                error_log('No transcription found, getting from audio file');
                $transcription_result = $this->transcribe_audio($recording->audio_file_path);

                if (is_wp_error($transcription_result)) {
                    error_log('Transcription error: ' . $transcription_result->get_error_message());
                    return $transcription_result;
                }

                // Update recording with transcription
                $this->db->update(
                    $this->db->prefix . 'ra_recordings',
                    ['transcription' => $transcription_result['transcription']],
                    ['id' => $recording_id]
                );

                $recording->transcription = $transcription_result['transcription'];
            }

            error_log('Processing recording with transcription: ' . $recording->transcription);

            // Now evaluate with the transcription
            $evaluation = $this->evaluate_reading($recording->transcription, $recording->passage_content);

            if (is_wp_error($evaluation)) {
                error_log('Evaluation error: ' . $evaluation->get_error_message());
                return $evaluation;
            }

            // Calculate LUS score
            $lus_score = $this->calculate_lus_score($evaluation);

            // Store evaluation
            return $this->store_ai_evaluation($recording_id, [
                'evaluation' => $evaluation,
                'lus_score' => $lus_score,
                'confidence_score' => isset($evaluation['confidence']) ? $evaluation['confidence'] : 0
            ]);

        } catch (Exception $e) {
            error_log('Process recording error: ' . $e->getMessage());
            return new WP_Error('processing_error', $e->getMessage());
        }
    }

    // CURL based
    public function transcribe_audio($file_path) {
        $full_path = wp_upload_dir()['basedir'] . $file_path;
        error_log('Full file path: ' . $full_path);
        error_log('Audio file size: ' . filesize($full_path) . ' bytes');

        if (!file_exists($full_path)) {
            error_log('transcribe_audio säger: File not found at path: ' . $full_path);
            return new WP_Error('file_not_found', 'Audio file not found');
        }

        try {
            $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');

            $post_data = [
                'file' => new CURLFile($full_path, 'audio/webm', basename($full_path)),
                'model' => 'whisper-1',
                'language' => 'sv',
                'response_format' => ['type' => 'text']
            ];

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post_data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->api_key
                ],
                CURLOPT_TIMEOUT => 120,
                CURLOPT_VERBOSE => true
            ]);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            error_log('CURL Response Code: ' . $http_code);

            if (curl_errno($ch)) {
                error_log('CURL Error: ' . curl_error($ch));
                curl_close($ch);
                return new WP_Error('curl_error', curl_error($ch));
            }

            curl_close($ch);

            if ($http_code !== 200) {
                error_log('API Error Response: ' . $response);
                return new WP_Error('transcription_error', 'Failed to transcribe audio: ' . $response);
            }

            // Return the transcription response directly
            return [
                'transcription' => $response,
                'status' => 'success'
            ];
        } catch (Exception $e) {
            error_log('Exception in transcribe_audio: ' . $e->getMessage());
            return new WP_Error('transcription_exception', $e->getMessage());
        }
    }

    public function get_evaluation_status($recording_id) {
        $recording = $this->get_recording($recording_id);
        if (!$recording) {
            return new WP_Error('invalid_recording', 'Recording not found');
        }

        $evaluation = $this->get_evaluation($recording_id);
        if (!$evaluation) {
            return [
                'has_transcription' => !empty($recording->transcription),
                'has_evaluation' => false,
                'status' => 'pending'
            ];
        }

        return [
            'has_transcription' => !empty($recording->transcription),
            'has_evaluation' => true,
            'lus_score' => $evaluation->lus_score,
            'confidence_score' => $evaluation->confidence_score,
            'evaluation_data' => json_decode($evaluation->evaluation_data),
            'created_at' => $evaluation->created_at,
            'status' => 'completed'
        ];
    }

    public function evaluate_reading($transcription, $expected_text) {
        error_log('=== Starting AI evaluate_reading ===');
        error_log('Transcription: ' . $transcription);
        error_log('Expected text: ' . $expected_text);

        $prompt = <<<EOT
        Du är en expert på att utvärdera svenska läsfärdigheter. Analysera följande högläsning och jämför med förväntad text.

        Ge detaljerad analys av:

        1. Precision:
        - Antal korrekt lästa ord
        - Felläsningar och typer av fel
        - Självrättningar

        2. Flyt:
        - Frasering och naturligt flöde
        - Pauser och avbrott
        - Läsrytm

        3. Uttal:
        - Fonetisk precision
        - Dialektala variationer
        - Konsekvent uttal

        4. Läshastighet:
        - Ord per minut
        - Balans mellan hastighet och förståelse
        - Variationer i tempo

        5. Förståelse:
        - Betoning av viktiga ord
        - Intonation vid skiljetecken
        - Anpassning till textens innehåll
        - Låter den läsande som att den förstår?

        Förväntad text:
        $expected_text

        Faktisk transkription:
        $transcription


        Svara med ett JSON-objekt som innehåller följande utvärderingar (skala 0-100):
        {
            "accuracy": (precision i läsningen),
            "fluency": (läsflyt),
            "pronunciation": (uttal),
            "speed": (läshastighet),
            "comprehension": (läsförståelse),
            "confidence": (din konfidensnivå för bedömningen),
            "statistics": {
                "words_per_minute": (antal ord per minut),
                "correct_words": (antal korrekt lästa ord),
                "errors": (antal fel)
            }
        }
        EOT;

        error_log('Sending prompt to OpenAI');

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

        error_log('OpenAI response: ' . print_r($response, true));

        if (is_wp_error($response)) {
            error_log('OpenAI request failed: ' . $response->get_error_message());
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

     public function store_ai_evaluation($recording_id, $data) {
        error_log('Storing AI evaluation: ' . print_r($data, true));

        $result = $this->db->insert(
            $this->db->prefix . 'ra_ai_evaluations',
            [
                'recording_id' => $recording_id,
                'evaluation_data' => json_encode($data['evaluation']),
                'lus_score' => floatval($data['lus_score']),
                'confidence_score' => floatval($data['confidence_score']),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%f', '%f', '%s']
        );

        if ($result === false) {
            error_log('Failed to store AI evaluation: ' . $this->db->last_error);
            return new WP_Error('db_error', 'Failed to store AI evaluation');
        }

        return [
            'evaluation_id' => $this->db->insert_id,
            'lus_score' => floatval($data['lus_score']),
            'confidence_score' => floatval($data['confidence_score'])
        ];
    }

     private function parse_gpt_response($response) {
        error_log('Parsing GPT response: ' . print_r($response, true));

        if (empty($response['choices'][0]['message']['content'])) {
            error_log('Invalid GPT response - no content');
            return new WP_Error('invalid_response', 'Invalid GPT response');
        }

        $content = $response['choices'][0]['message']['content'];
        error_log('Raw GPT content: ' . $content);

        // Try to parse JSON response
        $data = json_decode($content, true);
        if ($data) {
            error_log('Successfully parsed JSON response: ' . print_r($data, true));
            return $data;
        }

        // Fallback to regex pattern matching if not JSON
        preg_match_all('/(\w+):\s*(\d+(?:\.\d+)?)/', $content, $matches);
        error_log('Regex matches: ' . print_r($matches, true));

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
            $score = floatval($matches[2][$i]);
            if (isset($evaluation[$metric])) {
                $evaluation[$metric] = $score;
            }
        }

        error_log('Final parsed evaluation: ' . print_r($evaluation, true));
        return $evaluation;
    }

     private function get_recording($recording_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ra_recordings';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $recording_id
        ));
    }

    private function get_evaluation($recording_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ra_ai_evaluations';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE recording_id = %d
            ORDER BY created_at DESC LIMIT 1",
            $recording_id
        ));
    }
 }

<?php
/**
 * Handles assessment evaluation and scoring.
 *
 * @package    ReadingAssessment
 * @subpackage ReadingAssessment/includes
 */

class Reading_Assessment_Evaluator {
    
    private $db;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }

    /**
     * Evaluate user answers and calculate scores
     *
     * @param int $recording_id Recording ID
     * @param array $answers Array of question ID => answer pairs
     * @return array|WP_Error Assessment results or error
     */
    public function evaluate_assessment($recording_id, $answers) {
        // Get recording info
        $recording = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->db->prefix}ra_recordings WHERE id = %d",
                $recording_id
            )
        );

        if (!$recording) {
            return new WP_Error('invalid_recording', __('Invalid recording ID.', 'reading-assessment'));
        }

        // Get questions for the passage
        $questions = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->db->prefix}ra_questions WHERE passage_id = %d",
                $recording->passage_id
            )
        );

        if (!$questions) {
            return new WP_Error('no_questions', __('No questions found for this passage.', 'reading-assessment'));
        }

        $total_weight = 0;
        $score = 0;
        $responses = array();

        // Evaluate each answer
        foreach ($questions as $question) {
            $total_weight += $question->weight;
            
            if (isset($answers[$question->id])) {
                $is_correct = $this->check_answer($question->correct_answer, $answers[$question->id]);
                $question_score = $is_correct ? $question->weight : 0;
                $score += $question_score;

                // Store response
                $responses[] = array(
                    'recording_id' => $recording_id,
                    'question_id' => $question->id,
                    'user_answer' => $answers[$question->id],
                    'is_correct' => $is_correct,
                    'score' => $question_score
                );
            }
        }

        // Calculate normalized score (0-100)
        $normalized_score = ($score / $total_weight) * 100;

        // Store responses in database
        foreach ($responses as $response) {
            $this->db->insert(
                $this->db->prefix . 'ra_responses',
                $response,
                array('%d', '%d', '%s', '%d', '%f')
            );
        }

        // Store assessment result
        $assessment_data = array(
            'recording_id' => $recording_id,
            'total_score' => $score,
            'normalized_score' => $normalized_score,
            'completed_at' => current_time('mysql')
        );

        $this->db->insert(
            $this->db->prefix . 'ra_assessments',
            $assessment_data,
            array('%d', '%f', '%f', '%s')
        );

        return array(
            'score' => $score,
            'normalized_score' => $normalized_score,
            'total_questions' => count($questions),
            'correct_answers' => count(array_filter($responses, function($r) { return $r['is_correct']; })),
            'assessment_id' => $this->db->insert_id
        );
    }

    /**
     * Check if an answer is correct
     *
     * @param string $correct_answer The correct answer
     * @param string $user_answer The user's answer
     * @return boolean True if correct, false if not
     */
    private function check_answer($correct_answer, $user_answer) {
        // Convert both answers to lowercase and trim whitespace
        $correct_answer = strtolower(trim($correct_answer));
        $user_answer = strtolower(trim($user_answer));

        // Calculate similarity percentage
        $similarity = $this->calculate_similarity($correct_answer, $user_answer);

        // Consider answer correct if similarity is above 90%
        return $similarity >= 90;
    }

    /**
     * Calculate similarity between two strings
     *
     * @param string $str1 First string
     * @param string $str2 Second string
     * @return float Similarity percentage
     */
    private function calculate_similarity($str1, $str2) {
        $leven = levenshtein($str1, $str2);
        $max_len = max(strlen($str1), strlen($str2));
        
        if ($max_len === 0) {
            return 100;
        }

        return (1 - ($leven / $max_len)) * 100;
    }

    /**
     * Get assessment results
     *
     * @param int $assessment_id Assessment ID
     * @return array|WP_Error Assessment details or error
     */
    public function get_assessment_results($assessment_id) {
        $assessment = $this->db->get_row(
            $this->db->prepare(
                "SELECT a.*, r.user_id, r.passage_id, r.duration
                 FROM {$this->db->prefix}ra_assessments a
                 JOIN {$this->db->prefix}ra_recordings r ON a.recording_id = r.id
                 WHERE a.id = %d",
                $assessment_id
            ),
            ARRAY_A
        );

        if (!$assessment) {
            return new WP_Error('invalid_assessment', __('Invalid assessment ID.', 'reading-assessment'));
        }

        // Get detailed responses
        $responses = $this->db->get_results(
            $this->db->prepare(
                "SELECT r.*, q.question_text
                 FROM {$this->db->prefix}ra_responses r
                 JOIN {$this->db->prefix}ra_questions q ON r.question_id = q.id
                 WHERE r.recording_id = %d",
                $assessment['recording_id']
            ),
            ARRAY_A
        );

        $assessment['responses'] = $responses;
        return $assessment;
    }
}
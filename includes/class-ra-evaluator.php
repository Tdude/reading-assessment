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

    public function evaluate_assessment($recording_id, $answers) {
        // Get recording details including passage_id
        $recording = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->db->prefix}ra_recordings WHERE id = %d",
                $recording_id
            )
        );

        if (!$recording) {
            return new WP_Error('invalid_recording', 'Invalid recording ID');
        }

        // Get all questions for this passage
        $questions = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->db->prefix}ra_questions
                WHERE passage_id = %d",
                $recording->passage_id
            )
        );

        $total_score = 0;
        $total_weight = 0;
        $correct_answers = 0;

        // Evaluate each answer
        foreach ($questions as $question) {
            if (isset($answers[$question->id])) {
                $answer_score = $this->evaluate_answer(
                    $question->correct_answer,
                    $answers[$question->id],
                    $question->weight
                );

                $total_score += $answer_score['score'];
                $total_weight += $question->weight;

                if ($answer_score['is_correct']) {
                    $correct_answers++;
                }

                // Store response in database
                $this->store_response($recording_id, $question->id, $answers[$question->id], $answer_score);
            }
        }

        // Calculate normalized score (0-100)
        $normalized_score = ($total_weight > 0) ? ($total_score / $total_weight) * 100 : 0;

        // Store assessment result
        $assessment_id = $this->store_assessment($recording_id, $total_score, $normalized_score);

        return [
            'assessment_id' => $assessment_id,
            'score' => $total_score,
            'normalized_score' => $normalized_score,
            'correct_answers' => $correct_answers,
            'total_questions' => count($questions)
        ];
    }

    private function evaluate_answer($correct_answer, $user_answer, $weight) {
        // Convert to lowercase and trim whitespace
        $correct_answer = strtolower(trim($correct_answer));
        $user_answer = strtolower(trim($user_answer));

        // Calculate similarity percentage
        $similarity = $this->calculate_similarity($correct_answer, $user_answer);

        // Consider answer correct if similarity is above 90%
        $is_correct = ($similarity >= 90);

        // Calculate weighted score
        $score = $is_correct ? $weight : 0;

        return [
            'is_correct' => $is_correct,
            'score' => $score,
            'similarity' => $similarity
        ];
    }

    private function calculate_similarity($str1, $str2) {
        $leven = levenshtein($str1, $str2);
        $max_len = max(strlen($str1), strlen($str2));
        if ($max_len === 0) {
            return 100;
        }
        return (1 - ($leven / $max_len)) * 100;
    }

    private function store_response($recording_id, $question_id, $user_answer, $evaluation) {
        return $this->db->insert(
            $this->db->prefix . 'ra_responses',
            [
                'recording_id' => $recording_id,
                'question_id' => $question_id,
                'user_answer' => $user_answer,
                'is_correct' => $evaluation['is_correct'],
                'score' => $evaluation['score'],
                'similarity' => $evaluation['similarity']
            ],
            ['%d', '%d', '%s', '%d', '%f', '%f']
        );
    }

    private function store_assessment($recording_id, $total_score, $normalized_score) {
        $this->db->insert(
            $this->db->prefix . 'ra_assessments',
            [
                'recording_id' => $recording_id,
                'total_score' => $total_score,
                'normalized_score' => $normalized_score,
                'completed_at' => current_time('mysql')
            ],
            ['%d', '%f', '%f', '%s']
        );
        return $this->db->insert_id;
    }
  }
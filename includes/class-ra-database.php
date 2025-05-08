<?php
/**
 * File: includes/class-ra-database.php
 * Handles database operations for the plugin.
 *
 * @package ReadingAssessment
 * @subpackage ReadingAssessment/includes
 */
class RA_Database {
    private $db;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }


    // Add to class-ra-database.php
    private function get_upload_path() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/reading-assessment';
    }

    private function get_recording_path($user_id) {
        $year_month = date('Y/m');
        return $this->get_upload_path() . '/' . $year_month . '/' . $user_id;
    }

    /**
     * Create new passage
     *
     * @param array $data Passage data
     * @return int|WP_Error New passage ID or error
     */
    public function create_passage($data) {
        try {
            // Validate required fields
            if (empty($data['title']) || empty($data['content'])) {
                return new WP_Error('missing_fields', __('Title and content are required.', 'reading-assessment'));
            }

            // Set default time limit if not provided
            if (!isset($data['time_limit'])) {
                $data['time_limit'] = 180; // 3 minutes default
            }

            $insert_data = array(
                'title' => $data['title'],
                'content' => $data['content'],
                'time_limit' => absint($data['time_limit']),
                'difficulty_level' => isset($data['difficulty_level']) ? absint($data['difficulty_level']) : 1, // Default to level 1
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'audio_file' => isset($data['audio_file']) ? $data['audio_file'] : null
            );

            $insert_format = array(
                '%s', // title
                '%s', // content
                '%d', // time_limit
                '%d', // difficulty_level
                '%d', // created_by
                '%s', // created_at
                '%s'  // audio_file
            );

            // Debug output
            error_log('Attempting to insert passage with data: ' . print_r($insert_data, true));

            $result = $this->db->insert(
                $this->db->prefix . 'ra_passages',
                $insert_data,
                $insert_format
            );

            if ($result === false) {
                error_log('Database error: ' . $this->db->last_error);
                return new WP_Error('db_error', __('Failed to create passage. Database error: ', 'reading-assessment') . $this->db->last_error);
            }

            return $this->db->insert_id;
        } catch (Exception $e) {
            error_log('Exception in create_passage: ' . $e->getMessage());
            return new WP_Error('exception', __('Error creating passage: ', 'reading-assessment') . $e->getMessage());
        }
    }

    /**
     * Create new question
     *
     * @param array $data Question data
     * @return int|WP_Error New question ID or error
     */
    public function create_question($data) {
        try {
            if (empty($data['passage_id']) || empty($data['question_text']) || empty($data['correct_answer'])) {
                return new WP_Error('missing_fields', __('All fields are required.', 'reading-assessment'));
            }

            $result = $this->db->insert(
                $this->db->prefix . 'ra_questions',
                array(
                    'passage_id' => $data['passage_id'],
                    'question_text' => $data['question_text'],
                    'correct_answer' => $data['correct_answer'],
                    'weight' => isset($data['weight']) ? floatval($data['weight']) : 1.0
                ),
                array('%d', '%s', '%s', '%f')
            );

            if ($result === false) {
                return new WP_Error('db_error', __('Failed to create question.', 'reading-assessment'));
            }

            return $this->db->insert_id;
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Update existing question
     *
     * @param int $question_id Question ID
     * @param array $data Updated question data
     * @return bool|WP_Error True on success, error object on failure
     */
    public function update_question($question_id, $data) {
        try {
            if (empty($data['question_text']) || empty($data['correct_answer'])) {
                return new WP_Error('missing_fields', __('Question text and correct answer are required.', 'reading-assessment'));
            }

            $result = $this->db->update(
                $this->db->prefix . 'ra_questions',
                array(
                    'question_text' => $data['question_text'],
                    'correct_answer' => $data['correct_answer'],
                    'weight' => isset($data['weight']) ? floatval($data['weight']) : 1.0
                ),
                array('id' => $question_id),
                array('%s', '%s', '%f'),
                array('%d')
            );

            if ($result === false) {
                return new WP_Error('db_error', __('Failed to update question.', 'reading-assessment'));
            }

            return true;
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Delete question
     *
     * @param int $question_id Question ID
     * @return bool|WP_Error True on success, error object on failure
     */
    /*
    public function delete_question($question_id) {
        try {
            // First, check if there are any responses linked to this question
            $responses = $this->db->get_var(
                $this->db->prepare(
                    "SELECT COUNT(*) FROM {$this->db->prefix}ra_responses WHERE question_id = %d",
                    $question_id
                )
            );

            if ($responses > 0) {
                return new WP_Error(
                    'has_responses',
                    __('Cannot delete question because it has responses. Consider deactivating it instead.', 'reading-assessment')
                );
            }

            $result = $this->db->delete(
                $this->db->prefix . 'ra_questions',
                array('id' => $question_id),
                array('%d')
            );

            if ($result === false) {
                return new WP_Error('db_error', __('Failed to delete question.', 'reading-assessment'));
            }

            return true;
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }
*/
    public function delete_question($question_id) {
        try {
            // Start transaction
            $this->db->query('START TRANSACTION');

            // First delete all responses for this question
            $this->db->delete(
                $this->db->prefix . 'ra_responses',
                array('question_id' => $question_id),
                array('%d')
            );

            // Then delete the question
            $result = $this->db->delete(
                $this->db->prefix . 'ra_questions',
                array('id' => $question_id),
                array('%d')
            );

            if ($result === false) {
                $this->db->query('ROLLBACK');
                return new WP_Error('db_error', __('Failed to delete question.', 'reading-assessment'));
            }

            $this->db->query('COMMIT');
            return true;
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            return new WP_Error('exception', $e->getMessage());
        }
    }


    /**
     * Get a single question by ID
     *
     * @param int $question_id Question ID
     * @return object|null Question object or null if not found
     */
    public function get_question($question_id) {
        try {
            $question = $this->db->get_row(
                $this->db->prepare(
                    "SELECT q.*, p.title as passage_title
                    FROM {$this->db->prefix}ra_questions q
                    LEFT JOIN {$this->db->prefix}ra_passages p ON q.passage_id = p.id
                    WHERE q.id = %d",
                    $question_id
                )
            );

            if ($question === null) {
                return new WP_Error('not_found', __('Question not found.', 'reading-assessment'));
            }

            return $question;
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Get recording by ID
     *
     * @param int $recording_id Recording ID
     * @return object|null Recording object or null if not found
     */
    public function get_recording($recording_id) {
        error_log('Fetching recording: ' . $recording_id);

        $recording = $this->db->get_row($this->db->prepare(
            "SELECT r.*, u.ID as user_id,
                    AVG(a.normalized_score) as manual_lus_score,
                    COUNT(a.id) as assessment_count
             FROM {$this->db->prefix}ra_recordings r
             JOIN {$this->db->users} u ON r.user_id = u.ID
             LEFT JOIN {$this->db->prefix}ra_assessments a ON r.id = a.recording_id
             WHERE r.id = %d
             GROUP BY r.id, u.ID",
            $recording_id
        ));

        error_log('Found recording data: ' . print_r($recording, true));
        return $recording;
    }

    /**
     * Save recording data with more detailed information
     *
     * @param array $data Recording data including file info and duration
     * @return int|WP_Error New recording ID or error
     */
    public function save_recording($data) {
        $user_id = get_current_user_id();
        $recording_path = $this->get_recording_path($user_id);

        if (!file_exists($recording_path)) {
            wp_mkdir_p($recording_path);
        }

        $result = $this->db->insert(
            $this->db->prefix . 'ra_recordings',  // Fixed prefix
            array(
                'user_id' => $user_id,
                'passage_id' => isset($data['passage_id']) ? $data['passage_id'] : 0,
                'audio_file_path' => str_replace($this->get_upload_path(), '', $data['file_path']),
                'duration' => isset($data['duration']) ? $data['duration'] : 0,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%d', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to save recording data.', 'reading-assessment'));
        }

        return $this->db->insert_id;
    }

    /**
     * Get recent recordings with pagination
     */
    public function get_recent_recordings($per_page, $offset, $passage_filter = 0) {
        $where_conditions = array('1=1');
        $where_args = array();

        if ($passage_filter) {
            $where_conditions[] = 'r.passage_id = %d';
            $where_args[] = $passage_filter;
        }

        $where_clause = ' WHERE ' . implode(' AND ', $where_conditions);

        $query = "SELECT r.*, u.display_name, u.ID as user_id,
                r.audio_file_path, r.duration,
                DATE_FORMAT(r.created_at, '%Y/%m') as date_path,
                COUNT(a.id) as assessment_count,
                AVG(a.normalized_score) as avg_assessment_score
            FROM {$this->db->prefix}ra_recordings r
            JOIN {$this->db->users} u ON r.user_id = u.ID
            LEFT JOIN {$this->db->prefix}ra_assessments a ON r.id = a.recording_id
            {$where_clause}
            GROUP BY r.id
            ORDER BY r.created_at DESC
            LIMIT %d OFFSET %d";

        return $this->db->get_results(
            $this->db->prepare(
                $query,
                array_merge($where_args, array($per_page, $offset))
            )
        );
    }

    /**
     * Get total count of recordings with optional passage filter
     */
    public function get_recordings_count($passage_filter = 0) {
        $where = array('1=1');
        $where_args = array();

        if ($passage_filter) {
            $where[] = 'passage_id = %d';
            $where_args[] = $passage_filter;
        }

        $query = "SELECT COUNT(DISTINCT id)
            FROM {$this->db->prefix}ra_recordings
            WHERE " . implode(' AND ', $where);

        if (!empty($where_args)) {
            $query = $this->db->prepare($query, $where_args);
        }

        return (int) $this->db->get_var($query);
    }

    /**
     * Save response to questions in the database
     *
     * @param array $data {
     *     Array of response data.
     *     @type int    $recording_id The ID of the recording being answered
     *     @type int    $question_id  The ID of the question being answered
     *     @type string $user_answer  The user's answer text to the question
     * }
     *
     * @return int|false The response ID if successful, false on failure
     */
    public function save_response($data) {
        error_log('Attempting to save response: ' . print_r($data, true));

        try {
            // Check if created_at column exists
            $columns = $this->db->get_col("SHOW COLUMNS FROM {$this->db->prefix}ra_responses");
            $has_created_at = in_array('created_at', $columns);

            // Get the question with passage info
            $question = $this->get_question($data['question_id']);
            if (!$question) {
                error_log('Question not found for ID: ' . $data['question_id']);
                return false;
            }

            // Calculate similarity and correctness
            $user_answer = strtolower(trim(sanitize_text_field($data['user_answer'])));
            $correct_answer = strtolower(trim($question->correct_answer));

            $similarity_score = $this->calculate_similarity($user_answer, $correct_answer);
            $is_correct = $similarity_score >= 80 ? 1 : 0;

            error_log(sprintf(
                'Answer comparison - Passage: "%s", Question: "%s", User Answer: "%s", Correct Answer: "%s", Similarity: %f, Is Correct: %d',
                $question->passage_title,
                $question->question_text,
                $user_answer,
                $correct_answer,
                $similarity_score,
                $is_correct
            ));

            $insert_data = array(
                'recording_id' => absint($data['recording_id']),
                'question_id' => absint($data['question_id']),
                'user_answer' => sanitize_text_field($data['user_answer']),
                'is_correct' => $is_correct,
                'score' => $similarity_score
            );

            $format = array('%d', '%d', '%s', '%d', '%f');

            // Only add created_at if column exists
            if ($has_created_at) {
                $insert_data['created_at'] = current_time('mysql');
                $format[] = '%s';
            }

            $result = $this->db->insert(
                $this->db->prefix . 'ra_responses',
                $insert_data,
                $format
            );

            if ($result === false) {
                error_log('Database error when saving response: ' . $this->db->last_error);
                return false;
            }

            error_log('Successfully saved response with ID: ' . $this->db->insert_id);
            return $this->db->insert_id;

        } catch (Exception $e) {
            error_log('Exception when saving response: ' . $e->getMessage());
            return false;
        }
    }

    private function calculate_similarity($str1, $str2) {
        if (empty($str1) || empty($str2)) {
            return 0;
        }

        // Convert special characters and remove punctuation
        $str1 = iconv('UTF-8', 'ASCII//TRANSLIT', $str1);
        $str2 = iconv('UTF-8', 'ASCII//TRANSLIT', $str2);

        // Remove punctuation and extra spaces
        $str1 = preg_replace('/[^\p{L}\p{N}\s]/u', '', $str1);
        $str2 = preg_replace('/[^\p{L}\p{N}\s]/u', '', $str2);

        // Normalize whitespace
        $str1 = preg_replace('/\s+/', ' ', trim($str1));
        $str2 = preg_replace('/\s+/', ' ', trim($str2));

        // Calculate Levenshtein distance
        $levenshtein = levenshtein($str1, $str2);

        // Calculate similarity percentage
        $maxLength = max(strlen($str1), strlen($str2));
        if ($maxLength === 0) {
            return 0;
        }

        $similarity = (1 - ($levenshtein / $maxLength)) * 100;
        return max(0, min(100, $similarity));
    }


    /**
     * Get recordings for a user
     *
     * @param int $user_id User ID
     * @param int $limit Optional limit of results
     * @return array Array of recording objects
     */
    public function get_user_recordings($user_id, $limit = 10) {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT r.*, p.title as passage_title
                 FROM {$this->db->prefix}ra_recordings r
                 LEFT JOIN {$this->db->prefix}ra_passages p ON r.passage_id = p.id
                 WHERE r.user_id = %d
                 ORDER BY r.created_at DESC
                 LIMIT %d",
                $user_id,
                $limit
            )
        );
    }

   /**
     * Update recording status and metadata
     *
     * @param int $recording_id Recording ID
     * @param array $data Updated recording data
     * @return bool|WP_Error True on success, error object on failure
     */
    public function update_recording($recording_id, $data) {
        $update_data = array();
        $format = array();

        // Only update provided fields
        if (isset($data['passage_id'])) {
            $update_data['passage_id'] = $data['passage_id'];
            $format[] = '%d';
        }
        if (isset($data['duration'])) {
            $update_data['duration'] = $data['duration'];
            $format[] = '%d';
        }
        if (isset($data['status'])) {
            $update_data['status'] = $data['status'];
            $format[] = '%s';
        }
        if (isset($data['file_path'])) {
            $update_data['file_path'] = $data['file_path'];
            $format[] = '%s';
        }

        $update_data['updated_at'] = current_time('mysql');
        $format[] = '%s';

        $result = $this->db->update(
            $this->db->prefix . 'ra_recordings',
            $update_data,
            array('id' => $recording_id),
            $format,
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update recording.', 'reading-assessment'));
        }
        return true;
    }

    /**
     * Delete recording and its file
     *
     * @param int $recording_id Recording ID
     * @return bool True on success, false on failure
     */
    public function delete_recording($recording_id) {
        error_log('Attempting to delete recording: ' . $recording_id);

        // Get recording info to delete file
        $recording = $this->get_recording($recording_id);
        if (!$recording) {
            error_log('Recording not found for deletion');
            return false;
        }

        // Delete file if it exists
        if ($recording->audio_file_path) {
            $file_path = $this->get_upload_path() . $recording->audio_file_path;
            error_log('Attempting to delete file: ' . $file_path);

            if (file_exists($file_path)) {
                if (!@unlink($file_path)) {
                    error_log('Failed to delete file: ' . $file_path);
                } else {
                    error_log('File deleted successfully');
                }
            } else {
                error_log('File does not exist: ' . $file_path);
            }
        }

        // Delete database record
        $result = $this->db->delete(
            $this->db->prefix . 'ra_recordings',
            array('id' => $recording_id),
            array('%d')
        );

        error_log('Database deletion result: ' . ($result !== false ? 'success' : 'failed') .
                ' Last DB error: ' . $this->db->last_error);

        return $result !== false;
    }

    /**
     * Get unassigned recordings with user details and optional pagination
     *
     * Returns recordings that have no passage_id (0 or NULL) along with user display names.
     * Results are ordered by creation date, newest first.
     *
     * @param int $limit Maximum number of records to return
     * @param int $offset Number of records to skip (for pagination)
     * @return array Array of recording objects with user details
     */
    public function get_unassigned_recordings($limit = 20, $offset = 0) {
        return $this->db->get_results($this->db->prepare(
            "SELECT r.*, u.display_name
            FROM {$this->db->prefix}ra_recordings r
            JOIN {$this->db->users} u ON r.user_id = u.ID
            WHERE r.passage_id = 0 OR r.passage_id IS NULL
            ORDER BY r.created_at DESC
            LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }

    /**
     * Get total count of unassigned recordings
     *
     * Counts recordings that have no passage_id (0 or NULL). Used for pagination
     * and statistics.
     *
     * @return int Total number of unassigned recordings
     */
    public function get_total_unassigned_recordings() {
        return (int)$this->db->get_var(
            "SELECT COUNT(*)
            FROM {$this->db->prefix}ra_recordings
            WHERE passage_id = 0 OR passage_id IS NULL"
        );
    }

    /**
     * Update passage assignment for a single recording
     *
     * Associates a recording with a specific passage by updating its passage_id.
     * Used for both individual and bulk updates.
     *
     * @param int $recording_id ID of the recording to update
     * @param int $passage_id ID of the passage to associate with
     * @return bool True on successful update, false on failure
     */
    public function update_recording_passage($recording_id, $passage_id) {
        return $this->db->update(
            $this->db->prefix . 'ra_recordings',
            array('passage_id' => $passage_id),
            array('id' => $recording_id),
            array('%d'),
            array('%d')
        );
    }

    /**
     * Bulk update passage assignments for multiple recordings
     *
     * Takes an array of recording_id => passage_id pairs and updates them all.
     * Returns the number of successful updates.
     *
     * Example:
     * $recording_passages = [
     *     1 => 5,  // Assign recording 1 to passage 5
     *     2 => 5,  // Assign recording 2 to passage 5
     *     3 => 7   // Assign recording 3 to passage 7
     * ];
     *
     * @param array $recording_passages Associative array of recording_id => passage_id pairs
     * @return int Number of successfully updated recordings
     */
    public function bulk_update_recordings($recording_passages) {
        $success_count = 0;

        foreach ($recording_passages as $recording_id => $passage_id) {
            if ($this->update_recording_passage($recording_id, $passage_id)) {
                $success_count++;
            }
        }

        return $success_count;
    }

    /**
     * Get passage by ID
     *
     * @param int $passage_id Passage ID
     * @return object|null Passage object or null if not found
     */
    public function get_passage($passage_id) {
        return $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->db->prefix}ra_passages WHERE id = %d",
                $passage_id
            )
        );
    }

    /**
     * Get all passages
     *
     * @return array Array of passage objects
     */
    public function get_all_passages() {
        return $this->db->get_results(
            "SELECT * FROM {$this->db->prefix}ra_passages
             ORDER BY created_at DESC"
        );
    }

    /**
     * Get questions for passage
     *
     * @param int $passage_id Passage ID
     * @return array Array of question objects
     */
    public function get_questions_for_passage($passage_id) {
        error_log('=== Getting questions from database for passage: ' . $passage_id . ' ===');

        $query = $this->db->prepare(
            "SELECT id, question_text, correct_answer, weight
             FROM {$this->db->prefix}ra_questions
             WHERE passage_id = %d
             ORDER BY id ASC",
            $passage_id
        );

        error_log('Executing query: ' . $query);

        $questions = $this->db->get_results($query, OBJECT);

        error_log('Database returned questions: ' . print_r($questions, true));
        return $questions;
    }

    /**
     * Assign passage to user
     */
    public function assign_passage_to_user($passage_id, $user_id, $assigned_by, $due_date = null) {
        return $this->db->insert(
            $this->db->prefix . 'ra_assignments',
            array(
                'passage_id' => $passage_id,
                'user_id' => $user_id,
                'assigned_by' => $assigned_by,
                'due_date' => $due_date,
                'status' => 'pending'
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );
    }

    /**
     * Get passages assigned to user
     */
    public function get_user_assigned_passages($user_id) {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT p.*, a.assigned_at, a.due_date, a.status
                FROM {$this->db->prefix}ra_passages p
                JOIN {$this->db->prefix}ra_assignments a ON p.id = a.passage_id
                WHERE a.user_id = %d
                ORDER BY a.assigned_at DESC",
                $user_id
            )
        );
    }

    /* FOR DEBUGGING */
    function get_assigned_passages() {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT DISTINCT p.*
            FROM {$wpdb->prefix}passages p
            LEFT JOIN {$wpdb->prefix}passage_assignments pa
            ON p.id = pa.passage_id
            WHERE pa.user_id = %d",
            get_current_user_id()
        );

        // Debug output
        error_log('Passages Query: ' . $query);
        $results = $wpdb->get_results($query);
        error_log('Number of results: ' . count($results));

        return $results;
    }


    /**
     * Remove assignment
     */
    public function delete_assignment($assignment_id) {
        return $this->db->delete(
            $this->db->prefix . 'ra_assignments',
            array('id' => $assignment_id),
            array('%d')
        );
    }

    /**
     * Update assignment status
     */
    public function update_assignment_status($assignment_id, $status) {
        return $this->db->update(
            $this->db->prefix . 'ra_assignments',
            array('status' => $status),
            array('id' => $assignment_id),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Get all assignments with user and passage details
     */
    public function get_all_assignments() {
        return $this->db->get_results(
            "SELECT a.*,
                    u.display_name as user_name,
                    p.title as passage_title
            FROM {$this->db->prefix}ra_assignments a
            JOIN {$this->db->users} u ON a.user_id = u.ID
            JOIN {$this->db->prefix}ra_passages p ON a.passage_id = p.id
            ORDER BY a.assigned_at DESC"
        );
    }

    /**
     * Save assessment results
     *
     * @param array $data Assessment data
     * @return int|WP_Error New assessment ID or error
     */
    public function save_assessment_results($data) {
        $result = $this->db->insert(
            $this->db->prefix . 'ra_assessments',
            array(
                'user_id' => get_current_user_id(),
                'passage_id' => $data['passage_id'],
                'recording_id' => $data['recording_id'],
                'score' => $data['score'],
                'completion_time' => $data['completion_time'],
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%f', '%d', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to save assessment results.', 'reading-assessment'));
        }
        return $this->db->insert_id;
    }

    /**
     * Get user's assessment history
     *
     * @param int $user_id User ID
     * @param int $limit Optional limit of results
     * @return array Array of assessment objects
     */
    public function get_user_assessments($user_id, $limit = 10) {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT a.*, p.title as passage_title
                FROM {$this->db->prefix}ra_assessments a
                JOIN {$this->db->prefix}ra_passages p ON a.passage_id = p.id
                WHERE a.user_id = %d
                ORDER BY a.created_at DESC
                LIMIT %d",
                $user_id,
                $limit
            )
        );
    }

    /**
     * Update passage
     *
     * @param int $passage_id Passage ID
     * @param array $data Updated passage data
     * @return bool|WP_Error True on success, error object on failure
     */
    public function update_passage($passage_id, $data) {
        // Debug: Log the content before processing
        error_log('Raw content: ' . $data['content']);

        $content = isset($data['content']) ? $data['content'] : '';
        // If it's plain text, convert newlines to proper HTML
        if (strpos($content, '<p>') === false) {
            $content = wpautop($content);
        }

        error_log('Processed content: ' . $content);

        $result = $this->db->update(
            $this->db->prefix . 'ra_passages',
            array(
                'title' => wp_kses_post($data['title']),
                'content' => $content,  // Don't process it further
                'time_limit' => $data['time_limit'],
                'difficulty_level' => isset($data['difficulty_level']) ? absint($data['difficulty_level']) : 1,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $passage_id),
            array('%s', '%s', '%d', '%d', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update passage.', 'reading-assessment'));
        }
        return true;
    }

    /**
     * Delete passage and related data
     *
     * @param int $passage_id Passage ID
     * @return bool True on success, false on failure
     */
    public function delete_passage($passage_id) {
        $this->db->query('START TRANSACTION');

        try {
            // Delete related records first
            $this->db->delete(
                $this->db->prefix . 'ra_questions',
                array('passage_id' => $passage_id),
                array('%d')
            );

            // Delete assessments that are related to recordings of this passage
            $assessment_query = $this->db->prepare(
                "DELETE a FROM {$this->db->prefix}ra_assessments a
                 INNER JOIN {$this->db->prefix}ra_recordings r ON a.recording_id = r.id
                 WHERE r.passage_id = %d",
                $passage_id
            );
            $this->db->query($assessment_query);

            // Delete recordings
            $this->db->delete(
                $this->db->prefix . 'ra_recordings',
                array('passage_id' => $passage_id),
                array('%d')
            );

            // Finally delete the passage
            $result = $this->db->delete(
                $this->db->prefix . 'ra_passages',
                array('id' => $passage_id),
                array('%d')
            );

            if ($result === false) {
                throw new Exception('Failed to delete passage');
            }

            $this->db->query('COMMIT');
            return true;
        } catch (Exception $e) {
            error_log('Delete passage error: ' . $e->getMessage());
            $this->db->query('ROLLBACK');
            return false;
        }
    }


    public function get_passage_recording_count($passage_id) {
        return (int)$this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->db->prefix}ra_recordings
             WHERE passage_id = %d",
            $passage_id
        ));
    }


    /**
     * Save an assessment for a recording
     *
     * @param array $data {
     *     Assessment data to save
     *
     *     @type int    $recording_id     ID of the recording being assessed
     *     @type float  $total_score      Raw score given by assessor (1-20)
     *     @type float  $normalized_score Normalized score for the assessment
     *     @type string $completed_at     MySQL datetime of assessment completion
     * }
     * @return int|WP_Error Returns the assessment ID on success, WP_Error on failure
     */
    public function save_assessment($data) {
        global $wpdb;

        // Verify recording exists
        $recording = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ra_recordings WHERE id = %d",
            $data['recording_id']
        ));

        if (!$recording) {
            return new WP_Error(
                'invalid_recording',
                __('Inspelningen kunde inte hittas', 'reading-assessment')
            );
        }

        // Insert assessment
        $result = $wpdb->insert(
            $wpdb->prefix . 'ra_assessments',
            $data,
            ['%d', '%f', '%f', '%s']
        );

        if ($result === false) {
            return new WP_Error(
                'db_error',
                __('Kunde inte spara bedömningen', 'reading-assessment')
            );
        }

        return $wpdb->insert_id;
    }

    /**
     * Get orphaned recordings that need passage assignment
     *
     * Retrieves recordings with missing or invalid passage IDs,
     * along with relevant metadata to help identify correct passage.
     *
     * @param int $limit Maximum number of records to process at once
     * @param int $offset Number of records to skip
     * @return array Array of recording objects with metadata
     */
    public function get_orphaned_recordings($limit = 50, $offset = 0) {
        return $this->db->get_results($this->db->prepare(
            "SELECT r.*, u.display_name, u.user_email
            FROM {$this->db->prefix}ra_recordings r
            JOIN {$this->db->users} u ON r.user_id = u.ID
            WHERE r.passage_id = 0 OR r.passage_id IS NULL
            ORDER BY r.created_at DESC
            LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }

    /**
     * Get total count of orphaned recordings
     *
     * Used for pagination and progress tracking in the repair tool.
     *
     * @return int Total number of recordings needing repair
     */
    public function get_total_orphaned_recordings() {
        return (int)$this->db->get_var(
            "SELECT COUNT(*)
            FROM {$this->db->prefix}ra_recordings
            WHERE passage_id = 0 OR passage_id IS NULL"
        );
    }

    /**
     * Update passage ID for a batch of recordings
     *
     * @param array $updates Array of recording_id => passage_id pairs
     * @return array Array containing success count and error messages
     */
    public function batch_update_recordings($updates) {
        $results = array(
            'success' => 0,
            'errors' => array()
        );

        foreach ($updates as $recording_id => $passage_id) {
            $result = $this->db->update(
                $this->db->prefix . 'ra_recordings',
                array('passage_id' => $passage_id),
                array('id' => $recording_id),
                array('%d'),
                array('%d')
            );

            if ($result !== false) {
                $results['success']++;
            } else {
                $results['errors'][] = sprintf(
                    'Failed to update recording %d: %s',
                    $recording_id,
                    $this->db->last_error
                );
            }
        }

        return $results;
    }

    /**
     * Trying to gather all stats methods here
    */
/**
    * Get dashboard statistics
    *
    * @return object Statistics with total recordings, unique users, assessments, etc.
    */
    public function get_dashboard_statistics() {
        error_log('Running dashboard statistics query');

        $query = "SELECT
            COUNT(DISTINCT r.id) as total_recordings,
            COUNT(DISTINCT r.user_id) as unique_users,
            COUNT(DISTINCT a.id) as total_assessments,
            AVG(a.normalized_score) as avg_score,
            SUM(r.duration) as total_duration
        FROM {$this->db->prefix}ra_recordings r
        LEFT JOIN {$this->db->prefix}ra_assessments a ON r.id = a.recording_id";

        error_log('Statistics query: ' . $query);
        $stats = $this->db->get_row($query);
        error_log('Statistics result: ' . print_r($stats, true));

        // Return a default object if no results
        if (!$stats) {
            return (object)[
                'total_recordings' => 0,
                'unique_users' => 0,
                'total_assessments' => 0,
                'avg_score' => 0,
                'total_duration' => 0
            ];
        }

        return $stats;
    }

    /**
        * Get recording statistics
        *
        * @param array $filters Optional filters (user_id, passage_id, date_range)
        * @return array Statistics about recordings
        */
    public function get_recording_statistics($filters = array()) {
        $where = array('1=1');
        $where_args = array();

        if (isset($filters['user_id'])) {
            $where[] = 'r.user_id = %d';
            $where_args[] = $filters['user_id'];
        }
        if (isset($filters['passage_id'])) {
            $where[] = 'r.passage_id = %d';
            $where_args[] = $filters['passage_id'];
        }
        if (isset($filters['date_range'])) {
            $where[] = 'r.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
            $where_args[] = $filters['date_range'];
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where);

        return $this->db->get_row(
            $this->db->prepare(
                "SELECT
                    COUNT(*) as total_recordings,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT passage_id) as unique_passages,
                    AVG(duration) as avg_duration,
                    MAX(created_at) as latest_recording
                FROM {$this->db->prefix}ra_recordings r
                $where_clause",
                $where_args
            ),
            ARRAY_A
        );
    }

    /**
         * Get assessment statistics for a passage
         *
         * @param int $passage_id Passage ID
         * @return array Statistics including average score, completion times, etc.
         */
        public function get_passage_statistics($passage_id) {
            $stats = $this->db->get_row(
                $this->db->prepare(
                    "SELECT
                        COUNT(*) as total_attempts,
                        AVG(normalized_score) as average_score,
                        AVG(total_score) as total_score,
                        COUNT(DISTINCT r.id) as recording_count
                    FROM {$this->db->prefix}ra_assessments a
                    RIGHT JOIN {$this->db->prefix}ra_recordings r ON a.recording_id = r.id
                    WHERE r.passage_id = %d",
                    $passage_id
                )
            );

            return $stats ? $stats : (object)[
                'total_attempts' => 0,
                'average_score' => 0,
                'total_score' => 0,
                'recording_count' => 0
            ];
        }

    /**
     * Get comprehensive passage statistics
     * Returns count of passages and their recordings
     *
     * @return array Array of passages with recording counts
     */
     public function get_passage_statistics_overview() {
        $query = "
            SELECT
                p.id,
                p.title,
                COUNT(r.id) as recording_count,
                AVG(a.normalized_score) as avg_grade,
                MIN(a.normalized_score) as min_grade,
                MAX(a.normalized_score) as max_grade
            FROM {$this->db->prefix}ra_passages p
            LEFT JOIN {$this->db->prefix}ra_recordings r ON p.id = r.passage_id
            LEFT JOIN {$this->db->prefix}ra_assessments a ON r.id = a.recording_id
            GROUP BY p.id, p.title
            ORDER BY p.title ASC";

        return $this->db->get_results($query);
    }

    /**
     * Get detailed recording statistics per passage
     *
     * @return array Array of passages with recording counts and assessment stats
     */
    public function get_recordings_per_passage() {
        $query = "
            SELECT
                p.id,
                p.title,
                COUNT(DISTINCT r.id) as recording_count,
                COUNT(DISTINCT r.user_id) as unique_users,
                AVG(a.normalized_score) as avg_grade,
                COUNT(DISTINCT a.id) as assessment_count
            FROM {$this->db->prefix}ra_passages p
            LEFT JOIN {$this->db->prefix}ra_recordings r ON p.id = r.passage_id
            LEFT JOIN {$this->db->prefix}ra_assessments a ON r.id = a.recording_id
            GROUP BY p.id, p.title
            ORDER BY recording_count DESC";

        return $this->db->get_results($query);
    }

    /**
     * Get user performance statistics
     *
     * @return array Array of users with their recording and assessment stats
     */
    public function get_user_performance_stats() {
        $query = "
            SELECT
                u.ID,
                u.display_name,
                COUNT(DISTINCT r.id) as recording_count,
                AVG(a.normalized_score) as avg_grade,
                MIN(a.normalized_score) as min_grade,
                MAX(a.normalized_score) as max_grade,
                COUNT(DISTINCT a.id) as times_assessed
            FROM {$this->db->users} u
            JOIN {$this->db->prefix}ra_recordings r ON u.ID = r.user_id
            LEFT JOIN {$this->db->prefix}ra_assessments a ON r.id = a.recording_id
            GROUP BY u.ID, u.display_name
            HAVING recording_count > 0
            ORDER BY avg_grade DESC";

        return $this->db->get_results($query);
    }

    /**
     * Get overall system statistics
     *
     * @return object Statistics object with overall metrics
     */
    public function get_overall_statistics() {
        $query = "
            SELECT
                (SELECT COUNT(*) FROM {$this->db->prefix}ra_passages) as total_passages,
                (SELECT COUNT(*) FROM {$this->db->prefix}ra_recordings) as total_recordings,
                (SELECT COUNT(DISTINCT user_id) FROM {$this->db->prefix}ra_recordings) as total_users,
                (SELECT AVG(normalized_score) FROM {$this->db->prefix}ra_assessments) as overall_avg_grade,
                (SELECT COUNT(*) FROM {$this->db->prefix}ra_assessments) as total_assessments";

        return $this->db->get_row($query);
    }

    /**
     * Get assessment distribution
     * Groups assessments by score ranges
     *
     * @return array Array of assessment counts by score range
     */
    public function get_assessment_distribution() {
        $query = "
            SELECT
                CASE
                    WHEN normalized_score BETWEEN 0 AND 5 THEN '0-5'
                    WHEN normalized_score BETWEEN 6 AND 10 THEN '6-10'
                    WHEN normalized_score BETWEEN 11 AND 15 THEN '11-15'
                    WHEN normalized_score BETWEEN 16 AND 20 THEN '16-20'
                END as score_range,
                COUNT(*) as count
            FROM {$this->db->prefix}ra_assessments
            GROUP BY score_range
            ORDER BY score_range";

        return $this->db->get_results($query);
    }


    /**
     * Get student progress over time
     *
     * @param int $user_id User ID to track
     * @param string $period 'week', 'month', or 'year'
     * @param int $limit How many periods back to look
     * @return array Progress data over time
     */
    public function get_student_progress_over_time($user_id, $period = 'month', $limit = 12) {
        $period_format = $period === 'week' ? '%Y-%u' :
                        ($period === 'month' ? '%Y-%m' : '%Y');

        $period_label = $period === 'week' ? 'CONCAT("Vecka ", WEEK(r.created_at))' :
                    ($period === 'month' ? 'DATE_FORMAT(r.created_at, "%M %Y")' : 'YEAR(r.created_at)');

        $query = $this->db->prepare(
            "SELECT
                {$period_label} as period_label,
                DATE_FORMAT(r.created_at, '{$period_format}') as period,
                COUNT(DISTINCT r.id) as recording_count,
                AVG(a.normalized_score) as avg_grade,
                MIN(a.normalized_score) as min_grade,
                MAX(a.normalized_score) as max_grade,
                COUNT(DISTINCT a.id) as assessments_count
            FROM {$this->db->prefix}ra_recordings r
            LEFT JOIN {$this->db->prefix}ra_assessments a ON r.id = a.recording_id
            WHERE r.user_id = %d
            GROUP BY period
            ORDER BY period DESC
            LIMIT %d",
            $user_id,
            $limit
        );

        return array_reverse($this->db->get_results($query)); // Reverse to show oldest first
    }

    /**
     * Get class-wide progress over time
     *
     * @param string $period 'week', 'month', or 'year'
     * @param int $limit How many periods back to look
     * @return array Progress data over time
     */
    public function get_class_progress_over_time($period = 'month', $limit = 12) {
        try {
            $period_format = $period === 'week' ? '%Y-%u' :
                            ($period === 'month' ? '%Y-%m' : '%Y');

            $period_label = $period === 'week' ? 'CONCAT("Vecka ", WEEK(r.created_at))' :
                           ($period === 'month' ? 'DATE_FORMAT(r.created_at, "%M %Y")' : 'YEAR(r.created_at)');

            $query = $this->db->prepare(
                "SELECT
                    {$period_label} as period_label,
                    DATE_FORMAT(r.created_at, %s) as period,
                    COUNT(DISTINCT r.id) as recording_count,
                    COUNT(DISTINCT r.user_id) as unique_users,
                    AVG(a.normalized_score) as avg_grade,
                    MIN(a.normalized_score) as min_grade,
                    MAX(a.normalized_score) as max_grade
                FROM {$this->db->prefix}ra_recordings r
                LEFT JOIN {$this->db->prefix}ra_assessments a ON r.id = a.recording_id
                GROUP BY period
                ORDER BY period DESC
                LIMIT %d",
                $period_format,
                $limit
            );

            //error_log('Progress query: ' . $query);
            $results = $this->db->get_results($query);
            //error_log('Progress results: ' . print_r($results, true));

            return array_reverse($results); // Reverse to show oldest first

        } catch (Exception $e) {
            error_log('Database error in get_class_progress_over_time: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all users who have made recordings
     *
     * @return array Array of user objects with recording counts
     */
    public function get_users_with_recordings() {
        $query = "
            SELECT DISTINCT
                u.ID,
                u.display_name,
                COUNT(DISTINCT r.id) as recording_count
            FROM {$this->db->prefix}users u
            JOIN {$this->db->prefix}ra_recordings r ON u.ID = r.user_id
            GROUP BY u.ID, u.display_name
            ORDER BY u.display_name ASC";

        return $this->db->get_results($query);
    }

    /**
     * Summary of update_ai_evaluation_meta
     * @param mixed $evaluation_id
     * @param mixed $meta_data
     */
    public function update_ai_evaluation_meta($evaluation_id, $meta_data) {
        // Add to existing evaluation_data JSON
        global $wpdb;
        $current = $this->get_ai_evaluation($evaluation_id);
        $updated_data = json_decode($current->evaluation_data, true);
        $updated_data['meta'] = $meta_data;

        return $wpdb->update(
            $wpdb->prefix . 'ra_ai_evaluations',
            ['evaluation_data' => json_encode($updated_data)],
            ['id' => $evaluation_id],
            ['%s'],
            ['%d']
        );
    }

    public function get_ai_evaluation($evaluation_id) {
        error_log('Getting AI evaluation: ' . $evaluation_id);
        $result = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}ra_ai_evaluations WHERE id = %d",
            $evaluation_id
        ));
        error_log('Evaluation found: ' . ($result ? 'yes' : 'no'));
        return $result;
    }


    public function get_ai_evaluations($per_page = 20, $current_page = 1) {
        error_log('Getting AI evaluations list');
        $offset = ($current_page - 1) * $per_page;

        // Add r.transcription to SELECT
        $query = $this->db->prepare(
            "SELECT e.*, r.user_id, r.transcription, p.title as passage_title
             FROM {$this->db->prefix}ra_ai_evaluations e
             JOIN {$this->db->prefix}ra_recordings r ON e.recording_id = r.id
             JOIN {$this->db->prefix}ra_passages p ON r.passage_id = p.id
             ORDER BY e.created_at DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );

        error_log('Running query: ' . $query);
        $results = $this->db->get_results($query);
        error_log('Found evaluations: ' . count($results));

        return $results;
    }

    public function get_ai_evaluations_count() {
        return (int)$this->db->get_var(
            "SELECT COUNT(*) FROM {$this->db->prefix}ra_ai_evaluations"
        );
    }
}

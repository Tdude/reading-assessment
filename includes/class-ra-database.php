<?php
/** class-ra-database.php
 * Handles database operations for the plugin.
 *
 * @package ReadingAssessment
 * @subpackage ReadingAssessment/includes
 */
class Reading_Assessment_Database {
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

    /**
     * Get a single question by ID
     *
     * @param int $question_id Question ID
     * @return object|null Question object or null if not found
     */
    public function get_question($question_id) {
        return $this->db->get_row(
            $this->db->prepare(
                "SELECT q.*, p.title as passage_title
                FROM {$this->db->prefix}ra_questions q
                JOIN {$this->db->prefix}ra_passages p ON q.passage_id = p.id
                WHERE q.id = %d",
                $question_id
            )
        );
    }

    /**
     * Get question statistics
     *
     * @param int $question_id Question ID
     * @return array Statistics including correct/incorrect counts, average score
     */
    public function get_question_statistics($question_id) {
        return $this->db->get_row(
            $this->db->prepare(
                "SELECT
                    COUNT(*) as total_responses,
                    SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_responses,
                    AVG(score) as average_score
                FROM {$this->db->prefix}ra_responses
                WHERE question_id = %d",
                $question_id
            )
        );
    }

    /**
     * Get recording by ID
     *
     * @param int $recording_id Recording ID
     * @return object|null Recording object or null if not found
     */
    public function get_recording($recording_id) {
        error_log('Getting recording: ' . $recording_id);

        $recording = $this->db->get_row(
            $this->db->prepare(
                "SELECT r.*, p.title as passage_title, u.display_name as user_name
                FROM {$this->db->prefix}ra_recordings r
                LEFT JOIN {$this->db->prefix}ra_passages p ON r.passage_id = p.id
                LEFT JOIN {$this->db->users} u ON r.user_id = u.ID
                WHERE r.id = %d",
                $recording_id
            )
        );

        error_log('Get recording query result: ' . ($recording ? 'found' : 'not found'));

        if ($recording) {
            $recording->full_audio_path = $this->get_upload_path() . $recording->audio_file_path;
            error_log('Full audio path: ' . $recording->full_audio_path);
        }

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
            $this->db->prefix . 'ra_recordings',  // Removed extra prefix
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
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->db->prefix}ra_questions WHERE passage_id = %d ORDER BY id ASC",
                $passage_id
            )
        );
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

    /**
     * Remove assignment
     */
    public function remove_assignment($assignment_id) {
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
        $result = $this->db->update(
            $this->db->prefix . 'ra_passages',
            array(
                'title' => $data['title'],
                'content' => $data['content'],
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

            $this->db->delete(
                $this->db->prefix . 'ra_assessments',
                array('passage_id' => $passage_id),
                array('%d')
            );

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
            $this->db->query('ROLLBACK');
            return false;
        }
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
                    COUNT(DISTINCT recording_id) as total_recordings
                FROM {$this->db->prefix}ra_assessments
                WHERE recording_id IN (
                    SELECT id FROM {$this->db->prefix}ra_recordings
                    WHERE passage_id = %d
                )",
                $passage_id
            )
        );

        return $stats ? $stats : (object)[
            'total_attempts' => 0,
            'average_score' => 0,
            'total_score' => 0,
            'total_recordings' => 0
        ];
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
}
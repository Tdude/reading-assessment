<?php
/**
 * File: admin/partials/ra-admin-statistics.php
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Enhanced Statistics handling class
 */
class RA_Statistics {
    private $db;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }

    /**
     * Get overall statistics including assessment metrics
     */
    public function get_filtered_statistics($date_limit = '', $passage_id = 0) {
        $where = array('1=1');
        $where_args = array();

        if ($date_limit) {
            $where[] = 'r.created_at >= %s';
            $where_args[] = $date_limit;
        }

        if ($passage_id) {
            $where[] = 'r.passage_id = %d';
            $where_args[] = $passage_id;
        }

        $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = "SELECT
            COUNT(DISTINCT r.id) as total_recordings,
            (SELECT COUNT(DISTINCT user_id) FROM {$this->db->prefix}ra_recordings WHERE user_id > 0) as unique_students,
            AVG(a.normalized_score) as avg_normalized_score,
            COUNT(resp.id) as total_questions_answered,
            (SUM(CASE WHEN resp.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(resp.id), 0)) as correct_answer_rate
        FROM {$this->db->prefix}ra_recordings r
        LEFT JOIN {$this->db->prefix}ra_assessments a ON r.id = a.recording_id
        LEFT JOIN {$this->db->prefix}ra_responses resp ON r.id = resp.recording_id
        $where_clause";

        error_log('Statistics query: ' . $query);

        return $this->db->get_row(
            $this->db->prepare($query, $where_args),
            ARRAY_A
        );
    }

    /**
     * Get enhanced passage statistics
     */
    public function get_passage_statistics($date_limit = '') {
        $where = $date_limit ? 'WHERE r.created_at >= %s' : '';

        return $this->db->get_results(
            $this->db->prepare(
                "SELECT
                    p.title,
                    COUNT(DISTINCT r.id) as recording_count,
                    AVG(a.normalized_score) as avg_score,
                    AVG(r.duration) as avg_duration,
                    (SUM(CASE WHEN resp.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(resp.id)) as correct_answer_rate
                FROM {$this->db->prefix}ra_passages p
                LEFT JOIN {$this->db->prefix}ra_recordings r ON p.id = r.passage_id
                LEFT JOIN {$this->db->prefix}ra_assessments a ON r.id = a.recording_id
                LEFT JOIN {$this->db->prefix}ra_responses resp ON r.id = resp.recording_id
                $where
                GROUP BY p.id
                ORDER BY recording_count DESC",
                $date_limit ? array($date_limit) : array()
            ),
            ARRAY_A
        );
    }

    /**
     * Get question-level statistics
     */
    public function get_question_statistics($date_limit = '', $passage_id = 0) {
        $where = array('1=1');
        $where_args = array();

        if ($date_limit) {
            $where[] = 'r.created_at >= %s';
            $where_args[] = $date_limit;
        }

        if ($passage_id) {
            $where[] = 'r.passage_id = %d';
            $where_args[] = $passage_id;
        }

        $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = $this->db->prepare(
            "SELECT
                q.question_text,
                q.correct_answer,
                p.title as passage_title,
                COUNT(resp.id) as times_answered,
                SUM(CASE WHEN resp.is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
                COUNT(resp.id) as total_count,
                (SUM(CASE WHEN resp.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(resp.id)) as correct_rate,
                AVG(resp.score) as avg_similarity
            FROM {$this->db->prefix}ra_questions q
            JOIN {$this->db->prefix}ra_passages p ON q.passage_id = p.id
            JOIN {$this->db->prefix}ra_responses resp ON q.id = resp.question_id
            JOIN {$this->db->prefix}ra_recordings r ON resp.recording_id = r.id
            $where_clause
            GROUP BY q.id
            ORDER BY correct_rate DESC",
            $where_args
        );

        error_log('Question Statistics Query: ' . $query);

        $results = $this->db->get_results($query, ARRAY_A);

        error_log('Question Statistics Results: ' . print_r($results, true));

        return $results;
    }

    /**
     * Get all passages for filter dropdown
     */
    public function get_all_passages() {
        return $this->db->get_results(
            "SELECT id, title
             FROM {$this->db->prefix}ra_passages
             ORDER BY title ASC"
        );
    }
}
<?php
// admin/partials/ra-admin-dashboard.php
if (!defined('WPINC')) {
    die;
}

class Reading_Assessment_Dashboard_Admin {
    private $db;
    public $messages = array();
    private $plugin_name;
    private $version;

    public function __construct($db, $plugin_name, $version) {
        $this->db = $db;
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_ajax_ra_admin_delete_recording', array($this, 'ajax_delete_recording'));
    }

    public function render_page() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Get passage filter
        $passage_filter = isset($_GET['passage_filter']) ? intval($_GET['passage_filter']) : 0;
        $passage_title = '';
        if ($passage_filter) {
            $passage = $this->db->get_passage($passage_filter);
            if ($passage) {
                $passage_title = $passage->title;
            }
        }

        // Get upload directory info
        $upload_dir = wp_upload_dir();

        // Get pagination variables
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Get recordings
        $recent_recordings = $this->db->get_recent_recordings($per_page, $offset, $passage_filter);
        $total_count = $this->db->get_recordings_count($passage_filter);
        $total_pages = ceil($total_count / $per_page);

        // @TODO: Get statistics in class
        error_log('Getting dashboard statistics');
        $stats = $this->db->get_dashboard_statistics();
        error_log('Stats result: ' . print_r($stats, true));

        // Make variables available to the view
        $variables = [
            'passage_filter' => $passage_filter,
            'passage_title' => $passage_title,
            'upload_dir' => $upload_dir,
            'recent_recordings' => $recent_recordings,
            'total_count' => $total_count,
            'total_pages' => $total_pages,
            'current_page' => $current_page,
            'stats' => $stats  // Make sure stats is included
        ];

        $dashboard_data = $this->get_dashboard_data();
        $variables['dashboard_data'] = $dashboard_data;

        extract($variables);
        // Make variables available to the view
        require plugin_dir_path(__FILE__) . 'views/dashboard-admin-page.php';
    }

    public function get_dashboard_data() {
        $progress_data = $this->db->get_class_progress_over_time('month', 12);
        error_log('Progress data: ' . print_r($progress_data, true));

        return array(
            'passage_stats' => $this->db->get_passage_statistics_overview(),
            'recordings_per_passage' => $this->db->get_recordings_per_passage(),
            'user_stats' => $this->db->get_user_performance_stats(),
            'overall_stats' => $this->db->get_overall_statistics(),
            'assessment_distribution' => $this->db->get_assessment_distribution(),
            'class_progress' => $this->db->get_class_progress_over_time('month', 12)
        );
    }

    public function ajax_delete_recording() {
        check_ajax_referer('ra_admin_action', 'nonce');

        $recording_id = isset($_POST['recording_id']) ? intval($_POST['recording_id']) : 0;
        $result = $this->db->delete_recording($recording_id);

        if (!$result) {
            wp_send_json_error(['message' => __('Kunde inte radera inspelningen', 'reading-assessment')]);
        }

        wp_send_json_success(['message' => __('Inspelning raderad', 'reading-assessment')]);
    }
}
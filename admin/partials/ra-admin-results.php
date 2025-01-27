<?php
// admin/partials/ra-admin-results.php
if (!defined('WPINC')) {
    die;
}

error_log('Starting ra-admin-results.php');

if (!class_exists('Reading_Assessment_Results_Admin')) {
    error_log('Defining Reading_Assessment_Results_Admin class');

    /**
     * Results class
     */
    class Reading_Assessment_Results_Admin {
        private $db;
        private $plugin_name;
        private $version;
        private $stats;

        public function __construct($db, $plugin_name, $version) {
            error_log('Constructing Reading_Assessment_Results_Admin');

            if (!RA_Error_Handler::check_class_exists('RA_Statistics')) {
                error_log('Loading RA_Statistics, /ra-admin-statistics.php');
                require_once dirname(__FILE__) . '/ra-admin-statistics.php';
            }

            $this->db = $db;
            $this->plugin_name = $plugin_name;
            $this->version = $version;
            $this->stats = new RA_Statistics();
        }

        public function render_page() {
            error_log('Starting render_page in Reading_Assessment_Results_Admin');

            $passage_id = isset($_GET['passage_id']) ? intval($_GET['passage_id']) : 0;
            $date_range = isset($_GET['date_range']) ? intval($_GET['date_range']) : 30;
            $date_limit = $date_range > 0 ? date('Y-m-d', strtotime("-$date_range days")) : '';
            $overall_stats = $this->stats->get_overall_statistics($date_limit, $passage_id);
            $passage_stats = $this->stats->get_passage_statistics($date_limit);
            $question_stats = $this->stats->get_question_statistics($date_limit, $passage_id);
            $stats = $this->stats; // Make stats available to view

            require plugin_dir_path(__FILE__) . 'views/results-admin-page.php';
        }
    }
} else {
    error_log('Reading_Assessment_Results_Admin class already exists');
}
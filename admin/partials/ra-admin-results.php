<?php
/**
 * Results admin page
 *
 * @package    ReadingAssessment
 * @subpackage ReadingAssessment/admin/partials/ra-admin-results.php
 */

if (!defined('WPINC')) {
    die;
}

error_log('Starting ra-admin-results.php');

// Exit if the class is already defined
if (class_exists('RA_Results_Admin')) {
    error_log('RA_Results_Admin class already exists - skipping definition');
    return;
}

class RA_Results_Admin {
    private $db;
    private $plugin_name;
    private $version;
    private $stats;

    public function __construct($db, $plugin_name, $version) {
        error_log('Constructing RA_Results_Admin');

        if (!class_exists('RA_Statistics')) {
            error_log('Loading RA_Statistics');
            require_once plugin_dir_path(__FILE__) . 'ra-admin-statistics.php';
        }

        $this->db = $db;
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->stats = new RA_Statistics();
    }

    public function render_page() {
        error_log('Starting render_page in RA_Results_Admin');

        // Get filter values
        $passage_id = isset($_GET['passage_id']) ? intval($_GET['passage_id']) : 0;
        $date_range = isset($_GET['date_range']) ? intval($_GET['date_range']) : 30;

        // Calculate date range
        $date_limit = $date_range > 0 ? date('Y-m-d', strtotime("-$date_range days")) : '';

        // Get statistics
        $overall_stats = $this->stats->get_filtered_statistics($date_limit, $passage_id);
        $passage_stats = $this->stats->get_passage_statistics($date_limit);
        $question_stats = $this->stats->get_question_statistics($date_limit, $passage_id);

        // Make stats available to view
        $stats = $this->stats;

        // Include the view file
        $view_path = dirname(__FILE__) . '/views/results-admin-page.php';
        error_log('Loading view file from: ' . $view_path);

        if (file_exists($view_path)) {
            require $view_path;
        } else {
            error_log('View file not found at: ' . $view_path);
            echo '<div class="wrap"><p>Error: Results view file not found.</p></div>';
        }
    }
}

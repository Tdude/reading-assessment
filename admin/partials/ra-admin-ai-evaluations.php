<?php
/**
 * Admin view for AI evaluations
 * File: admin/partials/ra-admin-ai-evaluations.php
 * @package ReadingAssessment
 * @subpackage ReadingAssessment/admin/partials
 */
if (!defined('WPINC')) {
    die;
}

class Reading_Assessment_AI_Evaluations_Admin {
    private $db;
    private $plugin_name;
    private $version;

    public function __construct($db, $plugin_name, $version) {
        $this->db = $db;
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function render_page() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Get evaluations data
        $evaluations = $this->db->get_ai_evaluations(20, 1);

        // Make variables available to the view
        $variables = [
            'evaluations' => $evaluations,
            'current_tab' => isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'list'
        ];

        extract($variables);

        // Include the view template
        require plugin_dir_path(__FILE__) . 'views/ai-evaluations-admin-page.php';
    }

    private function get_tab_url($tab) {
        return add_query_arg([
            'page' => 'reading-assessment-ai-evaluations',
            'tab' => $tab
        ], admin_url('admin.php'));
    }

    public function get_evaluation_details($evaluation_id) {
        error_log('=== Getting evaluation details for ID: ' . $evaluation_id . ' ===');

        $evaluation = $this->db->get_ai_evaluation($evaluation_id);
        if (!$evaluation) {
            error_log('No evaluation found');
            return null;
        }

        error_log('Raw evaluation data: ' . print_r($evaluation, true));
        error_log('Evaluation data JSON: ' . $evaluation->evaluation_data);

        $recording = $this->db->get_recording($evaluation->recording_id);
        $passage = $recording ? $this->db->get_passage($recording->passage_id) : null;

        $details = [
            'evaluation' => $evaluation,
            'recording' => $recording,
            'passage' => $passage
        ];

        error_log('Full details: ' . print_r($details, true));
        return $details;
    }
}
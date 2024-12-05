<?php
/** class-ra-admin.php
 * Admin-specific functionality of the plugin.
 *
 * @package    ReadingAssessment
 * @subpackage ReadingAssessment/admin
 */

class Reading_Assessment_Admin {

    private $plugin_name;
    private $version;
    private $db;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->db = new Reading_Assessment_Database();
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            RA_PLUGIN_URL . 'admin/css/ra-admin.css',
            [],
            $this->version,
            'all'
        );
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            RA_PLUGIN_URL . 'admin/js/ra-admin.js',
            ['jquery'],
            $this->version,
            true
        );
        // Localize the script with translation strings and ajaxurl
        wp_localize_script(
            $this->plugin_name,
            'raStrings',
            array(
                'editText' => __('Ändra text', 'reading-assessment'),
                'errorLoading' => __('Fel vid inläsning av text från databasen', 'reading-assessment'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ra_passage_action')
            )
        );
    }

    public function add_menu_pages() {
        add_menu_page(
            __('Läsuppskattning', 'reading-assessment'),
            __('Läsuppskattning', 'reading-assessment'),
            'manage_options',
            'reading-assessment',
            [$this, 'render_dashboard_page'],
            'dashicons-welcome-learn-more',
            6
        );

        add_submenu_page(
            'reading-assessment',
            __('Texter', 'reading-assessment'),
            __('Texter', 'reading-assessment'),
            'manage_options',
            'reading-assessment-passages',
            [$this, 'render_passages_page']
        );

        add_submenu_page(
            'reading-assessment',
            __('Frågor', 'reading-assessment'),
            __('Frågor', 'reading-assessment'),
            'manage_options',
            'reading-assessment-questions',
            [$this, 'render_questions_page']
        );

        add_submenu_page(
            'reading-assessment',
            __('Tilldelningar av text', 'reading-assessment'),
            __('Tilldelningar', 'reading-assessment'),
            'manage_options',
            'reading-assessment-assignments',
            [$this, 'render_assignments_page']
        );

        add_submenu_page(
            'reading-assessment',
            __('Resultat', 'reading-assessment'),
            __('Resultat', 'reading-assessment'),
            'manage_options',
            'reading-assessment-results',
            [$this, 'render_results_page']
        );
    }

    public function render_dashboard_page() {
        include RA_PLUGIN_DIR . 'admin/partials/ra-admin-dashboard.php';
    }

    public function render_passages_page() {
        include RA_PLUGIN_DIR . 'admin/partials/ra-admin-passages.php';
    }

    public function render_questions_page() {
        include RA_PLUGIN_DIR . 'admin/partials/ra-admin-questions.php';
    }

    public function render_results_page() {
        include RA_PLUGIN_DIR . 'admin/partials/ra-admin-results.php';
    }

    public function render_assignments_page() {
        include RA_PLUGIN_DIR . 'admin/partials/ra-admin-assignments.php';
    }

    public function ajax_get_passage() {
        error_log('ajax_get_passage called');
        if (!current_user_can('manage_options')) {
            error_log('Permission denied');
            wp_send_json_error(['message' => __('Permission denied', 'reading-assessment')]);
        }
    
        if (!isset($_POST['passage_id']) || !wp_verify_nonce($_POST['nonce'], 'ra_passage_action')) {
            error_log('Invalid request or nonce');
            wp_send_json_error(['message' => __('Invalid request', 'reading-assessment')]);
        }
    
        $passage_id = intval($_POST['passage_id']);
        error_log('Getting passage: ' . $passage_id);
        $result = $this->db->get_passage($passage_id);
    
        if (!$result) {
            error_log('Passage not found');
            wp_send_json_error(['message' => __('Passage not found', 'reading-assessment')]);
        }
    
        error_log('Returning passage data: ' . print_r($result, true));
        wp_send_json_success($result);
    }

    public function ajax_get_passages() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'reading-assessment')]);
        }
    
        $passages = $this->db->get_all_passages();
        
        if (!$passages) {
            wp_send_json_error(['message' => __('No text passages found', 'reading-assessment')]);
        }
    
        wp_send_json_success($passages);
    }

    public function ajax_delete_passage() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'reading-assessment')]);
        }
    
        if (!isset($_POST['passage_id']) || !wp_verify_nonce($_POST['nonce'], 'ra_passage_action')) {
            wp_send_json_error(['message' => __('Invalid request', 'reading-assessment')]);
        }
    
        $passage_id = intval($_POST['passage_id']);
        $result = $this->db->delete_passage($passage_id);
    
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
    
        wp_send_json_success(['message' => __('Texten har raderats', 'reading-assessment')]);
    }

    public function ajax_get_questions() {
        // Implementation for retrieving questions via AJAX.
        wp_send_json_success(['message' => 'Questions retrieved']);
    }

    public function ajax_get_results() {
        // Implementation for retrieving results via AJAX.
        wp_send_json_success(['message' => 'Results retrieved']);
    }

    public function ajax_delete_question() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'reading-assessment')]);
        }

        if (!isset($_POST['question_id']) || !wp_verify_nonce($_POST['nonce'], 'ra_question_action')) {
            wp_send_json_error(['message' => __('Invalid request.', 'reading-assessment')]);
        }

        $question_id = intval($_POST['question_id']);
        $result = $this->db->delete_question($question_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Question deleted successfully.', 'reading-assessment')]);
    }

    public function ajax_delete_assignment() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'reading-assessment')]);
        }
    
        if (!isset($_POST['assignment_id']) || !wp_verify_nonce($_POST['nonce'], 'ra_assignment_action')) {
            wp_send_json_error(['message' => __('Invalid request', 'reading-assessment')]);
        }
    
        $assignment_id = intval($_POST['assignment_id']);
        $result = $this->db->remove_assignment($assignment_id);
    
        if ($result === false) {
            wp_send_json_error(['message' => __('Kunde inte ta bort tilldelningen', 'reading-assessment')]);
        }
    
        wp_send_json_success(['message' => __('Tilldelning borttagen', 'reading-assessment')]);
    }

}

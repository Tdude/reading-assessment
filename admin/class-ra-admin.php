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

        wp_localize_script(
            $this->plugin_name,
            'raStrings',
            array(
                'editText' => __('Ändra text', 'reading-assessment'),
                'errorLoading' => __('Fel vid inläsning av text från databasen', 'reading-assessment'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ra_admin_action'),  // Single nonce for all admin actions
                'confirmDelete' => __('Är du säker på att du vill radera denna inspelning?', 'reading-assessment')
            )
        );
    }

    public function add_menu_pages() {
        add_menu_page(
            __('Läsuppskattning', 'reading-assessment'),// rubrik
            __('LäsUppSkattning', 'reading-assessment'),// menytitel
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
        add_submenu_page(
            'reading-assessment',
            __('Hantera inspelningar', 'reading-assessment'),
            __('Inspelningar', 'reading-assessment'),
            'manage_options',
            'reading-assessment-recordings',
            [$this, 'render_recordings_page']
        );

        // Only add repair tool if there are orphaned recordings
        $ra_db = new Reading_Assessment_Database();
        if ($ra_db->get_total_orphaned_recordings() > 0) {
            add_submenu_page(
                'reading-assessment',
                __('Reparera inspelningar', 'reading-assessment'),
                __('Reparera', 'reading-assessment'),
                'manage_options',
                'reading-assessment-repair',
                [$this, 'render_repair_page']
            );
        }
    }

    public function render_repair_page() {
        include RA_PLUGIN_DIR . 'admin/partials/ra-admin-repair.php';
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

    public function render_recordings_page() {
        include RA_PLUGIN_DIR . 'admin/partials/ra-admin-recordings.php';
    }

    public function ajax_get_passage() {
        error_log('ajax_get_passage called');
        if (!current_user_can('manage_options')) {
            error_log('Permission denied');
            wp_send_json_error(['message' => __('Permission denied', 'reading-assessment')]);
        }

        if (!isset($_POST['passage_id']) || !wp_verify_nonce($_POST['nonce'], 'ra_admin_action')) {
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

        if (!isset($_POST['passage_id']) || !wp_verify_nonce($_POST['nonce'], 'ra_admin_action')) {
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

        if (!isset($_POST['question_id']) || !wp_verify_nonce($_POST['nonce'], 'ra_admin_action')) {
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

        if (!isset($_POST['assignment_id']) || !wp_verify_nonce($_POST['nonce'], 'ra_admin_action')) {
            wp_send_json_error(['message' => __('Invalid request', 'reading-assessment')]);
        }

        $assignment_id = intval($_POST['assignment_id']);
        $result = $this->db->remove_assignment($assignment_id);

        if ($result === false) {
            wp_send_json_error(['message' => __('Kunde inte ta bort tilldelningen', 'reading-assessment')]);
        }

        wp_send_json_success(['message' => __('Tilldelning borttagen', 'reading-assessment')]);
    }

    public function ajax_save_assessment() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error([
                'message' => __('Behörighet saknas', 'reading-assessment')
            ]);
        }
        if (!check_ajax_referer('ra_admin_action', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'reading-assessment')]);
        }


        $recording_id = intval($_POST['recording_id']);
        $score = floatval($_POST['score']);

        if ($score < 1 || $score > 20) {
            wp_send_json_error([
                'message' => __('Ogiltig poäng. Måste vara mellan 1 och 20.', 'reading-assessment')
            ]);
        }

        // Insert using the database class
        $result = $this->db->save_assessment([
            'recording_id' => $recording_id,
            'total_score' => $score,
            'normalized_score' => $score, // For now, using same value
            'completed_at' => current_time('mysql')
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
        }

        wp_send_json_success([
            'message' => __('LUS-bedömningen sparad', 'reading-assessment'),
            'assessment_id' => $result
        ]);
    }


    public function ajax_delete_recording() {
        error_log('=== Start delete_recording AJAX handler ===');

        // Clear all previous output and buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            error_log('Not an AJAX request');
            exit('Not an AJAX request');
        }

        if (!check_ajax_referer('ra_admin_action', 'nonce', false)) {
            error_log('Nonce verification failed');
            wp_send_json_error(['message' => __('Security check failed', 'reading-assessment')]);
            exit;
        }

        if (!current_user_can('manage_options')) {
            error_log('Permission check failed');
            wp_send_json_error(['message' => __('Permission denied', 'reading-assessment')]);
            exit;
        }

        $recording_id = isset($_POST['recording_id']) ? intval($_POST['recording_id']) : 0;
        if (!$recording_id) {
            error_log('No recording ID provided');
            wp_send_json_error(['message' => __('Recording ID missing', 'reading-assessment')]);
            exit;
        }

        error_log('Processing recording ID: ' . $recording_id);

        try {
            $db = new Reading_Assessment_Database();
            $recording = $db->get_recording($recording_id);

            if (!$recording) {
                error_log('Recording not found');
                wp_send_json_error(['message' => __('Recording not found', 'reading-assessment')]);
                exit;
            }

            // Try to delete the file
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . $recording->audio_file_path;
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

            // Delete database record
            $result = $db->delete_recording($recording_id);

            if ($result) {
                error_log('Recording deleted successfully');
                wp_cache_delete('ra_recordings_count');
                wp_cache_delete('ra_recordings_page_1');
                wp_send_json_success(['message' => __('Inspelningen har raderats', 'reading-assessment')]);
            } else {
                error_log('Failed to delete recording from database');
                wp_send_json_error(['message' => __('Kunde inte radera inspelningen', 'reading-assessment')]);
            }
        } catch (Exception $e) {
            error_log('Exception in delete_recording: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }

        error_log('=== End delete_recording AJAX handler ===');
        exit;
    }


    public function ajax_save_interactions() {
        if (!check_ajax_referer('ra_admin_action', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'reading-assessment')]);
        }

        if (!current_user_can('manage_options') || !get_option('ra_enable_tracking', true)) {
            wp_send_json_error(['message' => 'Tracking disabled or permission denied']);
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ra_admin_interactions';

        $data = array(
            'user_id' => get_current_user_id(),
            'clicks' => isset($_POST['clicks']) ? intval($_POST['clicks']) : 0,
            'active_time' => isset($_POST['active_time']) ? intval($_POST['active_time']) : 0,
            'idle_time' => isset($_POST['idle_time']) ? intval($_POST['idle_time']) : 0,
            'created_at' => current_time('mysql')
        );

        $result = $wpdb->insert($table_name, $data);

        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to save interactions']);
        }

        wp_send_json_success(['message' => 'Interactions saved']);
    }

    public function register_settings() {
        register_setting('reading-assessment', 'ra_enable_tracking', array(
            'type' => 'boolean',
            'default' => true
        ));

        add_settings_section(
            'ra_tracking_settings',
            __('Aktivitetsspårning', 'reading-assessment'),
            array($this, 'render_tracking_section'),
            'reading-assessment'
        );

        add_settings_field(
            'ra_enable_tracking',
            __('Aktivera aktivitetsspårning', 'reading-assessment'),
            array($this, 'render_tracking_field'),
            'reading-assessment',
            'ra_tracking_settings'
        );
    }

    public function render_tracking_section() {
        echo '<p>' . __('Inställningar för administratörs aktivitetsspårning.', 'reading-assessment') . '</p>';
    }

    public function render_tracking_field() {
        $enabled = get_option('ra_enable_tracking', true);
        echo '<input type="checkbox" name="ra_enable_tracking" value="1" ' . checked(1, $enabled, false) . '/>';
        echo '<p class="description">' . __('Spåra administratörers aktivitet i kontrollpanelen.', 'reading-assessment') . '</p>';
    }
}
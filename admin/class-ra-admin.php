<?php
/** admin/class-ra-admin.php
 * Admin-specific functionality of the plugin.
 *
 * @package    ReadingAssessment
 * @subpackage ReadingAssessment/admin
 */


require_once plugin_dir_path(__FILE__) . 'partials/ra-admin-dashboard.php';
require_once plugin_dir_path(__FILE__) . 'partials/ra-admin-assignments.php';
require_once plugin_dir_path(__FILE__) . 'partials/ra-admin-questions.php';

require_once plugin_dir_path(__FILE__) . 'partials/ra-admin-statistics.php';
require_once plugin_dir_path(__FILE__) . 'partials/ra-admin-results.php';
require_once plugin_dir_path(__DIR__) . 'includes/class-ra-utilities.php';



class Reading_Assessment_Admin {
    private static $initialized = false;
    private $nonce_key = 'ra_admin_action';
    private $plugin_name;
    private $version;
    private $db;
    private Reading_Assessment_Dashboard_Admin $dashboard_admin;
    private Reading_Assessment_Questions_Admin $questions_admin;
    private Reading_Assessment_Assignments_Admin $assignments_admin;
    private Reading_Assessment_Results_Admin $results_admin;

    public function __construct($plugin_name, $version) {
        if (self::$initialized) {
            error_log('Reading_Assessment_Admin already initialized');
            return;
        }

        self::$initialized = true;

        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->db = new Reading_Assessment_Database();

        error_log('Main Admin Constructor: Setting up assignments');

        $this->results_admin = new Reading_Assessment_Results_Admin(
            $this->db,
            $plugin_name,
            $version
        );

        $this->dashboard_admin = new Reading_Assessment_Dashboard_Admin(
            $this->db,
            $plugin_name,
            $version
        );

        $this->assignments_admin = new Reading_Assessment_Assignments_Admin(
            $this->db,
            $plugin_name,
            $version
        );

        $this->questions_admin = new Reading_Assessment_Questions_Admin(
            $this->db,
            $plugin_name,
            $version
        );

        $this->results_admin = new Reading_Assessment_Results_Admin(
            $this->db,
            $plugin_name,
            $version
        );

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

    public function enqueue_scripts($hook) {
        // List of plugin page hooks
        $plugin_pages = array(
            'toplevel_page_reading-assessment',
            'lasuppskattning_page_reading-assessment-passages',
            'lasuppskattning_page_reading-assessment-questions',
            'lasuppskattning_page_reading-assessment-assignments',
            'lasuppskattning_page_reading-assessment-results',
            'lasuppskattning_page_reading-assessment-recordings',
            'lasuppskattning_page_reading-assessment-settings',
            'lasuppskattning_page_reading-assessment-repair'
        );

        // Only return if we're NOT on a plugin page
        if (!in_array($hook, $plugin_pages)) {
            return;
        }

        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array('jquery'),
            '4.4.1',
            false
        );

        wp_enqueue_script(
            $this->plugin_name,
            RA_PLUGIN_URL . 'admin/js/ra-admin.js',
            array('jquery'),
            $this->version,
            true
        );

        // Get dashboard data
        $dashboard_data = $this->dashboard_admin->get_dashboard_data();

        wp_localize_script(
            $this->plugin_name,
            'raAdmin',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce($this->nonce_key),
                'strings' => array(
                    'editText' => __('Ändra text', 'reading-assessment'),
                    'errorLoading' => __('Fel vid inläsning av text från databasen', 'reading-assessment'),
                    'confirmDelete' => __('Är du säker på att du vill radera denna inspelning?', 'reading-assessment'),
                    'aiEvaluating' => __('AI utvärderar inspelningen...', 'reading-assessment'),
                    'aiError' => __('Kunde inte utföra AI-bedömning', 'reading-assessment')
                ),
                'progressData' => array_values($dashboard_data['class_progress']) // Ensure it's a numeric array
            )
        );

        // Add this debug output to the page
        add_action('admin_footer', function() use ($dashboard_data) {
            echo '<script>console.log("PHP data:", ' . json_encode($dashboard_data['class_progress']) . ');</script>';
        });
    }

    public function add_menu_pages() {
        add_menu_page(
            __('Läsuppskattning', 'reading-assessment'),
            __('LäsUppSkattning', 'reading-assessment'),
            'manage_options',
            'reading-assessment',
            array($this->dashboard_admin, 'render_page'),
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
            array($this->questions_admin, 'render_page')
        );

        add_submenu_page(
            'reading-assessment',
            __('Tilldelningar av text', 'reading-assessment'),
            __('Tilldelningar', 'reading-assessment'),
            'manage_options',
            'reading-assessment-assignments',
            array($this->assignments_admin, 'render_page')
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
        add_submenu_page(
            'reading-assessment',
            __('Settings', 'reading-assessment'),
            __('Settings', 'reading-assessment'),
            'manage_options',
            'reading-assessment-settings',
            [$this, 'render_settings_page']
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

    public function render_settings_page() {
        ?>
<div class="wrap">
    <h2><?php _e('Inställningar', 'reading-assessment'); ?></h2>
    <form method="post" action="options.php">
        <?php
            settings_fields('reading-assessment');
            do_settings_sections('reading-assessment');
            submit_button();
            ?>
    </form>
</div>
<?php
    }

    public function render_repair_page() {
        include RA_PLUGIN_DIR . 'admin/partials/ra-admin-repair.php';
    }

    public function render_passages_page() {
        include RA_PLUGIN_DIR . 'admin/partials/ra-admin-passages.php';
    }

    public function render_questions_page() {
        include RA_PLUGIN_DIR . 'admin/partials/ra-admin-questions.php';
    }

    //public function render_results_page() {
    //    include RA_PLUGIN_DIR . 'admin/partials/ra-admin-results.php';
    //}

    public function render_results_page() {
        error_log('Starting render_results_page in class-ra-admin.php');

        if (isset($this->results_admin)) {
            $this->results_admin->render_page();
        } else {
            error_log('Error: Results admin not properly initialized');
            echo '<div class="wrap"><p>Error: Results admin not properly initialized.</p></div>';
        }
    }

    public function render_statistics_page() {
        include RA_PLUGIN_DIR . 'admin/partials/ra-admin-statistics.php';
    }

    public function render_assignments_page() {
        include RA_PLUGIN_DIR . 'admin/partials/ra-admin-assignments.php';
    }

    public function render_recordings_page() {
        include RA_PLUGIN_DIR . 'admin/partials/ra-admin-recordings.php';
    }

    // Centralized nonce verification method
    private function verify_admin_nonce() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $this->nonce_key)) {
            wp_send_json_error(['message' => __('Security check failed', 'reading-assessment')]);
            exit;
        }
    }

    public function ajax_get_passage() {
        error_log('ajax_get_passage called');
        if (!current_user_can('manage_options')) {
            error_log('Permission denied');
            wp_send_json_error(['message' => __('Permission denied', 'reading-assessment')]);
        }

        $this->verify_admin_nonce();

        $passage_id = intval($_POST['passage_id']);
        $result = $this->db->get_passage($passage_id);

        if (!$result) {
            wp_send_json_error(['message' => __('Passage not found', 'reading-assessment')]);
        }

        wp_send_json_success($result);
    }


    public function ajax_delete_passage() {
        // Clear any previous output
        while (ob_get_level()) {
            ob_end_clean();
        }

        if (!current_user_can('manage_options')) {
            error_log('Permission denied');
            wp_send_json_error(['message' => __('Permission denied', 'reading-assessment')]);
            exit;
        }
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ra_admin_action')) {
            error_log('Nonce verification failed');
            wp_send_json_error(['message' => __('Invalid request', 'reading-assessment')]);
            exit;
        }

        $passage_id = intval($_POST['passage_id']);
        $result = $this->db->delete_passage($passage_id);

        if (is_wp_error($result)) {
            error_log('Delete error: ' . $result->get_error_message());
            wp_send_json_error(['message' => $result->get_error_message()]);
            exit;
        }

        wp_send_json_success(['message' => __('Texten har raderats', 'reading-assessment')]);
        exit;
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

        $this->verify_admin_nonce();

        $question_id = intval($_POST['question_id']);
        $result = $this->db->delete_question($question_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Question deleted successfully.', 'reading-assessment')]);
    }

    public function ajax_delete_assignment() {
        try {
            // Verify nonce using the same action
            if (!check_ajax_referer('ra_admin_action', 'nonce', false)) {
                wp_send_json_error(['message' => 'Security check failed']);
                return;
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permission denied']);
                return;
            }

            $assignment_id = isset($_POST['assignment_id']) ? absint($_POST['assignment_id']) : 0;
            if (!$assignment_id) {
                wp_send_json_error(['message' => 'Invalid assignment ID']);
                return;
            }

            $result = $this->db->delete_assignment($assignment_id);

            if ($result === false) {
                wp_send_json_error(['message' => 'Failed to delete assignment']);
                return;
            }

            wp_send_json_success(['message' => 'Assignment deleted successfully']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_save_assessment() {
        error_log('AJAX save_assessment called');

        if (!current_user_can('edit_posts')) {
            error_log('Permission check failed');
            wp_send_json_error([
                'message' => __('Behörighet saknas', 'reading-assessment')
            ]);
        }
        if (!check_ajax_referer('ra_admin_action', 'nonce', false)) {
            error_log('Nonce check failed');
            wp_send_json_error(['message' => __('Security check failed', 'reading-assessment')]);
        }

        $recording_id = intval($_POST['recording_id']);
        $score = floatval($_POST['score']);
        error_log('Processing assessment - Recording ID: ' . $recording_id . ', Score: ' . $score);

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
                wp_cache_delete('ra_recordings_count');
                wp_cache_delete('ra_recordings_page_1');
                wp_send_json_success(['message' => __('Inspelningen har raderats', 'reading-assessment')]);
            } else {
                wp_send_json_error(['message' => __('Kunde inte radera inspelningen', 'reading-assessment')]);
            }
        } catch (Exception $e) {
            error_log('Exception in delete_recording: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
        exit;
    }

    public function ajax_ai_evaluate() {
        if (!check_ajax_referer('ra_admin_action', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'reading-assessment')]);
            exit;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'reading-assessment')]);
            exit;
        }

        $recording_id = isset($_POST['recording_id']) ? intval($_POST['recording_id']) : 0;
        if (!$recording_id) {
            wp_send_json_error(['message' => __('Invalid recording ID', 'reading-assessment')]);
            exit;
        }

        try {
            // Create AI evaluator instance
            $ai_evaluator = new Reading_Assessment_AI_Evaluator();

            // Process the recording
            $result = $ai_evaluator->process_recording($recording_id);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
                exit;
            }

            // If we got a "scheduled_transcription" status, send appropriate response
            if (isset($result['status']) && $result['status'] === 'scheduled_transcription') {
                wp_send_json_success([
                    'status' => 'processing',
                    'message' => __('Transkribering schemalagd. Vänligen försök igen om några minuter.', 'reading-assessment')
                ]);
                exit;
            }

            // Return evaluation results
            wp_send_json_success([
                'lus_score' => $result['lus_score'],
                'confidence_score' => $result['confidence_score']
            ]);

        } catch (Exception $e) {
            error_log('AI Evaluation error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Ett fel uppstod vid AI-bedömningen', 'reading-assessment')]);
        }
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

    public function ajax_get_progress_data() {
        try {
            // Verify nonce
            check_ajax_referer('ra_admin_action', 'nonce');

            // Get parameters, using $_REQUEST to handle both GET and POST
            $period = isset($_REQUEST['period']) ? sanitize_text_field($_REQUEST['period']) : 'month';
            $user_id = isset($_REQUEST['user_id']) ? intval($_REQUEST['user_id']) : 0;

            // Get progress data
            if ($user_id) {
                $data = $this->db->get_student_progress_over_time($user_id, $period);
            } else {
                $data = $this->db->get_class_progress_over_time($period);
            }

            wp_send_json_success($data);

        } catch (Exception $e) {
            error_log('Error in ajax_get_progress_data: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Internal server error']);
        }
    }

    public function ajax_check_processing_status() {
        if (!check_ajax_referer('ra_admin_action', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'reading-assessment')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'reading-assessment')]);
        }

        $recording_id = isset($_POST['recording_id']) ? intval($_POST['recording_id']) : 0;

        // Get recording and its evaluation using your db class methods
        $recording = $this->db->get_recording($recording_id);

        if (!$recording) {
            wp_send_json_error(['message' => __('Recording not found', 'reading-assessment')]);
        }

        // Get AI evaluation if it exists
        $evaluation = $this->db->get_ai_evaluation($recording_id);

        $status = [
            'has_transcription' => !empty($recording->transcription),
            'has_evaluation' => !empty($evaluation),
            'next_cron' => wp_next_scheduled('ra_process_pending_recordings')
        ];

        if ($evaluation) {
            $status['lus_score'] = $evaluation->lus_score;
            $status['confidence_score'] = $evaluation->confidence_score;
        }

        wp_send_json_success($status);
    }

    public function ajax_trigger_processing() {
        if (!check_ajax_referer('ra_admin_action', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'reading-assessment')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'reading-assessment')]);
        }

        $recording_id = isset($_POST['recording_id']) ? intval($_POST['recording_id']) : 0;

        // Get the recording first to verify it exists
        $recording = $this->db->get_recording($recording_id);

        if (!$recording) {
            wp_send_json_error(['message' => __('Recording not found', 'reading-assessment')]);
        }

        // Manually trigger the processing
        $ai_evaluator = new Reading_Assessment_AI_Evaluator();

        // First handle transcription if needed
        $result = $ai_evaluator->process_transcription($recording_id);

        if ($result) {
            // If transcription successful, trigger evaluation
            $eval_result = $ai_evaluator->process_recording($recording_id);
            if (is_wp_error($eval_result)) {
                wp_send_json_error(['message' => $eval_result->get_error_message()]);
            }
            wp_send_json_success(['message' => __('Processing completed', 'reading-assessment')]);
        } else {
            wp_send_json_error(['message' => __('Processing failed', 'reading-assessment')]);
        }
    }

    public function register_settings() {
        // Tracking Settings
        register_setting('reading-assessment', 'ra_enable_tracking', [
            'type' => 'boolean',
            'default' => true
        ]);

        register_setting('reading-assessment', 'ra_openai_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        // Tracking Section
        add_settings_section(
            'ra_tracking_settings',
            __('Adminlogg', 'reading-assessment'),
            [$this, 'render_tracking_section'],
            'reading-assessment'
        );

        add_settings_field(
            'ra_enable_tracking',
            __('Aktivera adminloggen', 'reading-assessment'),
            [$this, 'render_tracking_field'],
            'reading-assessment',
            'ra_tracking_settings'
        );


        // AI Section
        add_settings_section(
            'ra_ai_settings',
            __('AI aktivering', 'reading-assessment'),
            null,
            'reading-assessment'
        );

        add_settings_field(
            'ra_openai_api_key',
            __('OpenAI API-nyckel', 'reading-assessment'),
            [$this, 'render_api_key_field'],
            'reading-assessment',
            'ra_ai_settings'
        );
        // NEW AI Settings
        register_setting('reading-assessment', 'ra_enable_ai_evaluation', [
            'type' => 'boolean',
            'default' => true
        ]);

        add_settings_section(
            'ra_ai_settings',
            __('AI-inställningar', 'reading-assessment'),
            [$this, 'render_ai_settings_section'],
            'reading-assessment'
        );

        add_settings_field(
            'ra_enable_ai_evaluation',
            __('Aktivera AI-bedömning', 'reading-assessment'),
            [$this, 'render_ai_enable_field'],
            'reading-assessment',
            'ra_ai_settings'
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

    public function render_api_key_field() {
        $api_key = get_option('ra_openai_api_key', '');
        echo '<input type="password" name="ra_openai_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
        echo '<p class="description">' . __('Din OpenAI API-nyckel här', 'reading-assessment') . '</p>';
    }

    // TESTING AI stuff
    public function render_ai_settings_section() {
        echo '<p>' . __('Inställningar för AI-baserad bedömning av läsning.', 'reading-assessment') . '</p>';
    }

    public function render_ai_enable_field() {
        $enabled = get_option('ra_enable_ai_evaluation', true);
        echo '<input type="checkbox" name="ra_enable_ai_evaluation" value="1" ' . checked(1, $enabled, false) . '/>';
        echo '<p class="description">' . __('Aktivera automatisk AI-bedömning av läsinspelningar.', 'reading-assessment') . '</p>';
    }
}
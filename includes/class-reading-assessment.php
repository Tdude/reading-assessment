<?php
/** includes/class-reading-assessment.php
 * The core plugin class.
 *
 * @package    ReadingAssessment
 * @subpackage ReadingAssessment/includes
 */

class RA {
    private static $instance = null;
    private $admin = null;
    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->plugin_name = 'reading-assessment';
        $this->version = RA_VERSION;

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_cron_hooks();
    }

    private function load_dependencies() {
        require_once RA_PLUGIN_DIR . 'includes/class-ra-security.php';
        require_once RA_PLUGIN_DIR . 'includes/class-ra-error-handler.php';
        require_once RA_PLUGIN_DIR . 'includes/class-ra-loader.php';
        require_once RA_PLUGIN_DIR . 'includes/class-ra-i18n.php';
        require_once RA_PLUGIN_DIR . 'admin/class-ra-admin.php';
        require_once RA_PLUGIN_DIR . 'public/class-ra-public.php';
        require_once RA_PLUGIN_DIR . 'includes/class-ra-recorder.php';
        require_once RA_PLUGIN_DIR . 'includes/class-ra-evaluator.php';
        require_once RA_PLUGIN_DIR . 'includes/class-ra-ai-evaluator.php';
        require_once RA_PLUGIN_DIR . 'includes/class-ra-database.php';


        $this->loader = new RA_Loader();
    }

    private function set_locale() {
        $plugin_i18n = new RA_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function define_admin_hooks() {
        if (!$this->admin === null) {
            return;
        }
        $plugin_admin = new RA_Admin($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_menu_pages');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');


        // AJAX handlers for ADMIN - note the 'admin_' prefix
        // @TODO: simplify into fewer admin nonces if possible!
        $this->loader->add_action('wp_ajax_ra_admin_get_passage', $plugin_admin, 'ajax_get_passage');
        $this->loader->add_action('wp_ajax_ra_admin_get_passages', $plugin_admin, 'ajax_get_passages');
        $this->loader->add_action('wp_ajax_ra_admin_delete_passage', $plugin_admin, 'ajax_delete_passage');
        $this->loader->add_action('wp_ajax_ra_admin_get_questions', $plugin_admin, 'ajax_get_questions');
        $this->loader->add_action('wp_ajax_ra_admin_delete_question', $plugin_admin, 'ajax_delete_question');
        $this->loader->add_action('wp_ajax_ra_admin_get_results', $plugin_admin, 'ajax_get_results');
        $this->loader->add_action('wp_ajax_ra_admin_delete_assignment', $plugin_admin, 'ajax_delete_assignment');
        $this->loader->add_action('wp_ajax_ra_admin_save_assessment', $plugin_admin, 'ajax_save_assessment');
        $this->loader->add_action('wp_ajax_ra_admin_delete_recording', $plugin_admin, 'ajax_delete_recording');
        $this->loader->add_action('wp_ajax_ra_admin_save_interactions', $plugin_admin, 'ajax_save_interactions');
        $this->loader->add_action('wp_ajax_ra_admin_get_progress_data', $plugin_admin, 'ajax_get_progress_data');
        $this->loader->add_action('wp_ajax_ra_admin_ai_evaluate', $plugin_admin, 'ajax_ai_evaluate');
        // Add  AJAX handlers for processing status and triggering
        $this->loader->add_action('wp_ajax_ra_admin_check_processing_status', $plugin_admin, 'ajax_check_processing_status');
        $this->loader->add_action('wp_ajax_ra_admin_trigger_processing', $plugin_admin, 'ajax_trigger_processing');
    }

    private function define_public_hooks() {
        $plugin_public = new RA_Public($this->get_plugin_name(), $this->get_version());

        // Basic hooks
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        $this->loader->add_action('init', $plugin_public, 'register_shortcodes');

        // Public AJAX handlers - note the 'public_' prefix for clarity :)
        $this->loader->add_action('wp_ajax_ra_public_get_questions', $plugin_public, 'ajax_get_questions');
        $this->loader->add_action('wp_ajax_nopriv_ra_public_get_questions', $plugin_public, 'ajax_get_questions');
        $this->loader->add_action('wp_ajax_ra_save_recording', $plugin_public, 'ajax_save_recording');
        $this->loader->add_action('wp_ajax_ra_submit_answers', $plugin_public, 'ajax_submit_answers');
        $this->loader->add_action('wp_ajax_ra_get_assessment', $plugin_public, 'ajax_get_assessment');
        $this->loader->add_action('ra_after_save_recording', $plugin_public, 'schedule_recording_processing');
        // Other hooks
        $this->loader->add_filter('login_redirect', $plugin_public, 'subscriber_login_redirect', 10, 3);
        // $this->loader->add_action('wp_footer', $plugin_public, 'show_login_message');

    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return $this->version;
    }

    private function define_cron_hooks() {
        $ai_evaluator = new RA_AI_Evaluator();
        //$this->loader->add_action('ra_process_transcription', $ai_evaluator, 'process_transcription');
        $this->loader->add_action('ra_process_recording', $ai_evaluator, 'process_recording');
    }
}

<?php
/**
 * The core plugin class.
 *
 * @package    ReadingAssessment
 * @subpackage ReadingAssessment/includes
 */

class Reading_Assessment {

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
    }

    private function load_dependencies() {
        require_once RA_PLUGIN_DIR . 'includes/class-ra-loader.php';
        require_once RA_PLUGIN_DIR . 'includes/class-ra-i18n.php';
        require_once RA_PLUGIN_DIR . 'admin/class-ra-admin.php';
        require_once RA_PLUGIN_DIR . 'public/class-ra-public.php';
        require_once RA_PLUGIN_DIR . 'includes/class-ra-recorder.php';
        require_once RA_PLUGIN_DIR . 'includes/class-ra-evaluator.php';
        require_once RA_PLUGIN_DIR . 'includes/class-ra-database.php';

        $this->loader = new Reading_Assessment_Loader();
    }

    private function set_locale() {
        $plugin_i18n = new Reading_Assessment_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks() {
        $plugin_admin = new Reading_Assessment_Admin($this->get_plugin_name(), $this->get_version());
    
        // Basic admin hooks
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_menu_pages');
    
        // AJAX handlers for admin
        $this->loader->add_action('wp_ajax_ra_get_passage', $plugin_admin, 'ajax_get_passage');
        $this->loader->add_action('wp_ajax_ra_get_passages', $plugin_admin, 'ajax_get_passages');
        $this->loader->add_action('wp_ajax_ra_delete_passage', $plugin_admin, 'ajax_delete_passage');
        $this->loader->add_action('wp_ajax_ra_get_questions', $plugin_admin, 'ajax_get_questions');
        $this->loader->add_action('wp_ajax_ra_delete_question', $plugin_admin, 'ajax_delete_question');
        $this->loader->add_action('wp_ajax_ra_get_results', $plugin_admin, 'ajax_get_results');
        $this->loader->add_action('wp_ajax_ra_delete_assignment', $plugin_admin, 'ajax_delete_assignment');
    }

    private function define_public_hooks() {
        $plugin_public = new Reading_Assessment_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        
        // Register shortcodes
        $this->loader->add_action('init', $plugin_public, 'register_shortcodes');
        
        // AJAX handlers for public
        $this->loader->add_action('wp_ajax_ra_save_recording', $plugin_public, 'ajax_save_recording');
        $this->loader->add_action('wp_ajax_ra_submit_answers', $plugin_public, 'ajax_submit_answers');
        $this->loader->add_action('wp_ajax_ra_get_assessment', $plugin_public, 'ajax_get_assessment');
        
        // Also allow non-logged-in users to view passages
        $this->loader->add_action('wp_ajax_nopriv_ra_get_passage', $plugin_public, 'ajax_get_passage');
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
}
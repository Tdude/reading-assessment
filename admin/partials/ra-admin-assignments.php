<?php
/**
 * admin/partials/ra-admin-assignments.php
 * Handles assignments management in the admin panel
 * Assignments are for a logged in user to be assigned a text passage. It should also be able to be removed.
 */

if (!defined('WPINC')) {
    die;
}

class RA_Assignments_Admin {
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
        add_action('wp_ajax_ra_admin_delete_assignment', array($this, 'ajax_delete_assignment'));
        add_action('wp_ajax_ra_admin_create_assignment', array($this, 'ajax_create_assignment'));
    }

    public function render_page() {
        // Security check
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $this->handle_form_submission();

        // Get data needed for the view
        $users = get_users(['role__not_in' => ['administrator']]);
        $passages = $this->db->get_all_passages();
        $assignments = $this->db->get_all_assignments();
        // Include the view template
        require plugin_dir_path(__FILE__) . 'views/assignments-admin-page.php';
    }

    private function handle_form_submission() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'ra_admin_action')) {
            wp_die(__('Security check failed', 'reading-assessment'));
        }

        $user_id = intval($_POST['user_id']);
        $passage_id = intval($_POST['passage_id']);
        $due_date = !empty($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : null;

        // Verify user and passage exist
        $user = get_user_by('id', $user_id);
        $passage = $this->db->get_passage($passage_id);

        if (!$user || !$passage) {
            $this->messages['error'] = __('Ogiltig användare eller text.', 'reading-assessment');
        } else {
            $result = $this->db->assign_passage_to_user($passage_id, $user_id, get_current_user_id(), $due_date);

            if ($result) {
                $this->messages['success'] = __('Texten blev tilldelad eleven.', 'reading-assessment');
            } else {
                $this->messages['error'] = __('Kunde inte tilldela text.', 'reading-assessment');
            }
        }
    }

    public function ajax_create_assignment() {
        check_ajax_referer('ra_admin_action', 'nonce');

        $user_id = intval($_POST['user_id']);
        $passage_id = intval($_POST['passage_id']);
        $due_date = !empty($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : null;

        // Verify user and passage exist
        $user = get_user_by('id', $user_id);
        $passage = $this->db->get_passage($passage_id);

        if (!$user || !$passage) {
            wp_send_json_error(array('message' => __('Ogiltig användare eller text.', 'reading-assessment')));
        }

        $result = $this->db->assign_passage_to_user($passage_id, $user_id, get_current_user_id(), $due_date);

        if ($result) {
            wp_send_json_success(array('message' => __('Texten blev tilldelad eleven.', 'reading-assessment')));
        } else {
            wp_send_json_error(array('message' => __('Kunde inte tilldela text.', 'reading-assessment')));
        }
    }

    public function ajax_delete_assignment() {
        check_ajax_referer('ra_admin_action', 'nonce');

        $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
        $result = $this->db->delete_assignment($assignment_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Tilldelningen har tagits bort.', 'reading-assessment')));
    }
}
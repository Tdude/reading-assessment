<?php
// admin/partials/ra-admin-questions.php
if (!defined('WPINC')) {
    die;
}

class Reading_Assessment_Questions_Admin {
    private $db;
    public $messages = array();
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) { // ERROR
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->db = new Reading_Assessment_Database();
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_ajax_ra_get_question', array($this, 'ajax_get_question'));
        add_action('wp_ajax_ra_get_questions', array($this, 'ajax_get_questions'));
        add_action('wp_ajax_ra_delete_question', array($this, 'ajax_delete_question'));
    }

    public function render_page() {
        $this->handle_form_submission();
        $passages = $this->db->get_all_passages();
        $selected_passage_id = isset($_GET['passage_id']) ? intval($_GET['passage_id']) :
                            ($passages ? $passages[0]->id : 0);
        $questions = $selected_passage_id ? $this->db->get_questions_for_passage($selected_passage_id) : array();

        // Make variables available to the view
        $view_data = array(
            'messages' => $this->messages,
            'passages' => $passages,
            'selected_passage_id' => $selected_passage_id,
            'questions' => $questions
        );
        extract($view_data);

        // Include the template directly in this file for now
        require __DIR__ . '/views/questions-admin-page.php';
        // If that doesn't work, try:
        // require plugin_dir_path(__FILE__) . 'views/questions-admin-page.php';
    }


    private function handle_form_submission() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ra_question_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['ra_question_nonce'], 'ra_question_action')) {
            wp_die(__('Security check failed', 'reading-assessment'));
        }

        $question_data = array(
            'passage_id' => intval($_POST['passage_id']),
            'question_text' => sanitize_text_field($_POST['question_text']),
            'correct_answer' => sanitize_text_field($_POST['correct_answer']),
            'weight' => floatval($_POST['weight'])
        );

        if (isset($_POST['question_id']) && !empty($_POST['question_id'])) {
            $result = $this->db->update_question(intval($_POST['question_id']), $question_data);
        } else {
            $result = $this->db->create_question($question_data);
        }

        if (is_wp_error($result)) {
            $this->messages['error'] = $result->get_error_message();
        } else {
            $this->messages['success'] = __('Frågan har sparats.', 'reading-assessment');
        }
    }

    public function ajax_get_question() {
        check_ajax_referer('ra_admin_action', 'nonce');

        $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        $question = $this->db->get_question($question_id);

        if (!$question) {
            wp_send_json_error(array('message' => __('Frågan kunde inte hittas.', 'reading-assessment')));
        }

        wp_send_json_success($question);
    }

    public function ajax_get_questions() {
        check_ajax_referer('ra_admin_action', 'nonce');

        $passage_id = isset($_POST['passage_id']) ? intval($_POST['passage_id']) : 0;
        $questions = $this->db->get_questions_for_passage($passage_id);

        ob_start();
        include plugin_dir_path(__FILE__) . 'partials/ra-questions-table.php';
        $html = ob_get_clean();

        wp_send_json_success($html);
    }

    public function ajax_delete_question() {
        check_ajax_referer('ra_admin_action', 'nonce');

        $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        $result = $this->db->delete_question($question_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Frågan har raderats.', 'reading-assessment')));
    }
}
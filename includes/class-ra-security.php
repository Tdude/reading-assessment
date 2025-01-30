<?php
/**
 * File: includes/class-ra-security.php
 * Security improvements for Reading Assessment Plugin
 * New class to centralize security operations
 */

class Reading_Assessment_Security {
    private static $instance = null;
    const NONCE_LIFETIME = 12 * HOUR_IN_SECONDS;

    // Nonce actions
    const NONCE_PUBLIC_RECORDING = 'ra_public_recording_nonce';
    const NONCE_PUBLIC_QUESTIONS = 'ra_public_questions_nonce';
    const NONCE_PUBLIC_ANSWERS = 'ra_public_answers_nonce';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Validate user capabilities for recording actions
     */
    public function can_record() {
        return is_user_logged_in() &&
               (current_user_can('read') || current_user_can('subscriber'));
    }

    /**
     * Validate AJAX request with proper error handling
     */
    public function validate_ajax_request($nonce_action, $required_capability = '') {
        try {
            // Verify nonce
            if (!check_ajax_referer($nonce_action, 'nonce', false)) {
                throw new Exception(__('Invalid security token', 'reading-assessment'));
            }

            // Check user capabilities if required
            if (!empty($required_capability) && !current_user_can($required_capability)) {
                throw new Exception(__('Insufficient permissions', 'reading-assessment'));
            }

            return true;
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'security_error'
            ]);
        }
    }

    /**
     * Sanitize and validate passage ID
     */
    public function validate_passage_id($passage_id) {
        $passage_id = absint($passage_id);
        if (!$passage_id) {
            throw new Exception(__('Invalid passage ID', 'reading-assessment'));
        }

        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ra_passages WHERE id = %d",
            $passage_id
        ));

        if (!$exists) {
            throw new Exception(__('Passage not found', 'reading-assessment'));
        }

        return $passage_id;
    }

    /**
     * Validate uploaded audio file
     */
    public function validate_audio_file($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception(__('Invalid file upload', 'reading-assessment'));
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed_types = ['audio/webm', 'audio/ogg', 'audio/wav'];
        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception(__('Invalid audio file type', 'reading-assessment'));
        }

        // Validate file size (max 50MB)
        $max_size = 50 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            throw new Exception(__('File size too large', 'reading-assessment'));
        }

        return true;
    }

    /**
     * Sanitize answer submissions
     */
    public function sanitize_answers($answers) {
        if (!is_array($answers)) {
            throw new Exception(__('Invalid answer format', 'reading-assessment'));
        }

        $sanitized = [];
        foreach ($answers as $question_id => $answer) {
            $question_id = absint($question_id);
            if (!$question_id) {
                continue;
            }

            $sanitized[$question_id] = sanitize_text_field($answer);
        }

        return $sanitized;
    }

    /**
     * Generate secure filename for audio uploads
     */
    public function generate_secure_filename($extension = 'webm') {
        return sprintf(
            'recording_%s_%s.%s',
            wp_generate_password(12, false),
            time(),
            sanitize_file_name($extension)
        );
    }

    /**
     * Validate recording ownership
     */
    public function validate_recording_ownership($recording_id) {
        global $wpdb;
        $user_id = get_current_user_id();

        $recording = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}ra_recordings WHERE id = %d",
            absint($recording_id)
        ));

        if (!$recording || $recording->user_id !== $user_id) {
            throw new Exception(__('Invalid recording access', 'reading-assessment'));
        }

        return true;
    }
}
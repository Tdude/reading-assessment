<?php
/**
 * File: includes/class-ra-security.php
 * Security improvements for Reading Assessment Plugin
 * New class to centralize security operations
 */

class RA_Security {
    private static $instance = null;
    const NONCE_LIFETIME = 12 * HOUR_IN_SECONDS;

    // Nonce actions
	const NONCE_PUBLIC = 'ra_public_action';

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
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], $nonce_action)) {
            throw new Exception(__('Invalid security token', 'reading-assessment'));
        }

        // Check user capabilities if required
        if (!empty($required_capability) && !current_user_can($required_capability)) {
            throw new Exception(__('Insufficient permissions', 'reading-assessment'));
        }

        return true;
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

        $allowed_types = ['video/webm', 'audio/webm', 'audio/ogg', 'audio/wav'];
        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception(__('Invalid audio file type', 'reading-assessment'));
        }

        error_log('File MIME type: ' . $mime_type);
        error_log('Allowed types: ' . implode(', ', $allowed_types));

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

        if ($user_id === 0) {
            error_log('No user is currently logged in');
            throw new Exception(__('User not logged in', 'reading-assessment'));
        }

        // More detailed query to get full recording information
        $recording = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, passage_id, audio_file_path
             FROM {$wpdb->prefix}ra_recordings
             WHERE id = %d",
            absint($recording_id)
        ));

        error_log('Full Recording Query Result: ' . print_r($recording, true));

        if (!$recording) {
            error_log('No recording found with the given ID');
            throw new Exception(__('Recording not found', 'reading-assessment'));
        }

        if ($recording->user_id != $user_id) {
            error_log('User ID mismatch');
            error_log('Recording User ID: ' . $recording->user_id);
            error_log('Current User ID: ' . $user_id);
            throw new Exception(__('Invalid recording access', 'reading-assessment'));
        }

        return true;
    }
}

<?php
/** File: admin/includes/class-ra-recorder.php
 * Handles public audio recording functionality.
 *
 * @package    ReadingAssessment
 * @subpackage ReadingAssessment/includes
 */

class Reading_Assessment_Recorder {

    private $upload_dir;
    private $allowed_mime_types;
    private $max_file_size; // in bytes

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->upload_dir = $upload_dir['basedir'] . '/reading-assessment';

        require_once RA_PLUGIN_DIR . 'includes/class-ra-error-handler.php';

        $this->allowed_mime_types = array(
            'audio/webm',
            'audio/ogg',
            'audio/wav',
            'audio/mp3',
            'audio/mpeg'
        );
        $this->max_file_size = 10 * 1024 * 1024; // 10MB

        // Create upload directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }
    }

    /**
     * Save uploaded audio file
     *
     * @param array $file $_FILES array element
     * @param int $user_id User ID
     * @param int $passage_id Passage ID
     * @return array|WP_Error Success/error info
     */
    public function save_recording($file, $user_id, $passage_id) {
        // Validate file
        $validation = $this->validate_audio_file($file);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Generate unique filename
        $filename = sprintf(
            'recording-%d-%d-%s.%s',
            $user_id,
            $passage_id,
            uniqid(),
            pathinfo($file['name'], PATHINFO_EXTENSION)
        );

        $filepath = $this->upload_dir . '/' . $filename;

        // Move file to uploads directory
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return new WP_Error(
                'upload_error',
                __('Failed to save recording file.', 'reading-assessment')
            );
        }

        // Get file duration
        $duration = $this->get_audio_duration($filepath);

        return array(
            'file_path' => $filepath,
            'duration' => $duration,
            'file_name' => $filename
        );
    }

    /**
     * Validate uploaded audio file
     *
     * @param array $file $_FILES array element
     * @return true|WP_Error True if valid, WP_Error if not
     */
    private function validate_audio_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return RA_Error_Handler::generate_error(
                'upload_error',
                RA_Error_Handler::get_upload_error_message($file['error'])
            );
        }
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return RA_Error_Handler::generate_error(
                'file_too_large',
                'The audio file is too large.'
            );
        }
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $this->allowed_mime_types)) {
            return RA_Error_Handler::generate_error(
                'invalid_file_type',
                'Invalid audio file type.'
            );
        }
        return true;
    }

    /**
     * Get audio file duration
     *
     * @param string $filepath Path to audio file
     * @return int Duration in seconds
     */
    private function get_audio_duration($filepath) {
        // Note: This would require getID3 or similar library
        // For now, return placeholder duration
        return 30;
    }

    /**
     * Delete recording file
     *
     * @param string $filepath Path to audio file
     * @return boolean True on success, false on failure
     */
    public function delete_recording($filepath) {
        if (file_exists($filepath) && is_file($filepath)) {
            return unlink($filepath);
        }
        return false;
    }
}
<?php
/**
 * File: admin/includes/class-ra-error-handler.php
 * RA_Error_Handler
 * A centralized error handling class for the Reading Assessment plugin.
 */
class RA_Error_Handler {

    /**
     * Generate a WP_Error object with a standardized error message.
     *
     * @param string $code Error code.
     * @param string $message Error message.
     * @return WP_Error The WP_Error object.
     */
    public static function generate_error($code, $message) {
        return new WP_Error($code, __($message, 'reading-assessment'));
    }

    /**
     * Log an error message.
     *
     * @param string $message Error message to log.
     */
    public static function log_error($message) {
        error_log($message);
    }

    /**
     * Register and check class dependencies
     *
     * @param string $class_name The class to check for
     * @return bool True if dependencies are met
     */
    public static function check_class_exists($class_name) {
        if (!class_exists($class_name)) {
            self::log_error("Required class {$class_name} not loaded");
            return false;
        }
        return true;
    }

    /**
     * Get the error message for a given PHP upload error code.
     *
     * @param int $error_code PHP upload error code.
     * @return string Error message.
     */
    public static function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return __('The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'reading-assessment');
            case UPLOAD_ERR_FORM_SIZE:
                return __('The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.', 'reading-assessment');
            case UPLOAD_ERR_PARTIAL:
                return __('The uploaded file was only partially uploaded.', 'reading-assessment');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded.', 'reading-assessment');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing a temporary folder.', 'reading-assessment');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk.', 'reading-assessment');
            case UPLOAD_ERR_EXTENSION:
                return __('A PHP extension stopped the file upload.', 'reading-assessment');
            default:
                return __('Unknown upload error.', 'reading-assessment');
        }
    }
}
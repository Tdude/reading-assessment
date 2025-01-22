<?php
/** class-ra-deactivator.php
 * Fired during plugin deactivation.
 *
 * @package    ReadingAssessment
 * @subpackage ReadingAssessment/includes
 */

class Reading_Assessment_Deactivator {
    /**
     * Remove plugin tables and options during deactivation
     */
    public static function deactivate() {
        global $wpdb;

        // Only remove tables if clean uninstall is enabled
        if (get_option('ra_clean_uninstall', false)) {
            // Drop all plugin tables
            $tables = array(
                'ra_passages',
                'ra_questions',
                'ra_recordings',
                'ra_responses',
                'ra_assessments'
            );

            foreach ($tables as $table) {
                $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
            }

            // Remove plugin options
            $options = array(
                'ra_clean_uninstall',
                'ra_db_version',
                'ra_settings'
            );

            foreach ($options as $option) {
                delete_option($option);
            }

            // Remove uploaded files
            $upload_dir = wp_upload_dir();
            $ra_upload_dir = $upload_dir['basedir'] . '/reading-assessment';

            if (is_dir($ra_upload_dir)) {
                self::delete_directory($ra_upload_dir);
            }
        }
    }

    /**
     * Recursively delete a directory
     *
     * @param string $dir Directory path
     * @return bool
     */
    private static function delete_directory($dir) {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!self::delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }
}
<?php
/** class-ra-activator.php
 * Fired during plugin activation.
 *
 * @package    ReadingAssessment
 * @subpackage ReadingAssessment/includes
 */

class RA_Activator
{
    /**
     * Create the necessary database tables and plugin setup.
     */
    public static function activate()
    {
        self::create_database_tables();
        self::upgrade_database_schema();
        self::create_directories();
        self::set_plugin_options();
    }


    /**
     * Add new columns and upgrade database schema
     */
    private static function upgrade_database_schema()
    {
        global $wpdb;
        $current_db_version = get_option('ra_db_version', '1.0');
        // Helper function for column existence
        $column_exists = function ($table, $column) use ($wpdb) {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = %s
                AND TABLE_NAME = %s
                AND COLUMN_NAME = %s",
                DB_NAME, $table, $column
            ));
        };

        // Upgrade to 1.1 - Add difficulty_level
        if (version_compare($current_db_version, '1.1', '<')) {
            if (!$column_exists("{$wpdb->prefix}ra_passages", 'difficulty_level')) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}ra_passages
                            ADD COLUMN difficulty_level int(11) DEFAULT 1");

                if ($wpdb->last_error) {
                    error_log("Failed to add difficulty_level column: " . $wpdb->last_error);
                    return false;
                }
            }
            update_option('ra_db_version', '1.1');
        }

        // Upgrade to 1.2 - Create assignments table
        if (version_compare($current_db_version, '1.2', '<')) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ra_assignments (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id bigint(20) UNSIGNED NOT NULL,
                passage_id bigint(20) UNSIGNED NOT NULL,
                assigned_by bigint(20) UNSIGNED NOT NULL,
                assigned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                due_date datetime DEFAULT NULL,
                status varchar(20) DEFAULT 'pending',
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY passage_id (passage_id),
                CONSTRAINT fk_assignment_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE,
                CONSTRAINT fk_assignment_passage FOREIGN KEY (passage_id) REFERENCES {$wpdb->prefix}ra_passages (id) ON DELETE CASCADE
            ) {$charset_collate}";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            if ($wpdb->last_error) {
                error_log("Failed to create assignments table: " . $wpdb->last_error);
                return false;
            }
            update_option('ra_db_version', '1.2');
        }

        // Upgrade to 1.3 - Add admin interactions table columns
        if (version_compare($current_db_version, '1.3', '<')) {
            $table_name = $wpdb->prefix . 'ra_admin_interactions';

            // Log upgrade process
            //error_log("Starting upgrade to version 1.3...");

            // Check if columns exist
            $row = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'clicks'");
            if (empty($row)) {
                $wpdb->query("ALTER TABLE {$table_name}
                            ADD COLUMN clicks int DEFAULT 0,
                            ADD COLUMN active_time int DEFAULT 0,
                            ADD COLUMN idle_time int DEFAULT 0");

                if ($wpdb->last_error) {
                    error_log("Failed to add interaction columns: " . $wpdb->last_error);
                    return false;
                } else {
                    error_log("Added clicks, active_time, and idle_time columns to {$table_name}.");
                }
            } else {
                error_log("No changes needed for version 1.3 - required columns already exist.");
            }
            update_option('ra_db_version', '1.3');
            error_log("Upgrade to version 1.3 completed.");
        }

        // Upgrade to 1.4 - Add transcription column
        if (version_compare($current_db_version, '1.4', '<')) {
            $table_name = $wpdb->prefix . 'ra_recordings';

            // Log upgrade process
            error_log("Starting upgrade to version 1.4...");

            // Add transcription column if it doesn't exist
            $transcription_column_check_result = $wpdb->get_results($wpdb->prepare(
                "SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = %s
                AND TABLE_NAME = %s
                AND COLUMN_NAME = 'transcription'",
                DB_NAME,
                $table_name
            ));

            if (empty($transcription_column_check_result)) {
                $wpdb->query("ALTER TABLE {$table_name}
                    ADD COLUMN transcription TEXT NULL DEFAULT NULL
                    AFTER audio_file_path");

                if ($wpdb->last_error) {
                    error_log("Failed to add transcription column: " . $wpdb->last_error);
                    return false;
                } else {
                    error_log("Added transcription column to {$table_name}.");
                }
            } else {
                error_log("No changes needed for version 1.4 - transcription column already exists.");
            }
            update_option('ra_db_version', '1.4');
            error_log("Upgrade to version 1.4 completed.");
        }

        // Upgrade to 1.5 - Add user_grade column to ra_recordings
        if (version_compare($current_db_version, '1.5', '<')) {
            $table_name = $wpdb->prefix . 'ra_recordings';
            error_log("Starting upgrade to version 1.5 for user_grade...");

            if (!$column_exists($table_name, 'user_grade')) {
                $wpdb->query("ALTER TABLE {$table_name}
                    ADD COLUMN user_grade VARCHAR(50) NULL DEFAULT NULL AFTER passage_id");

                if ($wpdb->last_error) {
                    error_log("Failed to add user_grade column to {$table_name}: " . $wpdb->last_error);
                    // Optionally return false or throw an exception if critical
                } else {
                    error_log("Added user_grade column to {$table_name}.");
                }
            } else {
                error_log("No changes needed for version 1.5 - user_grade column already exists in {$table_name}.");
            }
            update_option('ra_db_version', '1.5');
            error_log("Upgrade to version 1.5 completed.");
        }

    }

    /**
     * Create all required database tables
     */
    private static function create_database_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Table definitions
        $tables = array(
            'ra_passages' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ra_passages (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                title varchar(255) NOT NULL,
                content longtext NOT NULL,
                time_limit int(11) DEFAULT 180,
                difficulty_level int(11) DEFAULT 1,
                audio_file varchar(255) DEFAULT NULL,
                created_by bigint(20) UNSIGNED NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY created_by (created_by)
            ) {$charset_collate}",

            'ra_recordings' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ra_recordings (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id bigint(20) UNSIGNED NOT NULL,
                passage_id bigint(20) UNSIGNED NOT NULL,
                user_grade VARCHAR(50) NULL DEFAULT NULL,
                audio_file_path varchar(255) NOT NULL,
                transcription TEXT NULL DEFAULT NULL,
                duration int(11) NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY passage_id (passage_id),
                CONSTRAINT fk_recording_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE,
                CONSTRAINT fk_recording_passage FOREIGN KEY (passage_id) REFERENCES {$wpdb->prefix}ra_passages (id) ON DELETE CASCADE
            ) {$charset_collate}",

            'ra_questions' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ra_questions (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                passage_id bigint(20) UNSIGNED NOT NULL,
                question_text text NOT NULL,
                correct_answer text NOT NULL,
                weight float DEFAULT 1.0,
                PRIMARY KEY (id),
                KEY passage_id (passage_id),
                CONSTRAINT fk_question_passage FOREIGN KEY (passage_id) REFERENCES {$wpdb->prefix}ra_passages (id) ON DELETE CASCADE
            ) {$charset_collate};",

            'ra_responses' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ra_responses (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                recording_id bigint(20) UNSIGNED NOT NULL,
                question_id bigint(20) UNSIGNED NOT NULL,
                user_answer text NOT NULL,
                is_correct tinyint(1) DEFAULT 0,
                score float DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY recording_id (recording_id),
                KEY question_id (question_id),
                CONSTRAINT fk_response_recording
                    FOREIGN KEY (recording_id)
                    REFERENCES {$wpdb->prefix}ra_recordings (id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_response_question
                    FOREIGN KEY (question_id)
                    REFERENCES {$wpdb->prefix}ra_questions (id)
                    ON DELETE CASCADE
            ) $charset_collate;",

            'ra_assessments' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ra_assessments (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                recording_id bigint(20) UNSIGNED NOT NULL,
                total_score float DEFAULT 0,
                normalized_score float DEFAULT 0,
                completed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY recording_id (recording_id),
                CONSTRAINT fk_assessment_recording FOREIGN KEY (recording_id) REFERENCES {$wpdb->prefix}ra_recordings (id) ON DELETE CASCADE
            ) {$charset_collate};",

            'ra_assignments' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ra_assignments (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id bigint(20) UNSIGNED NOT NULL,
                passage_id bigint(20) UNSIGNED NOT NULL,
                assigned_by bigint(20) UNSIGNED NOT NULL,
                assigned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                due_date datetime DEFAULT NULL,
                status varchar(20) DEFAULT 'pending',
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY passage_id (passage_id),
                CONSTRAINT fk_assignment_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE,
                CONSTRAINT fk_assignment_passage FOREIGN KEY (passage_id) REFERENCES {$wpdb->prefix}ra_passages (id) ON DELETE CASCADE
            ) {$charset_collate};",

            'ra_admin_interactions' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ra_admin_interactions (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id bigint(20) UNSIGNED NOT NULL,
                clicks int DEFAULT 0,
                active_time int DEFAULT 0,
                idle_time int DEFAULT 0,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY created_at (created_at),
                CONSTRAINT fk_interaction_user FOREIGN KEY (user_id)
                    REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
            ) {$charset_collate}",

            'ra_ai_evaluations' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ra_ai_evaluations (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                recording_id bigint(20) UNSIGNED NOT NULL,
                evaluation_data text NOT NULL,
                lus_score float NOT NULL,
                confidence_score float NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY recording_id (recording_id),
                CONSTRAINT fk_ai_eval_recording FOREIGN KEY (recording_id)
                    REFERENCES {$wpdb->prefix}ra_recordings (id) ON DELETE CASCADE
            ) {$charset_collate}",

            // @TODO: verify this table is added/removed and works.
            // No methods added so far! They should operate in the PUBLIC part of the site, but behind login.
            'ra_audio_responses' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ra_audio_responses (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                recording_id bigint(20) UNSIGNED NOT NULL,
                question_id bigint(20) UNSIGNED NOT NULL,
                audio_file_path varchar(255) NOT NULL,
                duration int(11) NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY recording_id (recording_id),
                KEY question_id (question_id),
                CONSTRAINT fk_audio_response_recording FOREIGN KEY (recording_id)
                    REFERENCES {$wpdb->prefix}ra_recordings (id) ON DELETE CASCADE,
                CONSTRAINT fk_audio_response_question FOREIGN KEY (question_id)
                    REFERENCES {$wpdb->prefix}ra_questions (id) ON DELETE CASCADE
            ) {$charset_collate}"

        );

        // Create tables
        foreach ($tables as $table_name => $sql) {
            $result = dbDelta($sql);
            if (!empty($wpdb->last_error)) {
                error_log("Error creating table {$table_name}: {$wpdb->last_error}");
            }
        }
    }

    /**
     * Create required directories
     */
    private static function create_directories()
    {
        $upload_dir = wp_upload_dir();
        $dirs = array(
            $upload_dir['basedir'] . '/reading-assessment',
            $upload_dir['basedir'] . '/reading-assessment/' . date('Y'),
            $upload_dir['basedir'] . '/reading-assessment/' . date('Y') . '/' . date('m')
        );

        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                if (!wp_mkdir_p($dir)) {
                    error_log("Failed to create directory: {$dir}");
                } else {
                    // Create .htaccess to protect sneaky directory listing
                    $htaccess = $dir . '/.htaccess';
                    if (!file_exists($htaccess)) {
                        file_put_contents($htaccess, "Options -Indexes\n");
                    }
                }
            }
        }
    }

    /**
     * Set plugin options
     */
    private static function set_plugin_options()
    {
        $options = array(
            'ra_version' => RA_VERSION,
            'ra_db_version' => '1.0',
            'ra_installed_at' => current_time('mysql')
        );

        foreach ($options as $key => $value) {
            update_option($key, $value, 'no');
        }
    }
}

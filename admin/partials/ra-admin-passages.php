<?php
// admin/partials/ra-admin-passages.php
if (!defined('WPINC')) {
    die;
}

global $wpdb;
$test_query = "SELECT COUNT(*) as total FROM {$wpdb->prefix}ra_recordings";
$total_recordings = $wpdb->get_var($test_query);

// Get database instance
$ra_db = new RA_Database();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ra_passage_nonce'])) {
    if (!wp_verify_nonce($_POST['ra_passage_nonce'], 'ra_passage_action')) {
        wp_die(__('Security check failed', 'reading-assessment'));
    }

    $passage_data = array(
        'title' => sanitize_text_field($_POST['title']),
        'content' => wp_kses_post($_POST['content']),
        'time_limit' => intval($_POST['time_limit']),
        'difficulty_level' => intval($_POST['difficulty_level']),
    );

    // Handle file upload
    if (!empty($_FILES['audio_file']['name'])) {
        $upload_dir = wp_upload_dir();
        $ra_upload_dir = $upload_dir['basedir'] . '/reading-assessment';

        // Create directory if it doesn't exist
        if (!file_exists($ra_upload_dir)) {
            if (!wp_mkdir_p($ra_upload_dir)) {
                $error_message = __('Failed to create upload directory', 'reading-assessment');
                // error_log('Failed to create directory: ' . $ra_upload_dir);
            }
        }

        if (!isset($error_message)) {
            $file_name = sanitize_file_name($_FILES['audio_file']['name']);
            $file_path = $ra_upload_dir . '/' . $file_name;

            if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $file_path)) {
                $passage_data['audio_file'] = $file_name;
            } else {
                // error_log('Failed to move uploaded file to: ' . $file_path);
                $error_message = __('Failed to upload audio file', 'reading-assessment');
            }
        }
    }

    if (!isset($error_message)) {
        if (isset($_POST['passage_id']) && !empty($_POST['passage_id'])) {
            $result = $ra_db->update_passage(intval($_POST['passage_id']), $passage_data);
        } else {
            $result = $ra_db->create_passage($passage_data);
        }

        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            // error_log('Passage creation/update error: ' . $error_message);
        } else {
            $success_message = __('Text saved successfully.', 'reading-assessment');
        }
    }
}
?>

<div class="wrap" data-page="passages">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <button type="button" class="page-title-action" data-action="new-passage">
        <?php _e('Lägg till ny text', 'reading-assessment'); ?>
    </button>


    <?php if (isset($error_message)): ?>
    <div class="notice notice-error">
        <p><?php echo esc_html($error_message); ?></p>
    </div>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
    <div class="notice notice-success">
        <p><?php echo esc_html($success_message); ?></p>
    </div>
    <?php endif; ?>

    <!-- List of existing passages -->
    <div class="ra-passages-list">
        <h2><?php _e('Textpassager och lite data', 'reading-assessment'); ?></h2>
        <?php
        $passages = $ra_db->get_all_passages(); // We need to add this method to the database class

        add_action('admin_notices', function() {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p>' . __('Debug: Reading Assessment passages page loaded', 'reading-assessment') . '</p>';
            echo '</div>';
        });

        // See if we're getting passage data
        // error_log('Reading Assessment - Passages count: ' . count($passages));
        ?>

        <?php if ($passages): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Titel', 'reading-assessment'); ?></th>
                    <th><?php _e('Antal inspelningar', 'reading-assessment'); ?></th>
                    <th><?php _e('Tidsgräns', 'reading-assessment'); ?></th>
                    <th><?php _e('Grad', 'reading-assessment'); ?></th>
                    <th><?php _e('Inläsningar', 'reading-assessment'); ?></th>
                    <th><?php _e('Skapad', 'reading-assessment'); ?></th>
                    <th><?php _e('Aktivitet', 'reading-assessment'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($passages as $passage):
                // DEBUGGGG
                error_log('Rendering passage ID: ' . $passage->id);

                        $stats = $ra_db->get_passage_statistics($passage->id);
                        // Get recording count directly with a simple query
                        $recording_count = $ra_db->get_passage_recording_count($passage->id);
                    ?>
                <tr>
                    <td>
                        <strong>
                            <a href="#" class="ra-edit-passage" data-action="edit"
                                data-id="<?php echo esc_attr($passage->id); ?>">
                                <?php echo esc_html($passage->title); ?>
                            </a>
                        </strong>
                    </td>
                    <td>
                        <?php
                                global $wpdb;
                                // Use direct table name for debugging
                                $recording_count = $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->prefix}ra_recordings
                                    WHERE passage_id = %d",
                                    $passage->id
                                ));

                                if ($recording_count > 0): ?>
                        <a href="<?php echo esc_url(add_query_arg(array(
                                        'page' => 'reading-assessment',
                                        'passage_filter' => $passage->id
                                    ), admin_url('admin.php'))); ?>" class="recording-count-link">
                            <?php printf(
                                            _n('%d inspelning', '%d inspelningar', $recording_count, 'reading-assessment'),
                                            $recording_count
                                        ); ?>
                        </a>
                        <?php else: ?>
                        <span class="no-recordings">
                            <?php _e('Inga inspelningar', 'reading-assessment'); ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($passage->time_limit); ?> <?php _e('sekunder', 'reading-assessment'); ?>
                    </td>
                    <td><?php echo esc_html($passage->difficulty_level); ?></td>
                    <td>
                        <?php if ($stats && $stats->total_attempts > 0): ?>
                        <?php
                                    printf(
                                        __('Antal försök: %1$d<br>Medelresultat: %2$.1f ', 'reading-assessment'),
                                        $stats->total_attempts,
                                        $stats->average_score
                                    );
                                    ?>
                        <?php else: ?>
                        <?php _e('Inga försök här än', 'reading-assessment'); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($passage->created_at))); ?>
                    </td>
                    <td>
                        <div class="button-group">
                            <button type="button" class="button button-primary" style="width: 4rem;" data-action="edit"
                                data-id="<?php echo esc_attr($passage->id); ?>">
                                <?php _e('Ändra', 'reading-assessment'); ?>
                            </button>
                            <button type="button" class="button button-link-delete" style="width: 4rem;"
                                data-action="delete" data-module="passages"
                                data-id="<?php echo esc_attr($passage->id); ?>">
                                <?php _e('Radera', 'reading-assessment'); ?>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><?php _e('Inga texter hittade.', 'reading-assessment'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Add/Edit passage form -->
    <div class="ra-passage-form-container">
        <h2 id="ra-form-title"><?php _e('Lägg till ny text', 'reading-assessment'); ?></h2>
        <form id="ra-passage-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('ra_passage_action', 'ra_passage_nonce'); ?>
            <input type="hidden" name="passage_id" id="passage_id" value="">

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="title"><?php _e('Titel', 'reading-assessment'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="title" name="title" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="content"><?php _e('Textinnehåll', 'reading-assessment'); ?></label>
                    </th>
                    <td>
                        <?php // wpautop makes visual mode create html
                            wp_editor('', 'content', array(
                                'media_buttons' => false,
                                'textarea_rows' => 10,
                                'teeny' => true,
                                'tinymce' => array(
                                    'forced_root_block' => 'p',
                                    'remove_linebreaks' => false,
                                    'convert_newlines_to_brs' => false,
                                    'remove_redundant_brs' => false
                                )
                            ));
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="time_limit"><?php _e('Time Limit (seconds)', 'reading-assessment'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="time_limit" name="time_limit" value="180" min="30" step="1">
                        <p class="description">
                            <?php _e('Tidsgräns för inspelning i sekunder.', 'reading-assessment'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="difficulty_level"><?php _e('Svårighetsgrad', 'reading-assessment'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="difficulty_level" name="difficulty_level" value="1" min="1" max="20"
                            step="1">
                        <p class="description">
                            <?php _e('Svårighetsgrad 1-20 där 20 är svårast.', 'reading-assessment'); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary"
                    value="<?php _e('Spara text', 'reading-assessment'); ?>">
                <button type="button" id="ra-cancel-edit" class="button" style="display:none;">
                    <?php _e('Avbryt', 'reading-assessment'); ?>
                </button>
            </p>
        </form>
    </div>
</div>
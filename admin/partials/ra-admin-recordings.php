<?php
if (!defined('WPINC')) {
    die;
}

$ra_db = new Reading_Assessment_Database();
$passages = $ra_db->get_all_passages();

// Handle bulk assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ra_bulk_assign_nonce'])) {
    if (!wp_verify_nonce($_POST['ra_bulk_assign_nonce'], 'ra_bulk_assign_action')) {
        wp_die(__('Security check failed', 'reading-assessment'));
    }

    if (isset($_POST['recording_passages']) && is_array($_POST['recording_passages'])) {
        $success_count = 0;
        $error_count = 0;

        foreach ($_POST['recording_passages'] as $recording_id => $passage_id) {
            if ($passage_id > 0) {
                $result = $ra_db->update_recording_passage($recording_id, $passage_id);
                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }

        if ($success_count > 0) {
            $success_message = sprintf(
                _n(
                    '%d inspelning uppdaterad.',
                    '%d inspelningar uppdaterade.',
                    $success_count,
                    'reading-assessment'
                ),
                $success_count
            );
        }

        if ($error_count > 0) {
            $error_message = sprintf(
                _n(
                    '%d inspelning kunde inte uppdateras.',
                    '%d inspelningar kunde inte uppdateras.',
                    $error_count,
                    'reading-assessment'
                ),
                $error_count
            );
        }
    }
}

// Get recordings with pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Get unassigned recordings
$unassigned_recordings = $ra_db->get_unassigned_recordings($per_page, $offset);
$total_unassigned = $ra_db->get_total_unassigned_recordings();
$total_pages = ceil($total_unassigned / $per_page);
?>

<div class="wrap">
    <h1><?php echo esc_html__('Hantera inspelningar', 'reading-assessment'); ?></h1>

    <?php if (isset($success_message)): ?>
    <div class="notice notice-success">
        <p><?php echo esc_html($success_message); ?></p>
    </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="notice notice-error">
        <p><?php echo esc_html($error_message); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($unassigned_recordings): ?>
    <form method="post" id="ra-recordings-form">
        <?php wp_nonce_field('ra_bulk_assign_action', 'ra_bulk_assign_nonce'); ?>

        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="bulk_passage_id" id="bulk-passage-id">
                    <option value=""><?php _e('Välj text...', 'reading-assessment'); ?></option>
                    <?php foreach ($passages as $passage): ?>
                    <option value="<?php echo esc_attr($passage->id); ?>">
                        <?php echo esc_html($passage->title); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button" id="bulk-assign">
                    <?php _e('Tilldela markerade', 'reading-assessment'); ?>
                </button>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-1">
                    </td>
                    <th><?php _e('Användare', 'reading-assessment'); ?></th>
                    <th><?php _e('Inspelning', 'reading-assessment'); ?></th>
                    <th><?php _e('Längd', 'reading-assessment'); ?></th>
                    <th><?php _e('Inspelad', 'reading-assessment'); ?></th>
                    <th><?php _e('Tilldela till text', 'reading-assessment'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($unassigned_recordings as $recording):
                        $file_path = wp_upload_dir()['baseurl'] . $recording->audio_file_path;
                    ?>
                <tr>
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="recording_ids[]" value="<?php echo esc_attr($recording->id); ?>">
                    </th>
                    <td><?php echo esc_html($recording->display_name); ?></td>
                    <td>
                        <audio controls style="max-width: 250px;">
                            <source src="<?php echo esc_url($file_path); ?>" type="audio/webm">
                        </audio>
                    </td>
                    <td><?php echo esc_html($recording->duration ? round($recording->duration, 1) . 's' : 'N/A'); ?>
                    </td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($recording->created_at))); ?>
                    </td>
                    <td>
                        <select name="recording_passages[<?php echo esc_attr($recording->id); ?>]"
                            class="recording-passage-select">
                            <option value=""><?php _e('Välj text...', 'reading-assessment'); ?></option>
                            <?php foreach ($passages as $passage): ?>
                            <option value="<?php echo esc_attr($passage->id); ?>">
                                <?php echo esc_html($passage->title); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <button type="submit" class="button button-primary">
                    <?php _e('Spara tilldelningar', 'reading-assessment'); ?>
                </button>
            </div>
            <?php if ($total_pages > 1): ?>
            <div class="tablenav-pages">
                <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        ?>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <script>
    jQuery(document).ready(function($) {
        $('#bulk-assign').on('click', function() {
            var selectedPassage = $('#bulk-passage-id').val();
            if (!selectedPassage) {
                alert('<?php echo esc_js(__('Välj en text först', 'reading-assessment')); ?>');
                return;
            }

            $('input[name="recording_ids[]"]:checked').each(function() {
                var recordingId = $(this).val();
                $('select[name="recording_passages[' + recordingId + ']"]').val(
                selectedPassage);
            });
        });

        $('#cb-select-all-1').on('change', function() {
            $('input[name="recording_ids[]"]').prop('checked', $(this).prop('checked'));
        });
    });
    </script>
    <?php else: ?>
    <p><?php _e('Inga otilldelade inspelningar hittades.', 'reading-assessment'); ?></p>
    <?php endif; ?>
</div>
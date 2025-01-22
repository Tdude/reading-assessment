<?php
if (!defined('WPINC')) {
    die;
}

$ra_db = new Reading_Assessment_Database();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ra_repair_nonce'])) {
    if (!wp_verify_nonce($_POST['ra_repair_nonce'], 'ra_repair_action')) {
        wp_die(__('Security check failed', 'reading-assessment'));
    }

    if (isset($_POST['recording_passages']) && is_array($_POST['recording_passages'])) {
        $results = $ra_db->batch_update_recordings($_POST['recording_passages']);

        if ($results['success'] > 0) {
            $success_message = sprintf(
                _n(
                    '%d inspelning uppdaterad.',
                    '%d inspelningar uppdaterade.',
                    $results['success'],
                    'reading-assessment'
                ),
                $results['success']
            );
        }

        if (!empty($results['errors'])) {
            $error_message = __('Några uppdateringar misslyckades:', 'reading-assessment') .
                           '<br>' . implode('<br>', $results['errors']);
        }
    }
}

// Pagination setup
$per_page = 50;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Get data
$orphaned_recordings = $ra_db->get_orphaned_recordings($per_page, $offset);
$total_orphaned = $ra_db->get_total_orphaned_recordings();
$total_pages = ceil($total_orphaned / $per_page);
$passages = $ra_db->get_all_passages();
?>

<div class="wrap" data-page="repair-recordings">
    <h1><?php echo esc_html__('Reparera inspelningar', 'reading-assessment'); ?></h1>

    <?php if ($total_orphaned > 0): ?>
    <div class="notice notice-warning">
        <p>
            <?php printf(
                    __('Hittade %d inspelningar som saknar koppling till text. Använd formuläret nedan för att åtgärda detta.', 'reading-assessment'),
                    $total_orphaned
                ); ?>
        </p>
    </div>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
    <div class="notice notice-success">
        <p><?php echo esc_html($success_message); ?></p>
    </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="notice notice-error">
        <p><?php echo wp_kses_post($error_message); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($orphaned_recordings): ?>
    <form method="post">
        <?php wp_nonce_field('ra_repair_action', 'ra_repair_nonce'); ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Användare', 'reading-assessment'); ?></th>
                    <th><?php _e('E-post', 'reading-assessment'); ?></th>
                    <th><?php _e('Inspelning', 'reading-assessment'); ?></th>
                    <th><?php _e('Datum', 'reading-assessment'); ?></th>
                    <th><?php _e('Tilldela till text', 'reading-assessment'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orphaned_recordings as $recording):
                        $audio_url = wp_upload_dir()['baseurl'] . $recording->audio_file_path;
                    ?>
                <tr>
                    <td><?php echo esc_html($recording->display_name); ?></td>
                    <td><?php echo esc_html($recording->user_email); ?></td>
                    <td>
                        <audio controls style="max-width: 250px;">
                            <source src="<?php echo esc_url($audio_url); ?>" type="audio/webm">
                        </audio>
                    </td>
                    <td>
                        <?php echo esc_html(
                                    date_i18n(
                                        get_option('date_format') . ' ' . get_option('time_format'),
                                        strtotime($recording->created_at)
                                    )
                                ); ?>
                    </td>
                    <td>
                        <select name="recording_passages[<?php echo esc_attr($recording->id); ?>]">
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

        <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
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
        </div>
        <?php endif; ?>

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary"
                value="<?php esc_attr_e('Uppdatera tilldelningar', 'reading-assessment'); ?>">
        </p>
    </form>
    <?php else: ?>
    <div class="notice notice-success">
        <p><?php _e('Inga inspelningar behöver åtgärdas!', 'reading-assessment'); ?></p>
    </div>
    <?php endif; ?>
</div>
<?php
/**
 * admin/partials/viewsassignments-admin-page.php
 * Handles assignments management in the admin panel
 * Assignments are for a logged in user to be assigned a text passage. It should also be able to be removed.
 */

if (!defined('WPINC')) {
    die;
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

?>

<div class="wrap" data-page="assignments">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Loading indicator -->
    <div id="ra-loading" class="ra-loading" style="display: none;">
        <div class="ra-loading-spinner"></div>
        <span><?php _e('Laddar...', 'reading-assessment'); ?></span>
    </div>

    <?php if (isset($this->messages['error'])): ?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo esc_html($this->messages['error']); ?></p>
    </div>
    <?php endif; ?>

    <?php if (isset($this->messages['success'])): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php echo esc_html($this->messages['success']); ?></p>
    </div>
    <?php endif; ?>



    <!-- Assignment Form -->
    <div class="ra-assignment-form-container">
        <h2><?php _e('Tilldela text', 'reading-assessment'); ?></h2>
        <form method="post" id="ra-assignment-form">
            <input type="hidden" id="ra_admin_nonce"
                value="<?php echo esc_attr(wp_create_nonce('ra_admin_action')); ?>">
            <?php wp_nonce_field('ra_admin_action'); // This will create _wpnonce field ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="user_id"><?php _e('Användare', 'reading-assessment'); ?></label>
                    </th>
                    <td>
                        <select name="user_id" id="user_id" required>
                            <option value=""><?php _e('Välj användare', 'reading-assessment'); ?></option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>">
                                <?php echo esc_html($user->display_name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="passage_id"><?php _e('Text', 'reading-assessment'); ?></label>
                    </th>
                    <td>
                        <select name="passage_id" id="passage_id" required>
                            <option value=""><?php _e('Välj text', 'reading-assessment'); ?></option>
                            <?php foreach ($passages as $passage): ?>
                            <option value="<?php echo esc_attr($passage->id); ?>">
                                <?php echo esc_html($passage->title); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="due_date"><?php _e('Slutdatum (valfritt)', 'reading-assessment'); ?></label>
                    </th>
                    <td>
                        <input type="date" id="due_date" name="due_date">
                        <p class="description">
                            <?php _e('Lämna tomt om inget slutdatum behövs.', 'reading-assessment'); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary"
                    value="<?php _e('Tilldela text', 'reading-assessment'); ?>">
            </p>
        </form>
    </div>

    <!-- List of current assignments -->
    <div class="ra-assignments-list">
        <h2><?php _e('Aktuella tilldelningar', 'reading-assessment'); ?></h2>

        <?php if ($assignments): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Användare', 'reading-assessment'); ?></th>
                    <th><?php _e('Text', 'reading-assessment'); ?></th>
                    <th><?php _e('Tilldelad', 'reading-assessment'); ?></th>
                    <th><?php _e('Slutdatum', 'reading-assessment'); ?></th>
                    <th><?php _e('Status', 'reading-assessment'); ?></th>
                    <th><?php _e('Aktivitet', 'reading-assessment'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $assignment): ?>
                <tr>
                    <td><?php echo esc_html($assignment->user_name); ?></td>
                    <td><?php echo esc_html($assignment->passage_title); ?></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($assignment->assigned_at))); ?>
                    </td>
                    <td>
                        <?php
                        echo $assignment->due_date
                            ? esc_html(date_i18n(get_option('date_format'), strtotime($assignment->due_date)))
                            : __('Inget slutdatum', 'reading-assessment');
                        ?>
                    </td>
                    <td>
                        <?php
                        $status_labels = [
                            'pending' => __('Väntar', 'reading-assessment'),
                            'completed' => __('Slutförd', 'reading-assessment'),
                            'overdue' => __('Försenad', 'reading-assessment')
                        ];
                        echo esc_html($status_labels[$assignment->status] ?? $assignment->status);
                        ?>
                    </td>
                    <td>
                        <button type="button" class="button button-link-delete" data-action="delete"
                            data-id="<?php echo esc_attr($assignment->id); ?>"
                            data-confirm="<?php echo esc_attr__('Är du säker på att du vill ta bort denna tilldelning?', 'reading-assessment'); ?>">
                            <?php _e('Ta bort', 'reading-assessment'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><?php _e('Inga aktiva tilldelningar hittades.', 'reading-assessment'); ?></p>
        <?php endif; ?>
    </div>
</div>
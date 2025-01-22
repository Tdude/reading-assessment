<?php
// admin/partials/views/questions-admin-page.php

if (!defined('WPINC')) {
    die;
}
?>


<div class="wrap" data-page="questions">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <button type="button" class="button page-title-action" data-action="new-question">
        <?php _e('Lägg till ny fråga', 'reading-assessment'); ?>
    </button>

    <?php if (isset($this->messages['error'])): ?>
    <div class="notice notice-error">
        <p><?php echo esc_html($this->messages['error']); ?></p>
    </div>
    <?php endif; ?>

    <?php if (isset($this->messages['success'])): ?>
    <div class="notice notice-success">
        <p><?php echo esc_html($this->messages['success']); ?></p>
    </div>
    <?php endif; ?>

    <!-- Passage selection -->
    <div class="ra-passage-selector">
        <form method="get">
            <input type="hidden" name="page" value="reading-assessment-questions">
            <select name="passage_id" id="passage_id" data-action="select-passage">
                <?php foreach ($passages as $passage): ?>
                <option value="<?php echo esc_attr($passage->id); ?>"
                    <?php selected($selected_passage_id, $passage->id); ?>>
                    <?php echo esc_html($passage->title); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Questions list -->
    <div class="ra-questions-list">
        <h2><?php _e('Frågor för vald text', 'reading-assessment'); ?></h2>

        <?php if ($questions): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Fråga', 'reading-assessment'); ?></th>
                    <th><?php _e('Korrekt svar', 'reading-assessment'); ?></th>
                    <th><?php _e('Svårighetsgrad', 'reading-assessment'); ?></th>
                    <th><?php _e('Aktivitet', 'reading-assessment'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($questions as $question): ?>
                <tr>
                    <td><?php echo esc_html($question->question_text); ?></td>
                    <td><?php echo esc_html($question->correct_answer); ?></td>
                    <td><?php echo esc_html($question->weight); ?></td>
                    <td>
                        <div class="button-group">
                            <button type="button" class="button button-primary" style="width: 4rem;" data-action="edit"
                                data-id="<?php echo esc_attr($question->id); ?>">
                                <?php _e('Ändra', 'reading-assessment'); ?>
                            </button>
                            <button type="button" class="button button-link-delete" style="width: 4rem;"
                                data-action="delete" data-id="<?php echo esc_attr($question->id); ?>">
                                <?php _e('Radera', 'reading-assessment'); ?>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><?php _e('Hittade inga frågor på denna text.', 'reading-assessment'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Add/Edit question form -->
    <div class="ra-question-form-container">
        <h2 id="ra-form-title"><?php _e('Lägg till ny fråga', 'reading-assessment'); ?></h2>
        <form id="ra-question-form" method="post">
            <?php wp_nonce_field('ra_question_action', 'ra_question_nonce'); ?>
            <input type="hidden" name="question_id" id="question_id" value="">
            <input type="hidden" name="passage_id" value="<?php echo esc_attr($selected_passage_id); ?>">

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="question_text"><?php _e('Fråga', 'reading-assessment'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="question_text" name="question_text" class="large-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="correct_answer"><?php _e('Korrekt svar', 'reading-assessment'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="correct_answer" name="correct_answer" class="large-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="weight"><?php _e('Svårighetsgrad', 'reading-assessment'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="weight" name="weight" value="1" min="1" max="20" step="1" required>
                        <p class="description">
                            <?php _e('Svårighetsgrad 1-20 där 20 är svårast', 'reading-assessment'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary"
                    value="<?php _e('Spara fråga', 'reading-assessment'); ?>">
                <button type="button" id="ra-cancel-edit" class="button" style="display:none;">
                    <?php _e('Ångra', 'reading-assessment'); ?>
                </button>
            </p>
        </form>
    </div>
</div>
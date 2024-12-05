<?php
// ra-admin-questions.php
if (!defined('WPINC')) {
    die;
}

$ra_db = new Reading_Assessment_Database();
$passages = $ra_db->get_all_passages();

// Handle form submission for questions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ra_question_nonce'])) {
    if (!wp_verify_nonce($_POST['ra_question_nonce'], 'ra_question_action')) {
        wp_die(__('Security check failed', 'reading-assessment'));
    }

    $question_data = array(
        'passage_id' => intval($_POST['passage_id']),
        'question_text' => sanitize_text_field($_POST['question_text']),
        'correct_answer' => sanitize_text_field($_POST['correct_answer']),
        'weight' => floatval($_POST['weight'])
    );

    if (isset($_POST['question_id']) && !empty($_POST['question_id'])) {
        $result = $ra_db->update_question(intval($_POST['question_id']), $question_data);
    } else {
        $result = $ra_db->create_question($question_data);
    }

    if (is_wp_error($result)) {
        $error_message = $result->get_error_message();
    } else {
        $success_message = __('Frågan är sparad.', 'reading-assessment');
    }
}

// Get selected passage ID from query string or first passage
$selected_passage_id = isset($_GET['passage_id']) ? intval($_GET['passage_id']) : 
                      ($passages ? $passages[0]->id : 0);

// Get questions for selected passage
$questions = $selected_passage_id ? $ra_db->get_questions_for_passage($selected_passage_id) : array();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

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


    <!-- Passage selection -->
    <div class="ra-passage-selector">
        <form method="get">
            <input type="hidden" name="page" value="reading-assessment-questions">
            <select name="passage_id" id="passage_id" onchange="this.form.submit()">
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
                        <th><?php _e('Svårighetsgrad på fråga', 'reading-assessment'); ?></th>
                        <th><?php _e('Bearbetning', 'reading-assessment'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $question): ?>
                        <tr>
                            <td><?php echo esc_html($question->question_text); ?></td>
                            <td><?php echo esc_html($question->correct_answer); ?></td>
                            <td><?php echo esc_html($question->weight); ?></td>
                            <td>
                                <button class="button ra-edit-question" 
                                        data-id="<?php echo esc_attr($question->id); ?>"
                                        data-question="<?php echo esc_attr($question->question_text); ?>"
                                        data-answer="<?php echo esc_attr($question->correct_answer); ?>"
                                        data-weight="<?php echo esc_attr($question->weight); ?>">
                                    <?php _e('Ändra', 'reading-assessment'); ?>
                                </button>
                                <button class="button ra-delete-question" 
                                        data-id="<?php echo esc_attr($question->id); ?>">
                                    <?php _e('Radera', 'reading-assessment'); ?>
                                </button>
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
                        <input type="text" id="question_text" name="question_text" 
                               class="large-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="correct_answer"><?php _e('Korrekt svar', 'reading-assessment'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="correct_answer" name="correct_answer" 
                               class="large-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="weight"><?php _e('Svårighetsgrad', 'reading-assessment'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="weight" name="weight" value="1" 
                            min="1" max="20" step="1" required>
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

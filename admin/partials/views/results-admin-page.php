<?php
/**
 * admin/partials/views/results-admin-page.php
*/
if (!defined('WPINC')) {
    die;
}



error_log('Starting results-admin-page.php view');
error_log('Checking required variables:');
error_log('stats object exists: ' . (isset($stats) ? 'yes' : 'no'));
error_log('overall_stats exists: ' . (isset($overall_stats) ? 'yes' : 'no'));
error_log('passage_stats exists: ' . (isset($passage_stats) ? 'yes' : 'no'));
error_log('question_stats exists: ' . (isset($question_stats) ? 'yes' : 'no'));




$stats = new RA_Statistics();

// Get filter values
$passage_id = isset($_GET['passage_id']) ? intval($_GET['passage_id']) : 0;
$recording_id = isset($_GET['recording_id']) ? intval($_GET['recording_id']) : 0;
$date_range = isset($_GET['date_range']) ? intval($_GET['date_range']) : 30;

// Get evaluation data if recording_id is set
$evaluation = $recording_id ? $stats->get_evaluation_data($recording_id) : null;

// Calculate date range
$date_limit = $date_range > 0 ? date('Y-m-d', strtotime("-$date_range days")) : '';

// Get statistics
$overall_stats = $stats->get_filtered_statistics($date_limit, $passage_id);
$passage_stats = $stats->get_passage_statistics($date_limit);
$question_stats = $stats->get_question_statistics($date_limit, $passage_id);
?>

<div class="wrap" data-page="results">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Filters -->
    <div class="ra-results-container">
        <div class="ra-results-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="ra-results">
                <select name="passage_id">
                    <option value=""><?php _e('Alla texter', 'reading-assessment'); ?></option>
                    <?php foreach ($stats->get_all_passages() as $passage): ?>
                    <option value="<?php echo esc_attr($passage->id); ?>" <?php selected($passage_id, $passage->id); ?>>
                        <?php echo esc_html($passage->title); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select name="date_range">
                    <option value="7" <?php selected($date_range, 7); ?>>
                        <?php _e('Senast 7 dagarna', 'reading-assessment'); ?></option>
                    <option value="30" <?php selected($date_range, 30); ?>>
                        <?php _e('Senast 30 dagarna', 'reading-assessment'); ?></option>
                    <option value="90" <?php selected($date_range, 90); ?>>
                        <?php _e('Senast 90 dagarna', 'reading-assessment'); ?></option>
                    <option value="all" <?php selected($date_range, 'all'); ?>>
                        <?php _e('Sedan början', 'reading-assessment'); ?></option>
                </select>
                <?php submit_button(__('Filtrera', 'reading-assessment'), 'secondary', 'submit', false); ?>
                <p>Här kan man förstås ha veckonummer, välja mellan datum, termin etc</p>
            </form>
        </div>

        <!-- Overview Cards -->
        <div class="ra-stats-overview">
            <div class="ra-stat-card">
                <h3><?php _e('Antal inspelningar', 'reading-assessment'); ?></h3>
                <div class="stat-number"><?php echo esc_html($overall_stats['total_recordings']); ?></div>
            </div>
            <div class="ra-stat-card">
                <h3><?php _e('Antal (unika) elever', 'reading-assessment'); ?></h3>
                <div class="stat-number"><?php echo esc_html($overall_stats['unique_students']); ?></div>
            </div>
            <div class="ra-stat-card">
                <h3><?php _e('Medelresultat (LUSgrad)', 'reading-assessment'); ?></h3>
                <div class="stat-number">
                    <?php echo esc_html(number_format($overall_stats['avg_normalized_score'], 1)); ?>£</div>
            </div>
            <div class="ra-stat-card">
                <h3><?php _e('Frågor besvarade', 'reading-assessment'); ?></h3>
                <div class="stat-number"><?php echo esc_html($overall_stats['total_questions_answered']); ?></div>
            </div>
            <div class="ra-stat-card">
                <h3><?php _e('Antal rätt svar', 'reading-assessment'); ?></h3>
                <div class="stat-number">
                    <?php echo esc_html(number_format($overall_stats['correct_answer_rate'] ?? 0, 1)); ?>%</div>
            </div>
        </div>

        <!-- Passage Performance -->
        <div class="ra-stats-section">
            <h2><?php _e('Texternas resultat', 'reading-assessment'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th
                            title="<?php esc_attr_e('När det blir många texter, kan vi dela upp dem i kategorier eller grader så det blir lättare admin.', 'reading-assessment'); ?>">
                            <?php _e('Text', 'reading-assessment'); ?></th>
                        <th title="<?php esc_attr_e('Antal inspelningar för den texten.', 'reading-assessment'); ?>">
                            <?php _e('Inspelningar', 'reading-assessment'); ?></th>
                        <th
                            title="<?php esc_attr_e('Detta är ett heltal om endast en inspelning gjorts. Annars decimal. Vi kan avrunda automatiskt också...', 'reading-assessment'); ?>">
                            <?php _e('Medelresultat', 'reading-assessment'); ?></th>
                        <th
                            title="<?php esc_attr_e('Frågor och svar på texterna är inlagda av admin, specifika för varje text.', 'reading-assessment'); ?>">
                            <?php _e('Rätt svar', 'reading-assessment'); ?></th>
                        <th
                            title="<?php esc_attr_e('Vi behöver inte räkna tid för uppläsning men om vi gör det, syns det här.', 'reading-assessment'); ?>">
                            <?php _e('Tidsåtgång', 'reading-assessment'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($passage_stats as $passage): ?>
                    <tr>
                        <td><?php echo esc_html($passage['title']); ?></td>
                        <td><?php echo esc_html($passage['recording_count']); ?></td>
                        <td><?php echo esc_html(number_format($passage['avg_score'] ?? 0, 1)); ?> £</td>
                        <td><?php echo esc_html(number_format($passage['correct_answer_rate'] ?? 0, 1)); ?>%</td>
                        <td><?php echo esc_html(number_format($passage['avg_duration'], 1)); ?>s</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Question Analysis -->
        <div class="ra-stats-section">
            <h2><?php _e('Frågeanalys', 'reading-assessment'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th
                            title="<?php esc_attr_e('Frågor på texterna är inlagda av admin, specifika för varje text.', 'reading-assessment'); ?>">
                            <?php _e('Fråga', 'reading-assessment'); ?>
                        </th>
                        <th title="<?php esc_attr_e('Text från admingränssnittet.', 'reading-assessment'); ?>">
                            <?php _e('Text', 'reading-assessment'); ?>
                        </th>
                        <th title="<?php esc_attr_e('Rätt svar på frågan.', 'reading-assessment'); ?>">
                            <?php _e('Rätt svar', 'reading-assessment'); ?>
                        </th>
                        <th
                            title="<?php esc_attr_e('Vissa elever kanske vill läsa en text flera gånger.', 'reading-assessment'); ?>">
                            <?php _e('Antal inläsningar', 'reading-assessment'); ?>
                        </th>
                        <th
                            title="<?php esc_attr_e('Rätt svar på ett kort ord kan visa stora fel om man tex. tillåter att bara 80% av texten är rättstavad.', 'reading-assessment'); ?>">
                            <?php _e('Antal rätta svar', 'reading-assessment'); ?>
                        </th>
                        <th
                            title="<?php esc_attr_e('Visar spridning mellan lägsta och högsta värdet. Hög procent, i det här fallet, är mer likt.', 'reading-assessment'); ?>">
                            <?php _e('Likhet datapunkter', 'reading-assessment'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($question_stats as $question): ?>
                    <tr>
                        <td><?php echo esc_html($question['question_text']); ?></td>
                        <td><?php echo esc_html($question['passage_title']); ?></td>
                        <td><?php echo esc_html($question['correct_answer']); ?></td>
                        <td><?php echo esc_html($question['times_answered']); ?></td>
                        <td><?php echo esc_html(number_format($question['correct_rate'] ?? 0, 1)); ?>%</td>
                        <td><?php echo esc_html(number_format($question['avg_similarity'] ?? 0, 1)); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
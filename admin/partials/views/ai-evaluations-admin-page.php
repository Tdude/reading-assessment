<?php
/**
 * File:admin/partials/views/ai-evaluations-admin-page.php
 */
if (!defined('WPINC')) {
    die;
}
?>
<div class="wrap" data-page="ai-evaluations">
    <h1><?php _e('AI Utvärderingar', 'reading-assessment'); ?></h1>

    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url($this->get_tab_url('list')); ?>"
            class="nav-tab <?php echo $current_tab === 'list' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Översikt', 'reading-assessment'); ?>
        </a>
        <a href="<?php echo esc_url($this->get_tab_url('details')); ?>"
            class="nav-tab <?php echo $current_tab === 'details' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Detaljvy', 'reading-assessment'); ?>
        </a>
    </nav>

    <div class="ra-evaluations-content">
        <?php
        switch ($current_tab) {
            case 'details':
                $evaluation_id = isset($_GET['evaluation_id']) ? intval($_GET['evaluation_id']) : 0;
                $details = $this->get_evaluation_details($evaluation_id);

                if (!$details) {
                    echo '<div class="notice notice-warning"><p>' .
                         __('Välj en utvärdering att visa', 'reading-assessment') .
                         '</p></div>';
                    break;
                }

                $evaluation_data = json_decode($details['evaluation']->evaluation_data, true);
                ?>
        <div class="ra-evaluation-details">
            <!-- Audio playback if available -->
            <?php if ($details['recording']->audio_file_path): ?>
            <div class="postbox">
                <h2 class="hndle"><?php _e('Ljudinspelning', 'reading-assessment'); ?></h2>
                <div class="inside">
                    <div id="audio-container-<?php echo $details['recording']->id; ?>" class="ra-audio-container">
                        <button type="button" class="audio-lazy-button"
                            data-container="audio-container-<?php echo $details['recording']->id; ?>"
                            data-url="<?php echo esc_url(wp_upload_dir()['baseurl'] . $details['recording']->audio_file_path); ?>">
                            <span class="dashicons dashicons-controls-play"></span>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Text Comparison -->
            <div class="postbox">
                <h2 class="hndle"><?php _e('Text & Transkription', 'reading-assessment'); ?></h2>
                <div class="inside">
                    <div class="ra-text-comparison">
                        <div class="original-text">
                            <h3><?php _e('Original Text', 'reading-assessment'); ?></h3>
                            <div class="text-content">
                                <?php echo nl2br(esc_html($details['passage']->content)); ?>
                            </div>
                        </div>
                        <div class="transcription">
                            <h3><?php _e('Transkription', 'reading-assessment'); ?></h3>
                            <div class="text-content">
                                <?php echo nl2br(esc_html($details['recording']->transcription)); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Evaluation Metrics -->
            <div class="postbox">
                <h2 class="hndle"><?php _e('Huvudbedömning', 'reading-assessment'); ?></h2>
                <div class="inside">
                    <div class="ra-main-metrics">
                        <div class="ra-metric-large">
                            <label><?php _e('LUS Poäng (med AI)', 'reading-assessment'); ?></label>
                            <div class="ra-score-circle">
                                <?php echo round($details['evaluation']->lus_score, 1); ?>
                            </div>
                        </div>
                        <div class="ra-metric-large">
                            <label><?php _e('Konfidensnivå', 'reading-assessment'); ?></label>
                            <div class="ra-score-circle <?php
                                        echo $details['evaluation']->confidence_score >= 0.9 ? 'high' :
                                            ($details['evaluation']->confidence_score >= 0.7 ? 'medium' : 'low');
                                        ?>">
                                <?php echo round($details['evaluation']->confidence_score); ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Metrics -->
            <div class="postbox">
                <h2 class="hndle"><?php _e('Detaljerad Analys', 'reading-assessment'); ?></h2>
                <div class="inside">
                    <?php
                            $metrics = [
                                'precision' => [
                                    'label' => __('Precision', 'reading-assessment'),
                                    'description' => __('Korrekt lästa ord, felläsningar och självrättningar', 'reading-assessment')
                                ],
                                'fluency' => [
                                    'label' => __('Flyt', 'reading-assessment'),
                                    'description' => __('Frasering, pauser och läsrytm', 'reading-assessment')
                                ],
                                'pronunciation' => [
                                    'label' => __('Uttal', 'reading-assessment'),
                                    'description' => __('Fonetisk precision och konsekvens', 'reading-assessment')
                                ],
                                'speed' => [
                                    'label' => __('Läshastighet', 'reading-assessment'),
                                    'description' => __('Ord per minut och tempo', 'reading-assessment')
                                ],
                                'comprehension' => [
                                    'label' => __('Förståelse', 'reading-assessment'),
                                    'description' => __('Betoning och anpassning till innehåll', 'reading-assessment')
                                ]
                            ];

                            foreach ($metrics as $key => $metric) {
                                if (isset($evaluation_data[$key])) {
                                    ?>
                    <div class="ra-detailed-metric">
                        <div class="ra-metric-header">
                            <h3><?php echo esc_html($metric['label']); ?></h3>
                            <p class="description"><?php echo esc_html($metric['description']); ?></p>
                        </div>

                        <div class="ra-metric-content">
                            <div class="ra-score-bar">
                                <div class="ra-score-fill" style="width: <?php
                                                    echo esc_attr($evaluation_data[$key]);
                                                ?>%">
                                    <span class="ra-score-value">
                                        <?php echo round($evaluation_data[$key], 1); ?>%
                                    </span>
                                </div>
                            </div>

                            <?php if (isset($evaluation_data[$key . '_details'])): ?>
                            <div class="ra-metric-details">
                                <h4><?php _e('Observationer', 'reading-assessment'); ?></h4>
                                <ul>
                                    <?php
                                                        foreach ($evaluation_data[$key . '_details'] as $detail) {
                                                            echo '<li>' . esc_html($detail) . '</li>';
                                                        }
                                                        ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                                }
                            }
                            ?>
                </div>
            </div>

            <!-- Statistics -->
            <?php if (isset($evaluation_data['statistics'])): ?>
            <div class="postbox">
                <h2 class="hndle"><?php _e('Statistik', 'reading-assessment'); ?></h2>
                <div class="inside">
                    <div class="ra-statistics">
                        <?php foreach ($evaluation_data['statistics'] as $key => $value): ?>
                        <div class="ra-stat-item">
                            <label><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?></label>
                            <span><?php echo esc_html($value); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
            break;

            default: // List view
                ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Datum', 'reading-assessment'); ?></th>
                    <th><?php _e('Elev', 'reading-assessment'); ?></th>
                    <th><?php _e('Text', 'reading-assessment'); ?></th>
                    <th><?php _e('Transkription', 'reading-assessment'); ?></th>
                    <th><?php _e('LUS Poäng', 'reading-assessment'); ?></th>
                    <th><?php _e('AI Precision', 'reading-assessment'); ?></th>
                    <th><?php _e('Konfidens', 'reading-assessment'); ?></th>
                    <th><?php _e('Åtgärder', 'reading-assessment'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($evaluations as $evaluation):
                        $user = get_userdata($evaluation->user_id);
                        $eval_data = json_decode($evaluation->evaluation_data, true);
                        ?>
                <tr>
                    <td><?php echo date_i18n(get_option('date_format'), strtotime($evaluation->created_at)); ?></td>
                    <td><?php echo $user ? esc_html($user->display_name) : '-'; ?></td>
                    <td><?php echo esc_html($evaluation->passage_title); ?></td>
                    <td>
                        <button type="button" class="button show-transcription"
                            data-transcription="<?php echo esc_attr($evaluation->transcription); ?>">
                            <?php _e('Visa', 'reading-assessment'); ?>
                        </button>
                    </td>
                    <td>
                        <span class="lus-score">
                            <?php echo isset($evaluation->lus_score) ? round($evaluation->lus_score, 1) : '-'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="score-bar">
                            <div class="score-fill" style="width: <?php
                                        echo isset($eval_data['accuracy']) ? esc_attr($eval_data['accuracy']) : '0';
                                    ?>%">
                                <span class="score-text">
                                    <?php echo isset($eval_data['accuracy']) ?
                                                round($eval_data['accuracy'], 1) . '%' : '-'; ?>
                                </span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="confidence-indicator <?php
                            echo $evaluation->confidence_score >= 90 ? 'high' :
                                ($evaluation->confidence_score >= 70 ? 'medium' : 'low');
                            ?>">
                            <?php echo round($evaluation->confidence_score); ?>%
                        </span>
                    </td>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg([
                                    'page' => 'reading-assessment-ai-evaluations',
                                    'tab' => 'details',
                                    'evaluation_id' => $evaluation->id
                                ])); ?>" class="button"><?php _e('Visa detaljer', 'reading-assessment'); ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Transcription Modal -->
        <div id="transcription-modal" class="ra-modal">
            <div class="ra-modal-content">
                <span class="ra-modal-close">&times;</span>
                <h2><?php _e('Transkription', 'reading-assessment'); ?></h2>
                <div class="transcription-text"></div>
            </div>
        </div>
        <?php
                break;
        }
        ?>
    </div>
</div>
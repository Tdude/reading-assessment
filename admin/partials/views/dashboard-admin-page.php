<?php
/**
 * admin/partials/views/dashboard-admin-page.php
 * Dashboard view template
 * Contains the recording list, statistics, and assessment functionality
 */

if (!defined('WPINC')) {
    die;
}

// Security check
if (!current_user_can('edit_posts')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$upload_dir = wp_upload_dir();
?>

<div class="wrap">
    <button type="button" id="toggle-instructions" class="button button-secondary">
        <?php _e('Visa/dölj instruktioner', 'reading-assessment'); ?>
    </button>

    <div id="instructions-content" class="instructions-content">
        <div class="two-cols">
            <div>
                <h2><?php _e('Vad detta är och hur det kan hjälpa dig', 'reading-assessment'); ?></h2>
                <p><?php _e('Om du klickar på knappen Visa/dölj ska sidan komma ihåg hur du vill ha det.', 'reading-assessment'); ?>
                </p>
                <p><?php _e('Detta Wordpress-plugin är gjort för att spela in ljudfiler med. Sedan ska vi använda ljudfilerna för att träna en AI-modell med LUS-grader (bättre namn?). Adminfunktionerna här är inte cementerade utan freestyleprogrammerade utifrån lösa antaganden.', 'reading-assessment'); ?>
                </p>
                <p><?php _e('Klicka gärna runt här. Har du sönder något är det utvecklarens fel och inte ditt! Om du saknar funktionalitet eller tycker det är för rörigt, meddela gärna så fixar vi det.', 'reading-assessment'); ?>
                </p>
                <p><?php _e('Vi kommer inom kort att träna ett AI för att LUSa automatiskt. Först behöver vi dock ljudfiler med barn som spelar in texter.', 'reading-assessment'); ?>
                </p>
            </div>
            <div>
                <h2><?php _e('Hur man gör och sånt', 'reading-assessment'); ?></h2>
                <p><?php _e('Administratören, dvs. du, skapar texter att läsa in för elever i olika grader. Det behövs flera texter i varje poängsegment. Hur många, det är beroende av vad proffsen säger. När vi har tillräckligt med texter, kan vi bjuda in elever och andra att läsa texterna. Gradera dem gärna för vår egen skull så vi inte tilldelar fel svårighetsgrad. Texter kan ju ha samma titel.', 'reading-assessment'); ?>
                </p>
                <p><?php _e('Användaren/eleven behöver få ett login som du som admin skapar åt dem. Sedan loggar de in på sidan "Inspelningsmodul". Där läser de och spelar in texterna i vilken ordning som helst men se till att ni följer någon slags LUS-standard utan distraktioner. När en elev spelat in en text, kan du som admin gå in och LUSa den.', 'reading-assessment'); ?>
                </p>
                <pre>Kortkodde som man säger på Skånska: [reading_assessment]</pre>
            </div>
        </div>
    </div>
</div>

<div class="wrap" data-page="dashboard">
    <h1>
        <?php
        echo esc_html(get_admin_page_title());
        if ($passage_filter && $passage_title) {
            echo ' - ' . sprintf(
                __('Inspelningar för "%s"', 'reading-assessment'),
                esc_html($passage_title)
            );
        }
        ?>
    </h1>

    <?php if ($passage_filter): ?>
    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=reading-assessment')); ?>" class="button">
            <?php _e('Visa alla inspelningar', 'reading-assessment'); ?>
        </a>
    </p>
    <?php endif; ?>

    <div class="ra-dashboard-widgets">
        <div class="ra-widget">
            <h2><?php _e('LUSa inspelningarna här', 'reading-assessment'); ?></h2>
            <div class="ra-widget-content">
                <?php if ($recent_recordings): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="12%"><?php _e('Användare', 'reading-assessment'); ?></th>
                            <th width="10%"><?php _e('Inspelning', 'reading-assessment'); ?></th>
                            <th width="20%"><?php _e('Titel', 'reading-assessment'); ?></th>
                            <th width="10%"><?php _e('Längd', 'reading-assessment'); ?></th>
                            <th width="18%"><?php _e('Datum', 'reading-assessment'); ?></th>
                            <th width="30%"><?php _e('Bedömningar', 'reading-assessment'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_recordings as $recording): ?>
                        <tr>
                            <td><?php echo esc_html($recording->display_name); ?></td>
                            <td>
                                <?php
                                $file_path = $upload_dir['baseurl'] . $recording->audio_file_path;
                                $check_path = $upload_dir['basedir'] . $recording->audio_file_path;
                                if (file_exists($check_path)):
                                    $container_id = 'audio-container-' . $recording->id;
                                ?>
                                <div id="<?php echo esc_attr($container_id); ?>" class="ra-audio-container">
                                    <button type="button" class="audio-lazy-button"
                                        onclick="RAUtils.handleAudioLazyLoad('<?php echo esc_attr($container_id); ?>', '<?php echo esc_url($file_path); ?>')">
                                        <span class="dashicons dashicons-controls-play"></span>
                                    </button>
                                </div>
                                <?php else: ?>
                                <span class="error"><?php _e('Ljudfil saknas', 'reading-assessment'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($recording->passage_title ? wp_trim_words($recording->passage_title, 7, '...') : __('N/A', 'reading-assessment')); ?></td>
                            <td><?php echo esc_html($recording->duration ? round($recording->duration, 1) . ' sek' : 'N/A'); ?>
                            </td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($recording->created_at))); ?>
                            </td>
                            <td class="button-container">
                                <div class="recording-actions">

                                    <?php
                                    echo sprintf(
                                        _n('%d bedömning', '%d bedömningar', $recording->assessment_count, 'reading-assessment'),
                                        $recording->assessment_count
                                    );
                                    if ($recording->assessment_count > 0) {
                                        echo ' (' . round($recording->avg_assessment_score, 1) . ')';
                                    }
                                    ?>
                                    <div class="button-group wp-ra-button-group">
                                        <?php if (get_option('ra_enable_ai_evaluation', true)): ?>
                                        <button class="button button-secondary ai-evaluate-btn"
                                            data-recording-id="<?php echo esc_attr($recording->id); ?>">
                                            <?php _e('AI-bedömning', 'reading-assessment'); ?>
                                        </button>
                                        <?php endif; ?>
                                        <button type="button" class="button button-primary" data-action="evaluate"
                                            data-id="<?php echo esc_attr($recording->id); ?>">
                                            <?php _e('LUSa', 'reading-assessment'); ?>
                                        </button>
                                        <button type="button" class="button button-link-delete" data-action="delete"
                                            data-id="<?php echo esc_attr($recording->id); ?>">
                                            <?php _e('Radera', 'reading-assessment'); ?>
                                        </button>
                                    </div>

                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(
                                _n('%s inspelning', '%s inspelningar', $total_count, 'reading-assessment'),
                                number_format_i18n($total_count)
                            ); ?>
                        </span>
                        <span class="pagination-links">
                            <?php if ($current_page > 1): ?>
                            <a class="first-page button" href="<?php echo esc_url(add_query_arg('paged', 1)); ?>">
                                <span>«</span>
                            </a>
                            <a class="prev-page button"
                                href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>">
                                <span>‹</span>
                            </a>
                            <?php endif; ?>

                            <span class="paging-input">
                                <?php printf(
                                    '%s av %s',
                                    $current_page,
                                    $total_pages
                                ); ?>
                            </span>

                            <?php if ($current_page < $total_pages): ?>
                            <a class="next-page button"
                                href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>">
                                <span>›</span>
                            </a>
                            <a class="last-page button"
                                href="<?php echo esc_url(add_query_arg('paged', $total_pages)); ?>">
                                <span>»</span>
                            </a>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <p><?php _e('Inga inspelningar registrerade än.', 'reading-assessment'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics Widget -->
        <div class="ra-widget">
            <h2><?php _e('Allmänt', 'reading-assessment'); ?></h2>
            <div class="ra-widget-content">
                <div class="stats-container">
                    <svg viewBox="0 0 120 120" class="stats-pie">
                        <?php
                        // Calculate percentages for pie chart
                        $total = $stats->total_recordings + $stats->unique_users + $stats->total_assessments;
                        if ($total > 0):
                            $angles = array(
                                'recordings' => ($stats->total_recordings / $total) * 360,
                                'users' => ($stats->unique_users / $total) * 360,
                                'assessments' => ($stats->total_assessments / $total) * 360
                            );

                            // Generate colors for the number of segments we have
                            $utils = RA_Utilities::get_instance();
                            $colors = $utils->generate_colors(count($angles), 60, 65);

                            $start_angle = 0;
                            $i = 0;
                            $radius = 50;
                            $center = 60;

                            foreach ($angles as $angle):
                                $end_angle = $start_angle + $angle;
                                $start_rad = deg2rad($start_angle);
                                $end_rad = deg2rad($end_angle);

                                $start_x = $center + ($radius * cos($start_rad));
                                $start_y = $center + ($radius * sin($start_rad));
                                $end_x = $center + ($radius * cos($end_rad));
                                $end_y = $center + ($radius * sin($end_rad));

                                $large_arc = ($angle > 180) ? 1 : 0;
                                ?>
                        <path d="M <?php echo $start_x; ?> <?php echo $start_y; ?>
                                        A <?php echo $radius; ?> <?php echo $radius; ?> 0 <?php echo $large_arc; ?> 1
                                        <?php echo $end_x; ?> <?php echo $end_y; ?>
                                        L <?php echo $center; ?> <?php echo $center; ?> Z"
                            fill="<?php echo $colors[$i]; ?>" />
                        <?php
                                $start_angle = $end_angle;
                                $i++;
                            endforeach;
                        endif;
                        ?>
                    </svg>

                    <ul class="ra-stats-list">
                        <?php
                        $i = 0;
                        $stats_items = array(
                            'recordings' => array(
                                'label' => __('Sparade inläsningar', 'reading-assessment'),
                                'value' => $stats->total_recordings
                            ),
                            'users' => array(
                                'label' => __('Unika användare', 'reading-assessment'),
                                'value' => $stats->unique_users
                            ),
                            'assessments' => array(
                                'label' => __('Slutförda bedömningar', 'reading-assessment'),
                                'value' => $stats->total_assessments
                            )
                        );

                        foreach ($stats_items as $key => $item): ?>
                        <li>
                            <span class="color-dot" style="background-color: <?php echo $colors[$i]; ?>"></span>
                            <strong><?php echo esc_html($item['label']); ?>:</strong>
                            <?php echo esc_html($item['value']); ?>
                        </li>
                        <?php
                            $i++;
                        endforeach; ?>
                        <li>
                            <strong><?php _e('Total inspelningstid', 'reading-assessment'); ?>:</strong>
                            <?php echo esc_html($stats->total_duration ? round($stats->total_duration / 60, 1) : 0); ?>
                            minuter
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Text Passages Overview -->
            <h2><?php _e('Textöversikt', 'reading-assessment'); ?></h2>
            <div class="ra-widget-content">
                <div class="stats-container">
                    <svg viewBox="0 0 120 120" class="stats-pie">
                        <?php
                        $recordings_per_passage = $dashboard_data['recordings_per_passage'];
                        $total_recordings = array_sum(array_column($recordings_per_passage, 'recording_count'));

                        if ($total_recordings > 0):
                            // Generate colors for the number of passages
                            $utils = RA_Utilities::get_instance();
                            $colors = $utils->generate_colors(count($recordings_per_passage), 60, 65);
                            $start_angle = 0;

                            foreach ($recordings_per_passage as $index => $passage):
                                $percentage = ($passage->recording_count / $total_recordings) * 360;
                                $end_angle = $start_angle + $percentage;

                                $start_rad = deg2rad($start_angle);
                                $end_rad = deg2rad($end_angle);
                                $radius = 50;
                                $center = 60;

                                $start_x = $center + ($radius * cos($start_rad));
                                $start_y = $center + ($radius * sin($start_rad));
                                $end_x = $center + ($radius * cos($end_rad));
                                $end_y = $center + ($radius * sin($end_rad));

                                $large_arc = ($percentage > 180) ? 1 : 0;
                                ?>
                        <path d="M <?php echo $start_x; ?> <?php echo $start_y; ?>
                                    A <?php echo $radius; ?> <?php echo $radius; ?> 0 <?php echo $large_arc; ?> 1
                                    <?php echo $end_x; ?> <?php echo $end_y; ?>
                                    L <?php echo $center; ?> <?php echo $center; ?> Z"
                            fill="<?php echo $colors[$index]; ?>" />
                        <?php
                                $start_angle = $end_angle;
                            endforeach;
                        endif;
                        ?>
                    </svg>

                    <ul class="ra-stats-list">
                        <?php
                        foreach ($recordings_per_passage as $index => $passage):
                            // Calculate percentage for this passage
                            $percentage = ($passage->recording_count / $total_recordings) * 100;
                        ?>
                        <li>
                            <span class="color-dot" style="background-color: <?php echo $colors[$index]; ?>"></span>
                            <strong><?php echo esc_html($passage->title); ?>:</strong>
                            <?php echo esc_html($passage->recording_count); ?>
                            <?php _e('inspelningar', 'reading-assessment'); ?>
                            (<?php echo round($percentage, 1); ?>%)
                            <?php if ($passage->avg_grade): ?>
                            <span class="grade-info">
                                <?php echo number_format($passage->avg_grade, 1); ?>
                                <?php _e('medel', 'reading-assessment'); ?>
                            </span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Recordings per Passage -->
            <h2><?php _e('Inspelningar per text', 'reading-assessment'); ?></h2>
            <div class="ra-widget-content">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Text', 'reading-assessment'); ?></th>
                            <th><?php _e('Antal inspelningar', 'reading-assessment'); ?></th>
                            <th><?php _e('Unika användare', 'reading-assessment'); ?></th>
                            <th><?php _e('Genomsnittlig bedömning', 'reading-assessment'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard_data['recordings_per_passage'] as $passage): ?>
                        <tr>
                            <td><?php echo esc_html($passage->title); ?></td>
                            <td><?php echo esc_html($passage->recording_count); ?></td>
                            <td><?php echo esc_html($passage->unique_users); ?></td>
                            <td>
                                <?php
                                if ($passage->avg_grade) {
                                    echo number_format($passage->avg_grade, 1);
                                } else {
                                    _e('Ej bedömd', 'reading-assessment');
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- User Performance -->
            <h2><?php _e('Bedömning per användare', 'reading-assessment'); ?></h2>
            <div class="ra-widget-content">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Användare', 'reading-assessment'); ?></th>
                            <th><?php _e('Antal inspelningar', 'reading-assessment'); ?></th>
                            <th><?php _e('Genomsnittlig bedömning', 'reading-assessment'); ?></th>
                            <th><?php _e('Min/Max bedömning', 'reading-assessment'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard_data['user_stats'] as $user): ?>
                        <tr>
                            <td><?php echo esc_html($user->display_name); ?></td>
                            <td><?php echo esc_html($user->recording_count); ?></td>
                            <td>
                                <?php
                                if ($user->avg_grade) {
                                    echo number_format($user->avg_grade, 1);
                                } else {
                                    _e('Ej bedömd', 'reading-assessment');
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if ($user->min_grade && $user->max_grade) {
                                    echo number_format($user->min_grade, 1) . ' / ' . number_format($user->max_grade, 1);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="ra-dashboard-widgets">
        <div class="ra-widget">
            <!-- Progress Over Time -->
            <h2><?php _e('Utveckling över tid', 'reading-assessment'); ?></h2>
            <div class="ra-widget-content">
                <!-- Period selector -->
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select id="progress-period" class="postform">
                            <option value="week"><?php _e('Veckovis', 'reading-assessment'); ?></option>
                            <option value="month" selected><?php _e('Månadsvis', 'reading-assessment'); ?></option>
                            <option value="year"><?php _e('Årsvis', 'reading-assessment'); ?></option>
                        </select>
                        <?php
                // Get all users who have recordings
                $users_with_recordings = $this->db->get_users_with_recordings();
                if ($users_with_recordings): ?>
                        <select id="progress-user" class="postform">
                            <option value=""><?php _e('Hela klassen', 'reading-assessment'); ?></option>
                            <?php foreach ($users_with_recordings as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>">
                                <?php echo esc_html($user->display_name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        <!--
                        <button type="button" class="button" id="update-progress" style="margin: 1px 8px 0 0;">
                            <?php _e('Uppdatera', 'reading-assessment'); ?>
                        </button>
                        -->
                    </div>
                </div>

                <!-- Progress graph -->
                <div class="progress-chart-container" style="height: 400px; position: relative; margin-bottom: 20px;">
                    <canvas id="progressChart"></canvas>
                </div>

                <!-- Progress table -->
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Period', 'reading-assessment'); ?></th>
                            <th><?php _e('Antal inspelningar', 'reading-assessment'); ?></th>
                            <th><?php _e('Genomsnittlig bedömning', 'reading-assessment'); ?></th>
                            <th><?php _e('Min/Max bedömning', 'reading-assessment'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $progress_data = $dashboard_data['class_progress'];
                        foreach ($progress_data as $period):
                        ?>
                        <tr>
                            <td><?php echo esc_html($period->period_label); ?></td>
                            <td><?php echo esc_html($period->recording_count); ?></td>
                            <td>
                                <?php
                    if ($period->avg_grade) {
                        echo number_format($period->avg_grade, 1);
                    } else {
                        _e('Ej bedömd', 'reading-assessment');
                    }
                    ?>
                            </td>
                            <td>
                                <?php
                    if ($period->min_grade && $period->max_grade) {
                        echo number_format($period->min_grade, 1) . ' / ' .
                                number_format($period->max_grade, 1);
                    } else {
                        echo '-';
                    }
                    ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>


    <!-- Assessment Modal -->
    <div id="assessment-modal" class="ra-modal" style="display: none;">
        <div class="ra-modal-content">
            <span class="ra-modal-close">&times;</span>
            <h3><?php _e('Lägg till bedömning', 'reading-assessment'); ?></h3>

            <!-- AI Evaluation results in assessments modal -->
            <div id="assessment-ai-results" class="ai-evaluation-section">
                <div class="ai-loading" style="display: none;">
                    <?php _e('AI analyserar inspelningen...', 'reading-assessment'); ?>
                </div>
                <div class="ai-results" style="display: none;">
                    <div class="ai-score"></div>
                    <div class="ai-confidence"></div>
                    <div class="ai-details"></div>
                </div>
            </div>

            <form id="assessment-form">
                <?php wp_nonce_field('ra_admin_action'); ?>
                <input type="hidden" name="recording_id" id="assessment-recording-id">
                <input type="hidden" name="ai_score" id="ai-score-input">
                <div class="form-field">
                    <label for="assessment-score"><?php _e('Din bedömning (1-20)', 'reading-assessment'); ?></label>
                    <input type="number" id="assessment-score" name="score" min="1" max="20" required>
                    <p class="description">
                        <?php _e('Ange poäng mellan 1 och 20', 'reading-assessment'); ?>
                    </p>
                </div>
                <button type="submit" class="button button-primary">
                    <?php _e('Spara bedömning', 'reading-assessment'); ?>
                </button>
            </form>
        </div>
    </div>

    <!-- AI Evaluation in freestanding modal -->
    <div id="ai-evaluation-modal" class="ra-modal" style="display: none;">
        <div class="ra-modal-content">
            <span class="ra-modal-close">×</span>
            <div class="ai-eval-header">
                <h2>AI Utvärdering</h2>
            </div>
            <div id="ai-evaluation-results"></div>
            <div class="ai-eval-details"></div>
        </div>
    </div>
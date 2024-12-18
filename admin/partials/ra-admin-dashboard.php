<?php
// ra-admin-dashboard.php
if (!defined('WPINC')) {
    die;
}

$upload_dir = wp_upload_dir();

/**
 * Renders texxt for settings page in Klingon.
 */

$external_url = 'https://www.github.com/Tdude';

// Create the URL and link text separately for better translation support. @TODO: make prettier.
$default_page_link = sprintf(
    '<a href="%s" target="_blank">%s</a>',
    esc_url(site_url('/inspelningsmodul')),
    esc_html__('created here', 'reading-assessment')
);

echo '<div class="wrap">';
    echo '<button type="button" id="toggle-instructions" class="button button-secondary">';
    echo esc_html__('Visa/dölj instruktioner', 'reading-assessment');
    echo '</button>';

    echo '<div id="instructions-content" class="instructions-content">';
    echo '<div class="two-cols">';

    // Left column
    echo '<div>';
    echo '<h2>' . esc_html__('Här är en text som förklarar saker', 'reading-assessment') . '</h2>';
    echo '<p>' . esc_html__('Om du klickar på knappen Visa/dölj ska sidan komma ihåg hur du vill ha det. Mitt bidrag till UX-världen.', 'reading-assessment') . '</p>';
    echo '<p>' . esc_html__('Mer text och mer o mer', 'reading-assessment') . '</p>';
    echo '<p>' . esc_html__('Blajar på dårå och så vidare i ny pargraf. Här är min <a href="' . $external_url . '">Länk till Github</a>', 'reading-assessment') . '</p>';
    echo '</div>';

    // Right column
    echo '<div>';
    echo '<h2>' . esc_html__('Hur man gör och sånt', 'reading-assessment') . '</h2>';
    echo '<p>' . esc_html__('Du kan visa både det ena och det andra härna.', 'reading-assessment') . '</p>';
    echo '<pre>Kortkodde som man säger på Skånska: [reading_assessment]</pre>';

    // Method 1: Using sprintf for complete sentence translation
    echo '<p>' . sprintf(
        /* translators: %s: URL link */
        esc_html__('Det finns mer här.', 'reading-assessment'),
        $default_page_link
    ) . '</p>';

    // Method 2: Using wp_kses with the external URL
    $external_link = sprintf(
        '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
        esc_url($external_url),
        esc_html__('RUGD.se', 'reading-assessment')
    );

    echo '<p>' . sprintf(
        /* translators: %s: URL link */
        esc_html__('Over at my blog %s you can read more about something else.', 'reading-assessment'),
        $external_link
    ) . '</p>';

    echo '</div>';
    echo '</div>'; // .two-cols
    echo '</div>'; // #instructions-content
echo '</div>'; // .wrap


$passage_filter = isset($_GET['passage_filter']) ? intval($_GET['passage_filter']) : 0;
// Get passage info if filtered
$passage_title = '';
if ($passage_filter) {
    $ra_db = new Reading_Assessment_Database();
    $passage = $ra_db->get_passage($passage_filter);
    if ($passage) {
        $passage_title = $passage->title;
    }
}


?>
<div class="wrap">
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
            <a href="<?php echo esc_url(admin_url('admin.php?page=reading-assessment')); ?>"
            class="button">
                <?php _e('Visa alla inspelningar', 'reading-assessment'); ?>
            </a>
        </p>
    <?php endif; ?>

    <div class="ra-dashboard-widgets">

        <div class="ra-widget">
            <h2><?php _e('Senaste inspelningarna', 'reading-assessment'); ?></h2>
            <div class="ra-widget-content">
                <?php
                // Pagination variables
                $per_page = 20;
                $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                $offset = ($current_page - 1) * $per_page;

                // Get total count (with caching)
                $total_count = wp_cache_get('ra_recordings_count');
                if (false === $total_count) {
                    global $wpdb;
                    $total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ra_recordings");
                    wp_cache_set('ra_recordings_count', $total_count, '', 300); // Cache for 5 minutes
                }

                $total_pages = ceil($total_count / $per_page);

                // Get recordings for current page (with caching)
                $cache_key = 'ra_recordings_page_' . $current_page;
                $recent_recordings = wp_cache_get($cache_key);
                $where_conditions = array('1=1');
                $where_args = array();

                if ($passage_filter) {
                    $where_conditions[] = 'r.passage_id = %d';
                    $where_args[] = $passage_filter;
                }

                $where_clause = ' WHERE ' . implode(' AND ', $where_conditions);

                $recent_recordings = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT r.*, u.display_name, u.ID as user_id,
                                r.audio_file_path, r.duration,
                                DATE_FORMAT(r.created_at, '%Y/%m') as date_path,
                                COUNT(a.id) as assessment_count,
                                AVG(a.normalized_score) as avg_assessment_score
                        FROM {$wpdb->prefix}ra_recordings r
                        JOIN {$wpdb->users} u ON r.user_id = u.ID
                        LEFT JOIN {$wpdb->prefix}ra_assessments a ON r.id = a.recording_id
                        {$where_clause}
                        GROUP BY r.id
                        ORDER BY r.created_at DESC
                        LIMIT %d OFFSET %d",
                        array_merge($where_args, array($per_page, $offset))
                    )
                );

                if ($recent_recordings): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Användare', 'reading-assessment'); ?></th>
                                <th><?php _e('Inspelning', 'reading-assessment'); ?></th>
                                <th><?php _e('Längd', 'reading-assessment'); ?></th>
                                <th><?php _e('Datum', 'reading-assessment'); ?></th>
                                <th><?php _e('Bedömningar', 'reading-assessment'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_recordings as $recording):
                                $file_path = $upload_dir['baseurl'] . $recording->audio_file_path;
                                $check_path = $upload_dir['basedir'] . $recording->audio_file_path;
                            ?>
                                <tr>
                                    <td><?php echo esc_html($recording->display_name); ?></td>
                                    <td>
                                        <?php if (file_exists($check_path)): ?>
                                            <audio controls style="max-width: 250px;">
                                                <source src="<?php echo esc_url($file_path); ?>" type="audio/webm">
                                                <?php _e('Din webbläsare stöder inte ljuduppspelning.', 'reading-assessment'); ?>
                                            </audio>
                                        <?php else: ?>
                                            <span class="error"><?php _e('Ljudfil saknas', 'reading-assessment'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($recording->duration ? round($recording->duration, 1) . ' sek' : 'N/A'); ?></td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($recording->created_at))); ?></td>
                                    <td>
                                        <?php
                                            echo sprintf(
                                                _n('%d bedömning', '%d bedömningar', $recording->assessment_count, 'reading-assessment'),
                                                $recording->assessment_count
                                            );
                                            if ($recording->assessment_count > 0) {
                                                echo ' (' . round($recording->avg_assessment_score, 1) . ')';
                                            }
                                        ?>
                                        <div class="button-container">
                                            <button class="button add-assessment" data-recording-id="<?php echo esc_attr($recording->id); ?>">
                                                <?php _e('LUSa', 'reading-assessment'); ?>
                                            </button>
                                            <button class="button delete-recording" data-recording-id="<?php echo esc_attr($recording->id); ?>">
                                                <?php _e('Radera', 'reading-assessment'); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>


                    <div id="assessment-modal" class="ra-modal" style="display: none;">
                        <div class="ra-modal-content">
                            <span class="ra-modal-close">&times;</span>
                            <h3><?php _e('Lägg till bedömning', 'reading-assessment'); ?></h3>
                            <form id="assessment-form">
                                <input type="hidden" name="recording_id" id="assessment-recording-id">
                                <div class="form-field">
                                    <label for="assessment-score"><?php _e('Poäng (1-20)', 'reading-assessment'); ?></label>
                                    <input type="number" id="assessment-score" name="score" min="1" max="20" required>
                                    <p class="description"><?php _e('Ange poäng mellan 1 och 20', 'reading-assessment'); ?></p>
                                </div>
                                <button type="submit" class="button button-primary">
                                    <?php _e('Spara bedömning', 'reading-assessment'); ?>
                                </button>
                            </form>
                        </div>
                    </div>

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
                                    <?php
                                    // First page link
                                    if ($current_page > 1): ?>
                                        <a class="first-page button" href="<?php echo esc_url(add_query_arg('paged', 1)); ?>">
                                            <span>«</span>
                                        </a>
                                    <?php endif; ?>

                                    <?php
                                    // Previous page link
                                    if ($current_page > 1): ?>
                                        <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>">
                                            <span>‹</span>
                                        </a>
                                    <?php endif; ?>

                                    <span class="paging-input">
                                        <?php printf(
                                            '%s av %s',
                                            $current_page,
                                            $total_pagesq
                                        ); ?>
                                    </span>

                                    <?php
                                    // Next page link
                                    if ($current_page < $total_pages): ?>
                                        <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>">
                                            <span>›</span>
                                        </a>
                                    <?php endif; ?>

                                    <?php
                                    // Last page link
                                    if ($current_page < $total_pages): ?>
                                        <a class="last-page button" href="<?php echo esc_url(add_query_arg('paged', $total_pages)); ?>">
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



        <div class="ra-widget">
            <h2><?php _e('Statistik översikt', 'reading-assessment'); ?></h2>
            <div class="ra-widget-content">
                <?php
                $stats = $wpdb->get_row(
                    "SELECT
                        COUNT(DISTINCT r.id) as total_recordings,
                        COUNT(DISTINCT r.user_id) as unique_users,
                        COUNT(DISTINCT a.id) as total_assessments,
                        AVG(a.normalized_score) as avg_score,
                        SUM(r.duration) as total_duration
                    FROM {$wpdb->prefix}ra_recordings r
                    LEFT JOIN {$wpdb->prefix}ra_assessments a ON r.id = a.recording_id"
                );

                // Calculate percentages for pie chart
                $total = $stats->total_recordings + $stats->unique_users + $stats->total_assessments;
                $angles = array(
                    'recordings' => ($stats->total_recordings / $total) * 360,
                    'users' => ($stats->unique_users / $total) * 360,
                    'assessments' => ($stats->total_assessments / $total) * 360
                );

                // Calculate paths for pie slices
                $radius = 50;
                $center = 60;
                ?>

                <div class="stats-container">
                    <svg viewBox="0 0 120 120" class="stats-pie">
                        <?php
                        $start_angle = 0;
                        $colors = array('#0088FE', '#00C49F', '#FFBB28');
                        $i = 0;

                        foreach ($angles as $key => $angle) {
                            $end_angle = $start_angle + $angle;

                            // Calculate path
                            $start_rad = deg2rad($start_angle);
                            $end_rad = deg2rad($end_angle);

                            $start_x = $center + ($radius * cos($start_rad));
                            $start_y = $center + ($radius * sin($start_rad));
                            $end_x = $center + ($radius * cos($end_rad));
                            $end_y = $center + ($radius * sin($end_rad));

                            $large_arc = ($angle > 180) ? 1 : 0;

                            // Create pie slice
                            printf(
                                '<path d="M %f %f A %d %d 0 %d 1 %f %f L %d %d Z" fill="%s"/>',
                                $start_x, $start_y,
                                $radius, $radius,
                                $large_arc,
                                $end_x, $end_y,
                                $center, $center,
                                $colors[$i]
                            );

                            $start_angle = $end_angle;
                            $i++;
                        }
                        ?>
                    </svg>

                    <ul class="ra-stats-list">
                        <li>
                            <span class="color-dot" style="background-color: #0088FE"></span>
                            <strong><?php _e('Antal sparade inläsningar', 'reading-assessment'); ?>:</strong>
                            <?php echo esc_html($stats->total_recordings); ?>
                        </li>
                        <li>
                            <span class="color-dot" style="background-color: #00C49F"></span>
                            <strong><?php _e('Antal unika användare', 'reading-assessment'); ?>:</strong>
                            <?php echo esc_html($stats->unique_users); ?>
                        </li>
                        <li>
                            <span class="color-dot" style="background-color: #FFBB28"></span>
                            <strong><?php _e('Slutförda bedömningar', 'reading-assessment'); ?>:</strong>
                            <?php echo esc_html($stats->total_assessments); ?>
                        </li>
                        <li>
                            <strong><?php _e('Medelresultat', 'reading-assessment'); ?>:</strong>
                            <?php echo esc_html($stats->avg_score ? round($stats->avg_score, 2) : 0); ?> p
                        </li>
                        <li>
                            <strong><?php _e('Total inspelningstid', 'reading-assessment'); ?>:</strong>
                            <?php echo esc_html($stats->total_duration ? round($stats->total_duration / 60, 1) : 0); ?> minuter
                        </li>
                    </ul>
                </div>
            </div>

            <?php if (get_option('ra_enable_tracking', true)): ?>
                <div class="ra-stats-section">
                    <h2><?php _e('Användaraktivitet idag', 'reading-assessment'); ?></h2>
                    <p><?php _e('(sneaky Bossman vill veta hur mycket du jobbar)', 'reading-assessment'); ?></p>
                    <p><?php _e('Det här är en timer. Visar hur länge du haft fliken i fokus och antal klick.', 'reading-assessment'); ?></p>
                    <?php
                    global $wpdb;
                    $today = date('Y-m-d');
                    $user_id = get_current_user_id();

                    $stats = $wpdb->get_row($wpdb->prepare(
                        "SELECT
                            COALESCE(SUM(clicks), 0) as total_clicks,
                            COALESCE(SUM(active_time), 0) as total_active,
                            COALESCE(SUM(idle_time), 0) as total_idle
                        FROM {$wpdb->prefix}ra_admin_interactions
                        WHERE user_id = %d
                        AND DATE(created_at) = %s",
                        $user_id,
                        $today
                    ));

                    $interaction_data = array(
                        'active' => $stats ? intval($stats->total_active) : 0,
                        'idle' => $stats ? intval($stats->total_idle) : 0,
                        'clicks' => $stats ? intval($stats->total_clicks) : 0
                    );

                    $total = $interaction_data['active'] + $interaction_data['idle'];
                    ?>
                    <div class="stats-container">
                        <svg viewBox="0 0 120 120" class="stats-pie">
                            <?php
                            if ($total > 0) {
                                $start_angle = 0;
                                $colors = array('#4CAF50', '#FFC107', '#2196F3');
                                $i = 0;

                                foreach ($interaction_data as $value) {
                                    if ($value > 0) {
                                        $angle = ($value / $total) * 360;
                                        $end_angle = $start_angle + $angle;

                                        $start_rad = deg2rad($start_angle);
                                        $end_rad = deg2rad($end_angle);

                                        $center = 60;
                                        $radius = 50;

                                        $start_x = $center + ($radius * cos($start_rad));
                                        $start_y = $center + ($radius * sin($start_rad));
                                        $end_x = $center + ($radius * cos($end_rad));
                                        $end_y = $center + ($radius * sin($end_rad));

                                        $large_arc = ($angle > 180) ? 1 : 0;

                                        printf(
                                            '<path d="M %f %f A %d %d 0 %d 1 %f %f L %d %d Z" fill="%s"/>',
                                            $start_x, $start_y,
                                            $radius, $radius,
                                            $large_arc,
                                            $end_x, $end_y,
                                            $center, $center,
                                            $colors[$i]
                                        );

                                        $start_angle = $end_angle;
                                    }
                                    $i++;
                                }
                            }
                            ?>
                        </svg>

                        <ul class="ra-stats-list">
                            <li>
                                <span class="color-dot" style="background-color: #4CAF50"></span>
                                <strong><?php _e('Aktiv tid', 'reading-assessment'); ?>:</strong>
                                <?php echo esc_html(round($interaction_data['active'] / 60, 1)); ?> min
                            </li>
                            <li>
                                <span class="color-dot" style="background-color: #FFC107"></span>
                                <strong><?php _e('Inaktiv tid', 'reading-assessment'); ?>:</strong>
                                <?php echo esc_html(round($interaction_data['idle'] / 60, 1)); ?> min
                            </li>
                            <li>
                                <span class="color-dot" style="background-color: #2196F3"></span>
                                <strong><?php _e('Antal klick', 'reading-assessment'); ?>:</strong>
                                <?php echo esc_html($interaction_data['clicks']); ?>
                            </li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>
</div>
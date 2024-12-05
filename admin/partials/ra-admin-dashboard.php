<?php
// ra-admin-dashboard.php
if (!defined('WPINC')) {
    die;
}

$upload_dir = wp_upload_dir();

/**
 * Renders texxt for settings page in Klingon.
 */

$external_url = 'https://www.rugd.se';

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
echo '<p>' . esc_html__('Blajar på dårå och så vidare i ny pargraf.', 'reading-assessment') . '</p>';
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



?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
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
                
                if (false === $recent_recordings) {
                    global $wpdb;
                    $recent_recordings = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT r.*, u.display_name, u.ID as user_id, 
                                    r.audio_file_path, r.duration,
                                    DATE_FORMAT(r.created_at, '%Y/%m') as date_path
                            FROM {$wpdb->prefix}ra_recordings r
                            JOIN {$wpdb->users} u ON r.user_id = u.ID
                            ORDER BY r.created_at DESC
                            LIMIT %d OFFSET %d",
                            $per_page,
                            $offset
                        )
                    );
                    wp_cache_set($cache_key, $recent_recordings, '', 300); // Cache for 5 minutes
                }

                if ($recent_recordings): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Användare', 'reading-assessment'); ?></th>
                                <th><?php _e('Inspelning', 'reading-assessment'); ?></th>
                                <th><?php _e('Längd', 'reading-assessment'); ?></th>
                                <th><?php _e('Datum', 'reading-assessment'); ?></th>
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
                                    <td><?php echo esc_html($recording->duration ? round($recording->duration, 1) . 's' : 'N/A'); ?></td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($recording->created_at))); ?></td>
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
                                            $total_pages
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
                            <?php echo esc_html($stats->avg_score ? round($stats->avg_score, 2) : 0); ?>%
                        </li>
                        <li>
                            <strong><?php _e('Total inspelningstid', 'reading-assessment'); ?>:</strong> 
                            <?php echo esc_html($stats->total_duration ? round($stats->total_duration / 60, 1) : 0); ?> minuter
                        </li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>
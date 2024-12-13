<?php
// ra-admin-results.php
if (!defined('WPINC')) {
    die;
}

// Get statistics class instance
$stats = new RA_Statistics();

// Get filter values
$passage_id = isset($_GET['passage_id']) ? intval($_GET['passage_id']) : 0;
$date_range = isset($_GET['date_range']) ? intval($_GET['date_range']) : 30;

// Calculate date range
$date_limit = '';
if ($date_range > 0) {
    $date_limit = date('Y-m-d', strtotime("-$date_range days"));
}

// Get statistics
$overall_stats = $stats->get_overall_statistics($date_limit, $passage_id);
$passage_stats = $stats->get_passage_statistics($date_limit);
$question_stats = $stats->get_question_statistics($date_limit, $passage_id);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Filters -->
    <div class="ra-results-container">
        <div class="ra-results-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="ra-results">
                <select name="passage_id">
                    <option value=""><?php _e('Alla texter', 'reading-assessment'); ?></option>
                    <?php foreach ($stats->get_all_passages() as $passage): ?>
                        <option value="<?php echo esc_attr($passage->id); ?>"
                                <?php selected($passage_id, $passage->id); ?>>
                            <?php echo esc_html($passage->title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="date_range">
                    <option value="7" <?php selected($date_range, 7); ?>><?php _e('Senast 7 dagarna', 'reading-assessment'); ?></option>
                    <option value="30" <?php selected($date_range, 30); ?>><?php _e('Senast 30 dagarna', 'reading-assessment'); ?></option>
                    <option value="90" <?php selected($date_range, 90); ?>><?php _e('Senast 90 dagarna', 'reading-assessment'); ?></option>
                    <option value="all" <?php selected($date_range, 'all'); ?>><?php _e('Sedan början', 'reading-assessment'); ?></option>
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
                <h3><?php _e('Medelresultat', 'reading-assessment'); ?></h3>
                <div class="stat-number"><?php echo esc_html(number_format($overall_stats['avg_normalized_score'], 1)); ?>%</div>
            </div>
            <div class="ra-stat-card">
                <h3><?php _e('Frågor besvarade', 'reading-assessment'); ?></h3>
                <div class="stat-number"><?php echo esc_html($overall_stats['total_questions_answered']); ?></div>
            </div>
            <div class="ra-stat-card">
                <h3><?php _e('Antal rätt svar', 'reading-assessment'); ?></h3>
                <div class="stat-number"><?php echo esc_html(number_format($overall_stats['correct_answer_rate'], 1)); ?>%</div>
            </div>
        </div>

        <!-- Passage Performance -->
        <div class="ra-stats-section">
            <h2><?php _e('Texternas resultat', 'reading-assessment'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Text', 'reading-assessment'); ?></th>
                        <th><?php _e('Inspelningar', 'reading-assessment'); ?></th>
                        <th><?php _e('Medelresultat', 'reading-assessment'); ?></th>
                        <th><?php _e('"Rätt" svar', 'reading-assessment'); ?></th>
                        <th><?php _e('Tidsåtgång', 'reading-assessment'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($passage_stats as $passage): ?>
                    <tr>
                        <td><?php echo esc_html($passage['title']); ?></td>
                        <td><?php echo esc_html($passage['recording_count']); ?></td>
                        <td><?php echo esc_html(number_format($passage['avg_score'], 1)); ?>%</td>
                        <td><?php echo esc_html(number_format($passage['correct_answer_rate'], 1)); ?>%</td>
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
                        <th><?php _e('Fråga', 'reading-assessment'); ?></th>
                        <th><?php _e('Text', 'reading-assessment'); ?></th>
                        <th><?php _e('Antal inläsningar', 'reading-assessment'); ?></th>
                        <th><?php _e('Antal rätta svar', 'reading-assessment'); ?></th>
                        <th><?php _e('Likhet i medel', 'reading-assessment'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($question_stats as $question): ?>
                    <tr>
                        <td><?php echo esc_html($question['question_text']); ?></td>
                        <td><?php echo esc_html($question['passage_title']); ?></td>
                        <td><?php echo esc_html($question['times_answered']); ?></td>
                        <td><?php echo esc_html(number_format($question['correct_rate'], 1)); ?>%</td>
                        <td><?php echo esc_html(number_format($question['avg_similarity'], 1)); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
/**
 * Enhanced Statistics handling class
 */
class RA_Statistics {
    private $db;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }

    /**
     * Get overall statistics including assessment metrics
     */
    public function get_overall_statistics($date_limit = '', $passage_id = 0) {
        $where = array('1=1');
        $where_args = array();

        if ($date_limit) {
            $where[] = 'r.created_at >= %s';
            $where_args[] = $date_limit;
        }

        if ($passage_id) {
            $where[] = 'r.passage_id = %d';
            $where_args[] = $passage_id;
        }

        $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->db->get_row(
            $this->db->prepare(
                "SELECT
                    COUNT(DISTINCT r.id) as total_recordings,
                    COUNT(DISTINCT r.user_id) as unique_students,
                    AVG(a.normalized_score) as avg_normalized_score,
                    COUNT(resp.id) as total_questions_answered,
                    (SUM(CASE WHEN resp.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(resp.id)) as correct_answer_rate
                FROM {$this->db->prefix}ra_recordings r
                LEFT JOIN {$this->db->prefix}ra_assessments a ON r.id = a.recording_id
                LEFT JOIN {$this->db->prefix}ra_responses resp ON r.id = resp.recording_id
                $where_clause",
                $where_args
            ),
            ARRAY_A
        );
    }

    /**
     * Get enhanced passage statistics
     */
    public function get_passage_statistics($date_limit = '') {
        $where = $date_limit ? 'WHERE r.created_at >= %s' : '';

        return $this->db->get_results(
            $this->db->prepare(
                "SELECT
                    p.title,
                    COUNT(DISTINCT r.id) as recording_count,
                    AVG(a.normalized_score) as avg_score,
                    AVG(r.duration) as avg_duration,
                    (SUM(CASE WHEN resp.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(resp.id)) as correct_answer_rate
                FROM {$this->db->prefix}ra_passages p
                LEFT JOIN {$this->db->prefix}ra_recordings r ON p.id = r.passage_id
                LEFT JOIN {$this->db->prefix}ra_assessments a ON r.id = a.recording_id
                LEFT JOIN {$this->db->prefix}ra_responses resp ON r.id = resp.recording_id
                $where
                GROUP BY p.id
                ORDER BY recording_count DESC",
                $date_limit ? array($date_limit) : array()
            ),
            ARRAY_A
        );
    }

    /**
     * Get question-level statistics
     */
    public function get_question_statistics($date_limit = '', $passage_id = 0) {
        $where = array('1=1');
        $where_args = array();

        if ($date_limit) {
            $where[] = 'r.created_at >= %s';
            $where_args[] = $date_limit;
        }

        if ($passage_id) {
            $where[] = 'r.passage_id = %d';
            $where_args[] = $passage_id;
        }

        $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->db->get_results(
            $this->db->prepare(
                "SELECT
                    q.question_text,
                    p.title as passage_title,
                    COUNT(resp.id) as times_answered,
                    (SUM(CASE WHEN resp.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(resp.id)) as correct_rate,
                    AVG(resp.score) as avg_similarity
                FROM {$this->db->prefix}ra_questions q
                JOIN {$this->db->prefix}ra_passages p ON q.passage_id = p.id
                JOIN {$this->db->prefix}ra_responses resp ON q.id = resp.question_id
                JOIN {$this->db->prefix}ra_recordings r ON resp.recording_id = r.id
                $where_clause
                GROUP BY q.id
                ORDER BY correct_rate DESC",
                $where_args
            ),
            ARRAY_A
        );
    }

    /**
     * Get all passages for filter dropdown
     */
    public function get_all_passages() {
        return $this->db->get_results(
            "SELECT id, title
             FROM {$this->db->prefix}ra_passages
             ORDER BY title ASC"
        );
    }
}
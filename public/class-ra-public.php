<?php
/** class-ra-public.php
 * Public-facing functionality of the plugin.
 *
 * @package    ReadingAssessment
 * @subpackage ReadingAssessment/public
 */

class Reading_Assessment_Public {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }


    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name . '-public',
            RA_PLUGIN_URL . 'public/css/ra-public.css',
            [],
            $this->version,
            'all'
        );
    }

    public function enqueue_scripts() {
        global $post;

        // First register all scripts
        wp_register_script(
            $this->plugin_name . '-public',
            plugin_dir_url(__FILE__) . 'js/ra-public.js',
            ['jquery'],
            $this->version,
            true
        );

        // Only register recorder-related scripts on the recording page
        if (isset($post) && $post->post_name === 'inspelningsmodul') {
            // Define paths for local scripts
            $scripts_path = plugin_dir_path(__FILE__) . 'js/';
            $scripts_url = plugin_dir_url(__FILE__) . 'js/';

            // Download external scripts if they don't exist locally
            $external_scripts = [
                'wavesurfer.min.js' => 'https://cdn.jsdelivr.net/npm/wavesurfer.js@7.0.0/dist/wavesurfer.min.js',
                'regions.min.js' => 'https://cdn.jsdelivr.net/npm/wavesurfer.js@7.0.0/dist/plugins/regions.min.js'
            ];

            foreach ($external_scripts as $filename => $url) {
                if (!file_exists($scripts_path . $filename)) {
                    $response = wp_remote_get($url);
                    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                        wp_mkdir_p($scripts_path);
                        file_put_contents($scripts_path . $filename, wp_remote_retrieve_body($response));
                    }
                }
            }

            // Register WaveSurfer scripts
            wp_register_script('wavesurfer',
                $scripts_url . 'wavesurfer.min.js',
                [],
                '7.0.0',
                true
            );

            wp_register_script('wavesurfer-regions',
                $scripts_url . 'regions.min.js',
                ['wavesurfer'],
                '7.0.0',
                true
            );

            wp_register_script('ra-recorder',
                plugin_dir_url(__FILE__) . 'js/ra-recorder.js',
                ['wavesurfer', 'wavesurfer-regions', $this->plugin_name . '-public'],
                $this->version,
                true
            );

            // Enqueue recorder-specific scripts in correct order
            wp_enqueue_script('wavesurfer');
            wp_enqueue_script('wavesurfer-regions');
            wp_enqueue_script('ra-recorder');
        }

        // Always enqueue public script and localize it
        wp_enqueue_script($this->plugin_name . '-public');

        // Localize the script once with all needed data
        wp_localize_script($this->plugin_name . '-public', 'raAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(Reading_Assessment_Security::NONCE_PUBLIC),
            'debug' => true
        ]);
    }

    /**
     * Summary:
     * Cron job for asymmetric evaluating recorded sound files
     * @param mixed $recording_id
     * @return void
     */
    public function schedule_recording_processing($recording_id) {
        wp_schedule_single_event(time(), 'ra_process_recording', array($recording_id));
    }

    /**
     * Handle subscriber login redirect
     */
    public function subscriber_login_redirect($redirect_to, $requested_redirect_to, $user) {
        if ($user && is_object($user) && !is_wp_error($user)) {
            if (in_array('subscriber', $user->roles)) {
                return home_url('/inspelningsmodul?login=success');
            }
        }
        return $redirect_to;
    }

    /**
     * Display login success message
     */
    /*
    public function show_login_message() {
        if (isset($_GET['login']) && $_GET['login'] === 'success') {
            ?>
<div id="login-overlay" class="ra-overlay">
    <div id="login-message" class="ra-login-message">
        Du är inloggad.
    </div>
</div>
<?php
        }
    }
        */

    public function shortcode_audio_recorder() {
        ob_start();
        // Get current passage ID
        $current_passage_id = isset($_GET['passage_id']) ? intval($_GET['passage_id']) : 0;
        $has_valid_passage = $current_passage_id > 0;
        ?>
<div id="audio-recorder" class="ra-audio-recorder">
    <input type="hidden" id="current-passage-id" value="<?php echo esc_attr($current_passage_id); ?>">
    <!--
    <?php if (!$has_valid_passage): ?>
    <div class="ra-warning">
        Välj en text innan du börjar spela in.
    </div>
    <?php endif; ?>
    -->
    <div class="ra-controls <?php echo !$has_valid_passage ? 'ra-controls-disabled' : ''; ?>">
        <button id="start-recording" class="ra-button record" <?php echo !$has_valid_passage ? 'disabled' : ''; ?>>
            <span class="ra-icon">⚫</span>
            <span class="ra-label">Spela in</span>
        </button>

        <button id="stop-recording" class="ra-button stop" disabled>
            <span class="ra-icon">⬛</span>
            <span class="ra-label">Stopp</span>
        </button>

        <button id="playback" class="ra-button play" disabled>
            <span class="ra-icon">▶</span>
            <span class="ra-label">Spela</span>
        </button>

        <button id="trim-audio" class="ra-button trim" disabled>
            <span class="ra-icon">✂️</span>
            <span class="ra-label">Trimma</span>
        </button>

        <button id="upload-recording" class="ra-button upload" disabled>
            <span class="ra-icon">⬆️</span>
            <span class="ra-label">Ladda upp</span>
        </button>
    </div>


    <div id="waveform" class="ra-waveform"></div>
    <p id="status" class="ra-status">
        <?php echo $has_valid_passage ? 'Klicka på \'Spela in\' för att börja.' : 'Välj en text innan du börjar spela in.'; ?>
    </p>

    <!-- Add questions section -->
    <div id="questions-section" class="ra-questions" style="display: none;">
        <h3><?php _e('Frågor om texten', 'reading-assessment'); ?></h3>
        <?php
            if ($current_passage_id) {
                $db = new Reading_Assessment_Database();
                $questions = $db->get_questions_for_passage($current_passage_id);

                if ($questions): ?>
        <form id="questions-form" class="ra-questions-form">
            <?php foreach ($questions as $question): ?>
            <div class="ra-question-item">
                <label for="question-<?php echo esc_attr($question->id); ?>">
                    <?php echo esc_html($question->question_text); ?>
                </label>
                <input type="text" id="question-<?php echo esc_attr($question->id); ?>"
                    name="answers[<?php echo esc_attr($question->id); ?>]" class="ra-answer-input" required>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="ra-button submit-answers">
                <?php _e('Skicka svar', 'reading-assessment'); ?>
            </button>
        </form>
        <?php else: ?>
        <p><?php _e('Inga frågor tillgängliga för denna text.', 'reading-assessment'); ?></p>
        <?php endif;
                }
                ?>
    </div>
</div>
<?php
        return ob_get_clean();
    }


    // Here is where we show the text list tied to the user ID
    public function shortcode_display_passage($atts) {
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return '<p>' . __('Du måste vara inloggad för att se texter', 'reading-assessment') . '</p>';
        }

        $current_user = wp_get_current_user();
        $nickname = $current_user->nickname ?: $current_user->display_name;

        $db = new Reading_Assessment_Database();
        $assigned_passages = $db->get_user_assigned_passages($current_user_id);

        if (empty($assigned_passages)) {
            return '<p>' . __('Inga texter har tilldelats dig än', 'reading-assessment') . '</p>';
        }

        ob_start();
        ?>
<div class="ra-user-info">
    <h2><?php echo sprintf(__('Texter tilldelade till %s', 'reading-assessment'), esc_html($nickname)); ?></h2>
</div>

<!-- Passages Accordion -->
<div class="ra-assigned-passages">
    <?php foreach ($assigned_passages as $passage): ?>
    <div class="ra-collapsible">
        <h2 class="ra-collapsible-title" data-passage-id="<?php echo esc_attr($passage->id); ?>">
            <?php echo esc_html($passage->title); ?>
            <span class="ra-collapsible-icon">▼</span>
        </h2>
        <div id="passage-<?php echo esc_attr($passage->id); ?>" class="ra-collapsible-content">
            <?php echo wp_kses_post($passage->content); ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php
        return ob_get_clean();
    }

    public function register_shortcodes() {
        add_shortcode('ra_display_passage', [$this, 'shortcode_display_passage']);
        add_shortcode('ra_audio_recorder', [$this, 'shortcode_audio_recorder']);

    }


    /**
     * AJAX handler for saving recordings with security improvements
     */
    public function ajax_save_recording() {
        $security = Reading_Assessment_Security::get_instance();

        try {
            // Validate request
            $security->validate_ajax_request(Reading_Assessment_Security::NONCE_PUBLIC_RECORDING);
            if (!$security->can_record()) {
                throw new Exception(__('Permission denied', 'reading-assessment'));
            }

            // Validate passage
            $passage_id = $security->validate_passage_id($_POST['passage_id']);

            // Validate file
            if (!isset($_FILES['audio_file'])) {
                throw new Exception(__('No audio file received', 'reading-assessment'));
            }
            $security->validate_audio_file($_FILES['audio_file']);

            // Generate secure filename and path
            $upload_dir = wp_upload_dir();
            $year = date('Y');
            $month = date('m');
            $filename = $security->generate_secure_filename();
            $target_dir = $upload_dir['basedir'] . '/reading-assessment/' . $year . '/' . $month;

            // Ensure directory exists with proper permissions
            if (!wp_mkdir_p($target_dir)) {
                throw new Exception(__('Failed to create upload directory', 'reading-assessment'));
            }

            $file_path = $target_dir . '/' . $filename;
            if (!move_uploaded_file($_FILES['audio_file']['tmp_name'], $file_path)) {
                throw new Exception(__('Failed to save file', 'reading-assessment'));
            }

            // Set proper file permissions
            chmod($file_path, 0644);

            // Save to database using prepared statement
            global $wpdb;
            $result = $wpdb->insert(
                $wpdb->prefix . 'ra_recordings',
                [
                    'user_id' => get_current_user_id(),
                    'passage_id' => $passage_id,
                    'audio_file_path' => '/reading-assessment/' . $year . '/' . $month . '/' . $filename,
                    'duration' => floatval($_POST['duration']),
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%d', '%s', '%f', '%s']
            );

            if ($result === false) {
                throw new Exception(__('Database error', 'reading-assessment'));
            }

            wp_send_json_success([
                'message' => __('Recording saved successfully', 'reading-assessment'),
                'recording_id' => $wpdb->insert_id
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'recording_error'
            ]);
        }
    }

    /**
     * AJAX handler for saving recordings with security improvements
     */
    public function ajax_get_questions() {
        // error_log('POST data: ' . print_r($_POST, true));

        // First verify nonce
        if (!check_ajax_referer(NONCE_PUBLIC_QUESTIONS, 'nonce', false)) {
            // error_log('Nonce verification failed');
            wp_send_json_error(['message' => 'Säkerhetskontrollen ogiltig tyvärr.']);
            return;
        }

        // Get and validate passage_id
        $passage_id = isset($_POST['passage_id']) ? absint($_POST['passage_id']) : 0;

        if (!$passage_id || $passage_id === 0) {
            wp_send_json_error(['message' => 'Ogiltigt text ID. Du behöver få en text av en adinistratör att läsa upp.']);
            return;
        }

        // Get questions
        $db = new Reading_Assessment_Database();
        $questions = $db->get_questions_for_passage($passage_id);
        // error_log('Raw questions from database: ' . print_r($questions, true));

        if (empty($questions)) {
            wp_send_json_error(['message' => 'Inga frågor kunde hittas om denna text.']);
            return;
        }

        // Send the questions directly
        wp_send_json_success($questions);
        // error_log('===== END ajax_get_questions =====');
    }


    /**
     * AJAX handler for submitting answers with security improvements
     */
    public function ajax_submit_answers() {
        $security = Reading_Assessment_Security::get_instance();

        try {
            // Validate request
            $security->validate_ajax_request(Reading_Assessment_Security::NONCE_PUBLIC_ANSWERS);

            // Validate recording ownership
            $recording_id = absint($_POST['recording_id']);
            $security->validate_recording_ownership($recording_id);

            // Sanitize and validate answers
            $answers_json = isset($_POST['answers']) ? stripslashes($_POST['answers']) : '';
            $answers = json_decode($answers_json, true);
            $sanitized_answers = $security->sanitize_answers($answers);

            if (empty($sanitized_answers)) {
                throw new Exception(__('No valid answers provided', 'reading-assessment'));
            }

            // Save answers using prepared statements
            global $wpdb;
            $saved_count = 0;
            $errors = [];

            foreach ($sanitized_answers as $question_id => $answer) {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'ra_responses',
                    [
                        'recording_id' => $recording_id,
                        'question_id' => $question_id,
                        'user_answer' => $answer,
                        'created_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%s', '%s']
                );

                if ($result) {
                    $saved_count++;
                } else {
                    $errors[] = sprintf(__('Failed to save answer for question %d', 'reading-assessment'), $question_id);
                }
            }

            if ($saved_count > 0) {
                wp_send_json_success([
                    'message' => sprintf(
                        _n(
                            'Saved %d answer',
                            'Saved %d answers',
                            $saved_count,
                            'reading-assessment'
                        ),
                        $saved_count
                    ),
                    'saved_count' => $saved_count,
                    'errors' => $errors
                ]);
            } else {
                throw new Exception(__('Failed to save answers', 'reading-assessment'));
            }

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'answer_submission_error'
            ]);
        }
    }
}
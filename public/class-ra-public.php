<?php
/** class-ra-public.php
 * Public-facing functionality of the plugin.
 *
 * @package    ReadingAssessment
 * @subpackage ReadingAssessment/public
 */

class RA_Public {

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

        // Only load scripts on the recording page
        if (isset($post) && $post->post_name === 'inspelningsmodul') {
            // Register and enqueue WaveSurfer scripts directly from CDN
            wp_register_script(
                'wavesurfer',
                'https://unpkg.com/wavesurfer.js@6.6.4',
                [],
                '6.6.4',
                true
            );

            wp_register_script(
                'wavesurfer-regions',
                'https://unpkg.com/wavesurfer.js@6.6.4/dist/plugin/wavesurfer.regions.min.js',
                ['wavesurfer'],
                '6.6.4',
                true
            );

            // Register your custom scripts
            wp_register_script(
                $this->plugin_name . '-public',
                plugin_dir_url(__FILE__) . 'js/ra-public.js',
                ['jquery', 'wavesurfer', 'wavesurfer-regions'],
                $this->version,
                true
            );

            wp_register_script(
                'ra-recorder',
                plugin_dir_url(__FILE__) . 'js/ra-recorder.js',
                ['jquery', 'wavesurfer', 'wavesurfer-regions', $this->plugin_name . '-public'],
                $this->version,
                true
            );

            // Enqueue all scripts
            wp_enqueue_script('wavesurfer');
            wp_enqueue_script('wavesurfer-regions');
            wp_enqueue_script($this->plugin_name . '-public');
            wp_enqueue_script('ra-recorder');

            // Always enqueue public script and localize it
            wp_enqueue_script($this->plugin_name . '-public');

            // Add localization
            wp_localize_script($this->plugin_name . '-public', 'raAjax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(RA_Security::NONCE_PUBLIC),
                'current_user_id' => get_current_user_id(),
                'debug' => true
            ]);
        }

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


        public function shortcode_audio_recorder() {
            ob_start();
            // Get current passage ID
            $current_passage_id = isset($_GET['passage_id']) ? intval($_GET['passage_id']) : 0;
            $has_valid_passage = $current_passage_id > 0;
            ?>
<div id="audio-recorder" class="ra-audio-recorder">
    <input type="hidden" id="current-passage-id" value="<?php echo esc_attr($current_passage_id); ?>">

    <!-- User Grade Input -->
    <div class="ra-input-group">
        <label for="user-grade"><?php _e('Årskurs:', 'reading-assessment'); ?></label>
        <input type="text" id="user-grade" name="user_grade" class="ra-user-grade-input" placeholder="Ex. 3, 4A, Mellanstadiet, etc. Det kommer med i filnamnet">
    </div>

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

    <div id="waveform"></div>

    <p id="status" class="ra-status">
        <?php echo $has_valid_passage ? 'Klicka på \'Spela in\' för att börja.' : 'Välj en text innan du börjar spela in.'; ?>
    </p>

    <div id="questions-section" class="ra-questions" style="display: none;">
        <h3><?php _e('Frågor om texten', 'reading-assessment'); ?></h3>
        <?php
            if ($current_passage_id) {
                $db = new RA_Database();
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
        $db = new RA_Database();
        $is_admin = in_array('administrator', (array) $current_user->roles);
        $nickname = $current_user->nickname ?: $current_user->display_name;

        if ($is_admin) {
            $passages = $db->get_all_passages();
            $heading_text = __('Tillgängliga texter', 'reading-assessment');
        } else {
            $passages = $db->get_user_assigned_passages($current_user_id);
            $heading_text = sprintf(__('Texter tilldelade till %s', 'reading-assessment'), esc_html($nickname));
        }

        if (empty($passages)) {
            if ($is_admin) {
                return '<p>' . __('Det finns inga texter skapade än.', 'reading-assessment') . '</p>';
            } else {
                return '<p>' . __('Inga texter har tilldelats dig än', 'reading-assessment') . '</p>';
            }
        }

        ob_start();
        ?>
        <div class="ra-user-info">
            <h2><?php echo esc_html($heading_text); ?></h2>
        </div>

        <!-- Passages Accordion -->
        <div class="ra-assigned-passages">
            <?php foreach ($passages as $passage): ?>
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
        ob_start(); // Start output buffering

        try {
            global $wpdb; // Ensure $wpdb is available

            $security = RA_Security::get_instance();
            // Validate request
            $security->validate_ajax_request(RA_Security::NONCE_PUBLIC, 'nonce');
            if (!$security->can_record()) {
                throw new Exception(__('Permission denied', 'reading-assessment'));
            }

            // Validate passage
            $passage_id = $security->validate_passage_id($_POST['passage_id']);

            // Fetch raw passage title - This will be sanitized in generate_secure_filename
            $passage_title_raw = ''; // Default to empty string
            if ($passage_id > 0) {
                $passage_table = $wpdb->prefix . 'ra_passages';
                $db_title = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$passage_table} WHERE id = %d", $passage_id));

                if ($db_title !== null) {
                    $passage_title_raw = $db_title;
                }
            }

            // Get user_grade from POST and apply basic sanitization
            $user_grade_raw = isset($_POST['user_grade']) ? sanitize_text_field(wp_unslash($_POST['user_grade'])) : '';

            // Get current user ID before using it in generate_secure_filename
            $user_id = get_current_user_id(); 

            // Generate filename using the security class, passing raw-ish title and grade
            $filename = $security->generate_secure_filename($user_id, $passage_title_raw, $user_grade_raw, 'wav');

            // Prepare upload directory
            $upload_dir = wp_upload_dir();
            $year = date('Y');
            $month = date('m');
            // The user_grade is already sanitized and available from previous steps

            $target_dir = $upload_dir['basedir'] . '/reading-assessment/' . $year . '/' . $month;

            // Ensure directory exists with proper permissions
            if (!wp_mkdir_p($target_dir)) {
                throw new Exception(__('Failed to create upload directory', 'reading-assessment'));
            }

            $file_path = $target_dir . '/' . $filename;

            // Check for file upload errors before moving
            if (!isset($_FILES['audio_file'])) {
                throw new Exception(__('No file data received in request.', 'reading-assessment'));
            }

            if ($_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE   => __('The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'reading-assessment'),
                    UPLOAD_ERR_FORM_SIZE  => __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.', 'reading-assessment'),
                    UPLOAD_ERR_PARTIAL    => __('The uploaded file was only partially uploaded.', 'reading-assessment'),
                    UPLOAD_ERR_NO_FILE    => __('No file was uploaded.', 'reading-assessment'),
                    UPLOAD_ERR_NO_TMP_DIR => __('Missing a temporary folder on the server.', 'reading-assessment'),
                    UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk on the server.', 'reading-assessment'),
                    UPLOAD_ERR_EXTENSION  => __('A PHP extension stopped the file upload.', 'reading-assessment'),
                ];
                $error_code = $_FILES['audio_file']['error'];
                $error_message = isset($upload_errors[$error_code]) ? $upload_errors[$error_code] : __('Unknown upload error.', 'reading-assessment');
                throw new Exception($error_message . ' (Error code: ' . $error_code . ')');
            }
            
            // Ensure the uploaded file is a valid uploaded file
            if (!is_uploaded_file($_FILES['audio_file']['tmp_name'])) {
                // This can happen if the file is too large and PHP discards it silently, or other tampering.
                // $_FILES['audio_file']['tmp_name'] might not be set or might be empty if post_max_size is exceeded.
                // Or if UPLOAD_ERR_INI_SIZE or UPLOAD_ERR_FORM_SIZE occurred, tmp_name might be empty.
                error_log('RA Save Recording: is_uploaded_file check failed for ' . print_r($_FILES['audio_file'], true));
                throw new Exception(__('Invalid upload: The file was not recognized as a valid uploaded file. This could be due to exceeding post_max_size or other server-side issues.', 'reading-assessment'));
            }

            if (!move_uploaded_file($_FILES['audio_file']['tmp_name'], $file_path)) {
                // Log details to help debug why move_uploaded_file failed
                $last_error = error_get_last();
                $error_details = $last_error ? ' Last PHP error: ' . $last_error['message'] : '';
                $debug_info = ' Target: ' . $file_path . '. Temp file exists: ' . (file_exists($_FILES['audio_file']['tmp_name']) ? 'yes' : 'no') . '. Target dir writable: ' . (is_writable($target_dir) ? 'yes' : 'no') . '.';
                error_log('RA Save Recording: move_uploaded_file failed.' . $debug_info . $error_details);
                throw new Exception(__('Failed to save file. Please check server error logs and directory permissions.', 'reading-assessment'));
            }

            // Set proper file permissions
            chmod($file_path, 0644);

            // Save to database using prepared statement
            $result = $wpdb->insert(
                $wpdb->prefix . 'ra_recordings',
                [
                    'user_id' => $user_id,
                    'passage_id' => $passage_id,
                    'user_grade' => $user_grade_raw, 
                    'audio_file_path' => '/reading-assessment/' . $year . '/' . $month . '/' . $filename, // Ensure this path matches how files are stored and retrieved
                    'duration' => floatval($_POST['duration']),
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%d', '%s', '%s', '%f', '%s']
            );

            if ($result === false) {
                throw new Exception(__('Database error', 'reading-assessment'));
            }

            $response_data = [
                'message' => __('Recording saved successfully.', 'reading-assessment'),
                'recording_id' => $wpdb->insert_id,
                'new_nonce' => wp_create_nonce(RA_Security::NONCE_PUBLIC),
                'questions_optional' => (bool) get_option('ra_allow_public_recordings_without_questions', false)
            ];

            // Optionally log the response data for debugging
            ob_clean(); // Clean the output buffer before sending JSON
            wp_send_json_success($response_data);

        } catch (Exception $e) {
            error_log('RA Save Recording Exception: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString()); // Log the full error and trace
            ob_clean(); // Clean the output buffer before sending JSON
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
        $security = RA_Security::get_instance();
        try {
            // First verify nonce
            $security->validate_ajax_request(RA_Security::NONCE_PUBLIC, 'nonce');

            // Get and validate passage_id
            $passage_id = isset($_POST['passage_id']) ? absint($_POST['passage_id']) : 0;

            if (!$passage_id || $passage_id === 0) {
                wp_send_json_error(['message' => 'Ogiltigt text ID. Du behöver få en text av en adinistratör att läsa upp.']);
                return; // Important to return after wp_send_json_error
            }

            // Get questions
            $db = new RA_Database();
            $questions = $db->get_questions_for_passage($passage_id);

            if (empty($questions)) {
                wp_send_json_error(['message' => 'Inga frågor kunde hittas om denna text.']);
                return; // Important to return
            }

            wp_send_json_success($questions);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }


    /**
     * AJAX handler for submitting answers with security improvements
     */
    public function ajax_submit_answers() {
        $security = RA_Security::get_instance();

        try {
            // Validate request
            $security->validate_ajax_request(RA_Security::NONCE_PUBLIC, 'nonce');

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

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
            <script>
                setTimeout(function() {
                    var overlay = document.getElementById('login-overlay');
                    overlay.style.opacity = '0';
                    setTimeout(function() {
                        overlay.remove();
                    }, 500);
                }, 2000);
            </script>
            <?php
        }
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
        wp_enqueue_script(
            $this->plugin_name . '-public',
            RA_PLUGIN_URL . 'public/js/ra-public.js',
            ['jquery'],
            $this->version,
            true
        );


        // Include (on slug inspelningsmodul) WaveSurfer.js from CDN
        global $post;
        if (isset($post) && $post->post_name === 'inspelningsmodul') {
            wp_enqueue_script(
                'wavesurfer',
                'https://unpkg.com/wavesurfer.js@7/dist/wavesurfer.min.js',
                [],
                '7.0.0',
                true
            );

            // Include Regions plugin from CDN
            wp_enqueue_script(
                'wavesurfer-regions',
                'https://unpkg.com/wavesurfer.js@7/dist/plugins/regions.min.js',
                ['wavesurfer'],
                '7.0.0',
                true
            );


            wp_enqueue_script(
                'ra-recorder',
                plugins_url('/js/ra-recorder.js', __FILE__),
                ['wavesurfer', 'wavesurfer-regions'],
                $this->version,
                true
            );

            // Pass AJAX URL to JavaScript
            wp_localize_script(
                'ra-recorder',
                'raAjax',
                ['ajax_url' => admin_url('admin-ajax.php')]
            );
        }
    }


    public function shortcode_audio_recorder() {
        ob_start();
        ?>
        <div id="audio-recorder" class="ra-audio-recorder">
            <?php
            // Get current passage ID
            $current_passage_id = isset($_GET['passage_id']) ? intval($_GET['passage_id']) : 0;
            ?>
            <input type="hidden" id="current-passage-id" value="<?php echo esc_attr($current_passage_id); ?>">

            <div class="ra-controls">
            <button id="start-recording" class="ra-button record">
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


        <div id="waveform" class="ra-waveform" style="display: block; min-height: 128px; width: 100%;"></div>
        <p id="status" class="ra-status"></p>

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
                                <input type="text"
                                        id="question-<?php echo esc_attr($question->id); ?>"
                                        name="answers[<?php echo esc_attr($question->id); ?>]"
                                        class="ra-answer-input"
                                        required>
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

    // Here is where we show the text list tied to the user ID. An admin gives the user appropriate texts.
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

    public function ajax_save_recording() {
        error_log('Starting ajax_save_recording');
        error_log('POST data: ' . print_r($_POST, true));
        error_log('FILES data: ' . print_r($_FILES, true));

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'User not logged in']);
            return;
        }

        if (!isset($_FILES['audio_file'])) {
            wp_send_json_error(['message' => 'No audio file received']);
            return;
        }


        // Get the passage_id from the request
        $passage_id = isset($_POST['passage_id']) ? intval($_POST['passage_id']) : 0;
        error_log('Passage ID from request: ' . $passage_id);

        // Set up directory structure
        $upload_dir = wp_upload_dir();
        $year = date('Y');
        $month = date('m');
        $target_dir = $upload_dir['basedir'] . '/reading-assessment/' . $year . '/' . $month;

        // Create directories if they don't exist
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        // Generate unique filename
        $file_name = 'recording_' . time() . '.webm';
        $file_path = $target_dir . '/' . $file_name;

        // Store relative path for database
        $relative_path = '/reading-assessment/' . $year . '/' . $month . '/' . $file_name;

        if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $file_path)) {
            chmod($file_path, 0644);

            // Save to database with passage_id
            global $wpdb;
            $result = $wpdb->insert(
                $wpdb->prefix . 'ra_recordings',
                array(
                    'user_id' => get_current_user_id(),
                    'passage_id' => $passage_id,  // Make sure this gets saved
                    'audio_file_path' => $relative_path,
                    'duration' => isset($_POST['duration']) ? floatval($_POST['duration']) : 0,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%d', '%s')
            );

            if ($result === false) {
                error_log('Database insert failed: ' . $wpdb->last_error);
                wp_send_json_error([
                    'message' => 'Failed to save recording data to database',
                    'error' => $wpdb->last_error
                ]);
                return;
            }

            wp_send_json_success([
                'message' => 'File saved successfully',
                'file_path' => $relative_path,
                'recording_id' => $wpdb->insert_id,
                //'passage_id' => $passage_id,  // Return this in response?
                'url' => $upload_dir['baseurl'] . $relative_path
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Failed to save file',
                'error' => error_get_last()
            ]);
        }
    }


    public function ajax_get_questions() {
        error_log('===== QUESTIONS AJAX HANDLER CALLED =====');
        error_log('REQUEST: ' . print_r($_REQUEST, true));

        // Verify nonce
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        error_log('Received nonce: ' . $nonce);

        if (!wp_verify_nonce($nonce, 'ra_public_nonce')) {
            error_log('Nonce verification failed');
            wp_send_json_error(['message' => 'Security check failed']);
            exit;
        }

        // Get passage ID
        $passage_id = isset($_POST['passage_id']) ? absint($_POST['passage_id']) : 0;
        error_log('Processing passage ID: ' . $passage_id);

        if (!$passage_id) {
            error_log('Invalid passage ID');
            wp_send_json_error(['message' => 'Invalid passage ID']);
            exit;
        }

        // Get questions
        $questions = $this->db->get_questions_for_passage($passage_id);
        error_log('Questions from database: ' . print_r($questions, true));

        if (empty($questions)) {
            error_log('No questions found');
            wp_send_json_error(['message' => 'No questions found for this passage']);
            exit;
        }

        // Format and send response
        $formatted_questions = array_map(function($q) {
            return [
                'id' => absint($q['id']),
                'question_text' => $q['question_text'],
                'correct_answer' => $q['correct_answer'],
                'weight' => floatval($q['weight'])
            ];
        }, $questions);

        error_log('Sending questions response: ' . print_r($formatted_questions, true));
        wp_send_json_success($formatted_questions);
        exit;
    }


    public function ajax_submit_answers() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'User not logged in']);
            return;
        }

        if (!isset($_POST['answers']) || !isset($_POST['recording_id'])) {
            wp_send_json_error(['message' => 'Missing required data']);
            return;
        }

        // Get recording ID and answers
        $recording_id = intval($_POST['recording_id']);
        $answers = $_POST['answers'];

        // Validate recording exists and belongs to user
        $recording = $this->db->get_recording($recording_id);
        if (!$recording || $recording->user_id !== get_current_user_id()) {
            wp_send_json_error(['message' => 'Invalid recording']);
            return;
        }

        // Initialize evaluator
        $evaluator = new Reading_Assessment_Evaluator();

        // Process answers and store results
        $result = $evaluator->evaluate_assessment($recording_id, $answers);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }

        // Return success with results
        wp_send_json_success([
            'message' => 'Answers submitted successfully',
            'score' => $result['normalized_score'],
            'correct_answers' => $result['correct_answers'],
            'total_questions' => $result['total_questions']
        ]);
    }

    public function ajax_get_assessment() {
        // Implementation for retrieving assessments via AJAX.
        wp_send_json_success(['message' => 'Assessment retrieved']);
    }

    public function ajax_get_passage() {
        // Implementation for retrieving a passage via AJAX.
        wp_send_json_success(['message' => 'Passage retrieved']);
    }
}

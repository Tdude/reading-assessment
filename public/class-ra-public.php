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


            // Get current user and nonce for debog
        $ajax_nonce = wp_create_nonce('ra_public_nonce');
        // error_log('Generated nonce: ' . $ajax_nonce);
            // Pass essential data to JS
            $script_data = array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => $ajax_nonce,
                'debug' => true
            );
        // error_log('Localizing script with data: ' . print_r($script_data, true));



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
             // Pass the same data to recorder script
            wp_localize_script('ra-recorder', 'raAjax', $script_data);
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

    <?php if (!$has_valid_passage): ?>
    <div class="ra-warning">
        Välj en text innan du börjar spela in.
    </div>
    <?php endif; ?>

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
       // error_log('Starting ajax_save_recording');
       // error_log('POST data: ' . print_r($_POST, true));
       // error_log('FILES data: ' . print_r($_FILES, true));

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Användaren är inte inloggad. Har du fått ett login?']);
            return;
        }

        if (!isset($_FILES['audio_file'])) {
            wp_send_json_error(['message' => 'Kunde inte ta emot ljudfil.']);
            return;
        }

        // More logging
        // error_log('POST data: ' . print_r($_POST, true));

        // Get the passage_id from the request
        $passage_id = isset($_POST['passage_id']) ? intval($_POST['passage_id']) : 0;
        // error_log('Passage ID from request: ' . $passage_id);

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
                    'message' => 'Kunde inte spara den inspelade filen. Prova igen eller kontakta administratören!',
                    'error' => $wpdb->last_error
                ]);
                return;
            }

            wp_send_json_success([
                'message' => 'File saved successfully',
                'file_path' => $relative_path,
                'recording_id' => $wpdb->insert_id,
                'passage_id' => $passage_id,  // Return this in response
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
        // error_log('===== START ajax_get_questions =====');
        // error_log('POST data: ' . print_r($_POST, true));

        // First verify nonce
        if (!check_ajax_referer('ra_public_nonce', 'nonce', false)) {
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



    public function ajax_submit_answers() {

        try {
            // Verify nonce
            if (!check_ajax_referer('ra_public_nonce', 'nonce', false)) {
                // error_log('Nonce verification failed');
                wp_send_json_error(['message' => 'Säkerhetskontrollen ogiltig tyvärr.']);
                return;
            }

            // Get and validate recording ID
            $recording_id = isset($_POST['recording_id']) ? absint($_POST['recording_id']) : 0;

            if (!$recording_id) {
                wp_send_json_error(['message' => 'Ogiltigt inspelnings ID. Det betyder att det uppstod ett fel vid uppladdningen. Prova igen.']);
                return;
            }

            // Get and validate answers
            $answers_json = isset($_POST['answers']) ? stripslashes($_POST['answers']) : '';
            $answers = json_decode($answers_json, true);

            if (!is_array($answers) || empty($answers)) {
                wp_send_json_error(['message' => 'Ogiltigt svarsformat.']);
                return;
            }

            // Verify recording
            $db = new Reading_Assessment_Database();
            $recording = $db->get_recording($recording_id);
            $current_user_id = get_current_user_id();

            if (!$recording) {
                wp_send_json_error(['message' => 'Inspelningen kunde inte hittas.']);
                return;
            }

            if ($recording->user_id != $current_user_id) {
                // error_log('Recording user ID mismatch. Recording user: ' . $recording->user_id . ', Current user: ' . $current_user_id);
                wp_send_json_error(['message' => 'Denna användare nekas tyvärr. Logga ut och in igen!']);
                return;
            }

            // Save answers
            $saved_count = 0;
            $errors = [];

            foreach ($answers as $question_id => $answer_text) {
                try {
                    $result = $db->save_response([
                        'recording_id' => $recording_id,
                        'question_id' => absint($question_id),
                        'user_answer' => sanitize_text_field($answer_text)
                    ]);

                    if ($result) {
                        $saved_count++;
                    } else {
                        $errors[] = "Kunde inte spara svaret till frågan: $question_id";
                    }
                } catch (Exception $e) {
                    // error_log("Error saving answer for question $question_id: " . $e->getMessage());
                    $errors[] = "Error with question $question_id: " . $e->getMessage();
                }
            }

            if ($saved_count > 0) {
                $message = sprintf(
                    _n(
                        'Sparade %d svar',
                        'Sparade %d svar',
                        $saved_count,
                        'reading-assessment'
                    ),
                    $saved_count
                );

                if (!empty($errors)) {
                    $message .= ' (' . count($errors) . ' misslyckades)';
                }

                wp_send_json_success([
                    'message' => $message,
                    'saved_count' => $saved_count,
                    'errors' => $errors
                ]);
            } else {
                wp_send_json_error([
                    'message' => 'Kunde inte spara några svar',
                    'errors' => $errors
                ]);
            }

        } catch (Exception $e) {
            // error_log('Error in ajax_submit_answers: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Det uppstod ett serverfel.']);
        }
    }
}
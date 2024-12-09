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
                    Nu är du inloggad.
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
                    
            <div id="waveform" class="ra-waveform"></div>
            <p id="status" class="ra-status"></p>
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
        <div class="ra-assigned-passages">
            <?php foreach ($assigned_passages as $passage): ?>
                <div class="ra-collapsible">
                    <h2 class="ra-collapsible-title" data-target="passage-<?php echo esc_attr($passage->id); ?>">
                        <?php echo esc_html($passage->title); ?>
                        <span class="ra-collapsible-icon">▼</span>
                    </h2>
                    <div id="passage-<?php echo esc_attr($passage->id); ?>" class="ra-collapsible-content">
                        <?php echo wp_kses_post($passage->content); ?>
                        <?php if ($passage->audio_file): ?>
                            <div class="ra-passage-audio">
                                <audio controls>
                                    <source src="<?php echo esc_url(wp_upload_dir()['baseurl'] . '/reading-assessment/' . $passage->audio_file); ?>" type="audio/mpeg">
                                </audio>
                            </div>
                        <?php endif; ?>
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
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'User not logged in']);
            return;
        }
    
        if (!isset($_FILES['audio_file'])) {
            wp_send_json_error(['message' => 'No audio file received']);
            return;
        }
    
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
            
            // Save to database
            global $wpdb;
            $result = $wpdb->insert(
                $wpdb->prefix . 'ra_recordings',
                array(
                    'user_id' => get_current_user_id(),
                    'passage_id' => isset($_POST['passage_id']) ? intval($_POST['passage_id']) : 0,
                    'audio_file_path' => $relative_path,
                    'duration' => isset($_POST['duration']) ? floatval($_POST['duration']) : 0,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%d', '%s')
            );
    
            if ($result === false) {
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
                'url' => $upload_dir['baseurl'] . $relative_path
            ]);

            // Possibly need to add this for the admin list when saving new recordings
            // wp_cache_delete('ra_recordings_count');
            // wp_cache_delete('ra_recordings_page_1');

        } else {
            wp_send_json_error([
                'message' => 'Failed to save file',
                'error' => error_get_last()
            ]);
        }
    }

    public function ajax_submit_answers() {
        // Implementation for submitting answers via AJAX.
        wp_send_json_success(['message' => 'Answers submitted']);
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

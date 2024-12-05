<?php
/**
 * Reading Assessment
 *
 * @package     ReadingAssessment
 * @author      Tibor Berki
 * @copyright   2024 @Tdude
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: Reading Assessment
 * Plugin URI:  https://example.com/plugins/reading-assessment
 * Description: A plugin for recording and evaluating reading comprehension
 * Version:     1.0.1
 * Author:      Tibor Berki
 * Author URI:  https://klickomaten.com
 * Text Domain: reading-assessment
 * License:     GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Plugin version
define('RA_VERSION', '1.0.1');
define('RA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RA_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_reading_assessment() {
    require_once RA_PLUGIN_DIR . 'includes/class-ra-activator.php';
    Reading_Assessment_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_reading_assessment() {
    require_once RA_PLUGIN_DIR . 'includes/class-ra-deactivator.php';
    Reading_Assessment_Deactivator::deactivate();
}


register_deactivation_hook(__FILE__, function() {
    add_option('ra_preserve_data', true);
});

register_activation_hook(__FILE__, 'activate_reading_assessment');
register_deactivation_hook(__FILE__, 'deactivate_reading_assessment');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once RA_PLUGIN_DIR . 'includes/class-reading-assessment.php';

/**
 * Begins execution of the plugin.
 */
function run_reading_assessment() {
    $plugin = new Reading_Assessment();
    $plugin->run();
}
run_reading_assessment();
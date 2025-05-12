<?php
/**
 * Status Sentry WP - Advanced WordPress Monitoring Plugin
 *
 * This is the main plugin file that registers hooks, defines constants,
 * and starts the plugin execution.
 *
 * @link              https://github.com/status-sentry/status-sentry-wp
 * @since             1.0.0
 * @version           1.2.0
 * @package           Status_Sentry
 *
 * @wordpress-plugin
 * Plugin Name:       Status Sentry WP
 * Plugin URI:        https://github.com/status-sentry/status-sentry-wp
 * Description:       A comprehensive WordPress plugin for monitoring site health, capturing performance data, and detecting plugin conflicts with minimal impact.
 * Version:           1.2.0
 * Author:            Status Sentry Team
 * Author URI:        https://github.com/status-sentry
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       status-sentry-wp
 * Domain Path:       /languages
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Tested up to:      6.2
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Define plugin constants.
 *
 * These constants are used throughout the plugin for file paths, URLs,
 * versioning, and other configuration.
 *
 * @since 1.0.0
 */
define('STATUS_SENTRY_VERSION', '1.2.0');                         // Plugin version for cache busting and version checks
define('STATUS_SENTRY_PLUGIN_DIR', plugin_dir_path(__FILE__));    // Plugin directory path with trailing slash
define('STATUS_SENTRY_PLUGIN_URL', plugin_dir_url(__FILE__));     // Plugin directory URL with trailing slash
define('STATUS_SENTRY_PLUGIN_BASENAME', plugin_basename(__FILE__)); // Plugin basename for plugin_action_links filter

/**
 * The code that runs during plugin activation.
 *
 * This function is triggered when the plugin is activated through the WordPress admin interface.
 * It runs database migrations, sets up scheduled tasks, and initializes plugin settings.
 *
 * @since 1.0.0
 * @see Status_Sentry_Activator::activate()
 */
function activate_status_sentry() {
    require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/class-status-sentry-activator.php';
    Status_Sentry_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 *
 * This function is triggered when the plugin is deactivated through the WordPress admin interface.
 * It cleans up scheduled tasks and performs any necessary cleanup operations.
 *
 * @since 1.0.0
 * @see Status_Sentry_Deactivator::deactivate()
 */
function deactivate_status_sentry() {
    require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/class-status-sentry-deactivator.php';
    Status_Sentry_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_status_sentry');
register_deactivation_hook(__FILE__, 'deactivate_status_sentry');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 *
 * @since 1.0.0
 */
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/class-status-sentry.php';

/**
 * Register the plugin's autoloader.
 *
 * This autoloader follows WordPress naming conventions and automatically loads
 * classes with the 'Status_Sentry_' prefix from the appropriate directories.
 *
 * Class names are converted to file paths according to the following rules:
 * - 'Status_Sentry_' prefix is removed
 * - Underscores are converted to hyphens
 * - The class name is converted to lowercase
 * - 'class-' prefix is added
 * - '.php' extension is added
 *
 * Example: 'Status_Sentry_Hook_Manager' becomes 'includes/hooks/class-hook-manager.php'
 *
 * @since 1.0.0
 */
spl_autoload_register(function ($class_name) {
    // Check if the class should be loaded by this autoloader
    if (strpos($class_name, 'Status_Sentry_') !== 0) {
        return;
    }

    // Convert class name to file path
    $class_file = str_replace('Status_Sentry_', '', $class_name);
    $class_file = str_replace('_', '-', $class_file);
    $class_file = strtolower($class_file);

    // Check in includes directory
    $file = STATUS_SENTRY_PLUGIN_DIR . 'includes/class-' . $class_file . '.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }

    // Check in subdirectories
    $directories = ['hooks', 'data', 'db', 'admin', 'monitoring'];
    foreach ($directories as $dir) {
        $file = STATUS_SENTRY_PLUGIN_DIR . 'includes/' . $dir . '/class-' . $class_file . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

/**
 * Begins execution of the plugin.
 *
 * This function initializes the plugin by creating an instance of the main
 * Status_Sentry class and calling its run method. This is the entry point
 * for the plugin's functionality.
 *
 * @since 1.0.0
 * @see Status_Sentry::run()
 */
function run_status_sentry() {
    $plugin = new Status_Sentry();
    $plugin->run();
}

// Run the plugin
run_status_sentry();

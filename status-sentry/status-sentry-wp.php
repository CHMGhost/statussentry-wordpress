<?php
declare(strict_types=1);

/**
 * Status Sentry WP - Advanced WordPress Monitoring Plugin
 *
 * This is the main plugin file that registers hooks, defines constants,
 * and starts the plugin execution.
 *
 * @link              https://github.com/status-sentry/status-sentry-wp
 * @since             1.0.0
 * @version           1.3.0
 * @package           Status_Sentry
 *
 * @wordpress-plugin
 * Plugin Name:       Status Sentry WP
 * Plugin URI:        https://github.com/status-sentry/status-sentry-wp
 * Description:       A comprehensive WordPress plugin for monitoring site health, capturing performance data, and detecting plugin conflicts with minimal impact.
 * Version:           1.3.0
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
define('STATUS_SENTRY_VERSION', '1.3.0');                         // Plugin version for cache busting and version checks
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
 * Verify database tables after activation.
 *
 * This function checks if the required database tables were created successfully
 * during plugin activation and logs any issues.
 *
 * @since 1.5.0
 */
function verify_status_sentry_tables() {
    global $wpdb;

    // Check if the events table exists
    $events_table = $wpdb->prefix . 'status_sentry_events';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$events_table}'") != $events_table) {
        error_log("Status Sentry: WARNING - Events table {$events_table} does not exist after activation");

        // Try to create the table again
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/db/class-status-sentry-db-migrator.php';
        $migrator = new Status_Sentry_DB_Migrator();
        $result = $migrator->run_migrations();

        error_log("Status Sentry: Attempted to run migrations again, result: " . ($result ? 'success' : 'failure'));

        // Check if the table exists now
        if ($wpdb->get_var("SHOW TABLES LIKE '{$events_table}'") != $events_table) {
            error_log("Status Sentry: CRITICAL - Events table {$events_table} still does not exist after retry");

            // Add an admin notice
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__('Status Sentry: Critical error - Events table could not be created. Please check your database permissions and try deactivating and reactivating the plugin.', 'status-sentry-wp');
                echo '</p></div>';
            });
        } else {
            error_log("Status Sentry: Events table {$events_table} created successfully on retry");
        }
    } else {
        error_log("Status Sentry: Events table {$events_table} exists");
    }
}

// Run table verification after plugin activation
add_action('admin_init', 'verify_status_sentry_tables');

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
spl_autoload_register(function (string $class_name) {
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
    $directories = ['hooks', 'data', 'db', 'admin', 'monitoring', 'benchmarking'];
    foreach ($directories as $dir) {
        $file = STATUS_SENTRY_PLUGIN_DIR . 'includes/' . $dir . '/class-' . $class_file . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

/**
 * Load the output buffer class.
 *
 * This class is used to capture and handle output before headers are sent.
 *
 * @since 1.5.0
 */
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/class-status-sentry-output-buffer.php';

/**
 * Load the dashboard REST API controller.
 *
 * This class handles the REST API endpoints for the dashboard.
 *
 * @since 1.5.0
 */
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/admin/class-status-sentry-dashboard-controller.php';

/**
 * Load the benchmark admin page.
 *
 * This file adds a benchmark page to the WordPress admin.
 *
 * @since 1.6.0
 */
require_once STATUS_SENTRY_PLUGIN_DIR . 'admin-benchmark.php';

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
    // Start output buffering to prevent warnings from being output before headers
    $buffer = Status_Sentry_Output_Buffer::get_instance();
    $buffer->start();

    try {
        $plugin = new Status_Sentry();
        $plugin->run();
    } catch (Exception $e) {
        error_log('Status Sentry: Error running plugin - ' . $e->getMessage());
    }

    // Register REST API routes
    add_action('rest_api_init', function() {
        // Make sure the WP_REST_Controller class is loaded
        if (!class_exists('WP_REST_Controller')) {
            require_once ABSPATH . 'wp-includes/rest-api/endpoints/class-wp-rest-controller.php';
        }

        // Always use the real dashboard controller which has internal error handling
        $dashboard_controller = new Status_Sentry_Dashboard_Controller();
        $dashboard_controller->register_routes();
        error_log('Status Sentry: Dashboard controller routes registered');
    });

    // End output buffering after plugin initialization
    // Use an earlier hook to ensure output buffer is ended before redirects
    add_action('admin_init', function() use ($buffer) {
        $buffer->end();
    }, 1); // Priority 1 to run early

    // Also end on wp_loaded as a fallback
    add_action('wp_loaded', function() use ($buffer) {
        $buffer->end();
    });
}

// Run the plugin
run_status_sentry();

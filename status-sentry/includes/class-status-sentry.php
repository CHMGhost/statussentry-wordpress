<?php
/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes
 */
class Status_Sentry {

    /**
     * The hook manager instance.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Status_Sentry_Hook_Manager    $hook_manager    Manages the plugin's hooks.
     */
    protected $hook_manager;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->load_dependencies();
        $this->set_locale();
        $this->init_hook_manager();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        // Hook management
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/hooks/class-status-sentry-hook-config.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/hooks/class-status-sentry-hook-manager.php';

        // Data pipeline
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-data-capture.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-data-filter.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-sampling-manager.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-event-queue.php';

        // Event processing
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-event-processor.php';

        // Database
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/db/class-status-sentry-query-cache.php';

        // Monitoring
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-baseline.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-self-monitor.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-resource-manager.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-task-state-manager.php';

        // Scheduler
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/class-status-sentry-scheduler.php';

        // Admin
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/admin/class-status-sentry-admin.php';
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {
        add_action('plugins_loaded', function() {
            load_plugin_textdomain(
                'status-sentry-wp',
                false,
                dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
            );
        });
    }

    /**
     * Initialize the hook manager.
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_hook_manager() {
        $hook_config = new Status_Sentry_Hook_Config();
        $this->hook_manager = new Status_Sentry_Hook_Manager($hook_config);
    }

    /**
     * Run the plugin.
     *
     * @since    1.0.0
     */
    public function run() {
        // Initialize admin
        $admin = new Status_Sentry_Admin();
        $admin->init();

        // Initialize scheduler
        Status_Sentry_Scheduler::init();

        // Register cleanup hooks
        add_action('status_sentry_cleanup_expired_cache', ['Status_Sentry_Scheduler', 'cleanup_expired_cache']);
        add_action('status_sentry_cleanup_expired_task_state', ['Status_Sentry_Scheduler', 'cleanup_expired_task_state']);

        // Register hooks
        $this->hook_manager->register_hooks();
    }
}

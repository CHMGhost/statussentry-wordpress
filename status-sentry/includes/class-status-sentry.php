<?php
declare(strict_types=1);

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
     * The monitoring manager instance.
     *
     * @since    1.3.0
     * @access   protected
     * @var      Status_Sentry_Monitoring_Manager    $monitoring_manager    Manages the plugin's monitoring system.
     */
    protected $monitoring_manager;

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

        // Centralized Monitoring System Interfaces (1.3.0)
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/interface-status-sentry-monitoring.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/interface-status-sentry-monitoring-handler.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-monitoring-event.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-monitoring-manager.php';

        // Monitoring Components
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-baseline.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-self-monitor.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-resource-manager.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-task-state-manager.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-conflict-detector.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-cron-logger.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-health-checker.php';

        // Scheduler
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/class-status-sentry-scheduler.php';

        // Admin
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/admin/class-status-sentry-admin.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/admin/class-status-sentry-setup-wizard.php';
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

        // Initialize monitoring manager
        $this->init_monitoring_manager();

        // Register hooks
        $this->hook_manager->register_hooks();
    }

    /**
     * Initialize the monitoring manager.
     *
     * @since    1.3.0
     * @access   private
     */
    private function init_monitoring_manager() {
        // Get the monitoring manager instance
        $this->monitoring_manager = Status_Sentry_Monitoring_Manager::get_instance();

        // Register monitoring components
        $conflict_detector = new Status_Sentry_Conflict_Detector();
        $this->monitoring_manager->register_component('conflict_detector', $conflict_detector);

        // Register self-monitor component
        $self_monitor = new Status_Sentry_Self_Monitor();
        $this->monitoring_manager->register_component('self_monitor', $self_monitor);

        // Register resource manager component
        $resource_manager = new Status_Sentry_Resource_Manager();
        $this->monitoring_manager->register_component('resource_manager', $resource_manager);

        // Register baseline component
        $baseline = new Status_Sentry_Baseline();
        $this->monitoring_manager->register_component('baseline', $baseline);

        // Register task state manager component
        $task_state_manager = new Status_Sentry_Task_State_Manager();
        $this->monitoring_manager->register_component('task_state_manager', $task_state_manager);

        // Register cron logger component
        $cron_logger = new Status_Sentry_Cron_Logger();
        $this->monitoring_manager->register_component('cron_logger', $cron_logger);

        // Register health checker component
        $health_checker = new Status_Sentry_Health_Checker();
        $this->monitoring_manager->register_component('health_checker', $health_checker);

        // Add monitoring event hook for the scheduler
        add_action('status_sentry_before_task_execution', [$this, 'emit_task_start_event'], 10, 2);
        add_action('status_sentry_after_task_execution', [$this, 'emit_task_end_event'], 10, 3);

        // Register a health check event
        add_action('status_sentry_monitoring_health_check', [$this, 'emit_health_check_event']);

        // Schedule the health check event if not already scheduled
        if (!wp_next_scheduled('status_sentry_monitoring_health_check')) {
            wp_schedule_event(time(), 'hourly', 'status_sentry_monitoring_health_check');
        }
    }

    /**
     * Emit a task start event.
     *
     * @since    1.3.0
     * @param    string    $task_name    The name of the task.
     * @param    string    $tier         The tier of the task.
     * @return   void
     */
    public function emit_task_start_event(string $task_name, string $tier): void {
        $this->monitoring_manager->emit(
            Status_Sentry_Monitoring_Event::TYPE_INFO,
            'scheduler',
            'task_start',
            sprintf('Task %s started', $task_name),
            [
                'task_name' => $task_name,
                'tier' => $tier,
                'timestamp' => microtime(true),
                'memory_start' => memory_get_usage(),
            ],
            Status_Sentry_Monitoring_Event::PRIORITY_LOW
        );
    }

    /**
     * Emit a task end event.
     *
     * @since    1.3.0
     * @param    string    $task_name       The name of the task.
     * @param    string    $tier            The tier of the task.
     * @param    array     $execution_data  The execution data.
     * @return   void
     */
    public function emit_task_end_event(string $task_name, string $tier, array $execution_data): void {
        $event_type = Status_Sentry_Monitoring_Event::TYPE_INFO;
        $priority = Status_Sentry_Monitoring_Event::PRIORITY_LOW;

        // Check if the task failed
        if (isset($execution_data['status']) && $execution_data['status'] === 'failed') {
            $event_type = Status_Sentry_Monitoring_Event::TYPE_ERROR;
            $priority = Status_Sentry_Monitoring_Event::PRIORITY_HIGH;
        }

        // Check if the task exceeded its resource budget
        if (isset($execution_data['budget_exceeded']) && $execution_data['budget_exceeded']) {
            $event_type = Status_Sentry_Monitoring_Event::TYPE_WARNING;
            $priority = Status_Sentry_Monitoring_Event::PRIORITY_NORMAL;
        }

        $this->monitoring_manager->emit(
            $event_type,
            'scheduler',
            'task_end',
            sprintf('Task %s ended with status %s', $task_name, $execution_data['status'] ?? 'unknown'),
            array_merge(
                [
                    'task_name' => $task_name,
                    'tier' => $tier,
                    'timestamp' => microtime(true),
                ],
                $execution_data
            ),
            $priority
        );
    }

    /**
     * Emit a health check event.
     *
     * @since    1.3.0
     * @return   void
     */
    public function emit_health_check_event(): void {
        global $wpdb;

        // Collect system information
        $system_info = [
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
            'php_version' => phpversion(),
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => STATUS_SENTRY_VERSION,
            'db_queries' => $wpdb->num_queries,
            'active_plugins' => count(get_option('active_plugins')),
            'timestamp' => microtime(true),
        ];

        $this->monitoring_manager->emit(
            Status_Sentry_Monitoring_Event::TYPE_HEALTH,
            'system',
            'health_check',
            'System health check',
            $system_info,
            Status_Sentry_Monitoring_Event::PRIORITY_LOW
        );
    }
}

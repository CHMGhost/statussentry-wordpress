<?php
/**
 * Resource manager class.
 *
 * @since      1.1.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */

/**
 * Resource manager class.
 *
 * This class manages resource usage for the plugin, enforcing budgets for
 * memory, execution time, and database operations. It helps prevent the
 * plugin from overloading the server or causing performance issues.
 *
 * Key responsibilities:
 * - Track resource usage for tasks
 * - Enforce resource budgets
 * - Provide graceful degradation when resources are constrained
 * - Abort operations that exceed resource limits
 * - Trigger garbage collection when needed
 * - Monitor CPU usage and system load
 * - Provide adaptive resource allocation
 * - Implement the Monitoring_Interface for centralized monitoring
 *
 * Memory Management:
 * - Default memory budgets are set per tier (critical: 10MB, standard: 20MB, intensive: 50MB, report: 100MB)
 * - Garbage collection is triggered when memory usage exceeds 80% of PHP memory_limit
 * - GC is forced after specific tasks like 'cleanup' and 'process_queue'
 * - Multiple GC cycles (default: 3) are run to maximize memory recovery
 *
 * CPU Load Monitoring:
 * - Default CPU threshold is 70% (0.7)
 * - System load combines CPU, memory, and database activity metrics
 * - Tasks are aborted when system is overloaded
 * - Scheduler adds delays proportional to system load
 *
 * Configuration:
 * - All settings can be modified via WordPress filters:
 *   - 'status_sentry_resource_budgets' - Modify memory/time/query budgets per tier
 *   - 'status_sentry_gc_settings' - Modify garbage collection behavior
 *   - 'status_sentry_cpu_threshold' - Change the CPU load threshold
 *
 * @since      1.1.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Resource_Manager implements Status_Sentry_Monitoring_Interface, Status_Sentry_Monitoring_Handler_Interface {

    /**
     * The baseline instance.
     *
     * @since    1.1.0
     * @access   private
     * @var      Status_Sentry_Baseline    $baseline    The baseline instance.
     */
    private $baseline;

    /**
     * Resource budgets for different tiers.
     *
     * @since    1.1.0
     * @access   private
     * @var      array    $budgets    Resource budgets for different tiers.
     */
    private $budgets;

    /**
     * Garbage collection settings.
     *
     * @since    1.2.0
     * @access   private
     * @var      array    $gc_settings    Garbage collection settings.
     */
    private $gc_settings;

    /**
     * CPU load threshold.
     *
     * @since    1.2.0
     * @access   private
     * @var      float    $cpu_threshold    CPU load threshold.
     */
    private $cpu_threshold;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.1.0
     */
    public function __construct() {
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-baseline.php';
        $this->baseline = new Status_Sentry_Baseline();

        // Set default budgets
        $this->budgets = [
            'critical' => [
                'memory' => 10 * 1024 * 1024, // 10 MB
                'time' => 10, // 10 seconds
                'db_queries' => 50,
            ],
            'standard' => [
                'memory' => 20 * 1024 * 1024, // 20 MB
                'time' => 20, // 20 seconds
                'db_queries' => 100,
            ],
            'intensive' => [
                'memory' => 50 * 1024 * 1024, // 50 MB
                'time' => 45, // 45 seconds
                'db_queries' => 500,
            ],
            'report' => [
                'memory' => 100 * 1024 * 1024, // 100 MB
                'time' => 120, // 120 seconds
                'db_queries' => 1000,
            ],
        ];

        // Set default garbage collection settings
        $this->gc_settings = [
            'memory_threshold' => 0.8, // Trigger GC when memory usage is above 80%
            'force_after_tasks' => ['cleanup', 'process_queue'], // Force GC after these tasks
            'cycles' => 3, // Number of GC cycles to run
        ];

        // Set default CPU threshold
        $this->cpu_threshold = 0.7; // 70% CPU load threshold

        // Allow budgets to be filtered
        $this->budgets = apply_filters('status_sentry_resource_budgets', $this->budgets);

        // Allow GC settings to be filtered
        $this->gc_settings = apply_filters('status_sentry_gc_settings', $this->gc_settings);

        // Allow CPU threshold to be filtered
        $this->cpu_threshold = apply_filters('status_sentry_cpu_threshold', $this->cpu_threshold);
    }

    /**
     * Check if a task should continue based on resource usage.
     *
     * @since    1.1.0
     * @param    string    $tier           The tier of the task.
     * @param    float     $start_time     The start time of the task (microtime).
     * @param    int       $memory_start   The memory usage at the start of the task.
     * @param    int       $db_queries     Optional. The number of database queries performed.
     * @return   bool                      Whether the task should continue.
     */
    public function should_continue($tier, $start_time, $memory_start, $db_queries = 0) {
        // Get the budget for this tier
        $budget = $this->get_budget($tier);

        // Check memory usage
        $memory_usage = memory_get_usage() - $memory_start;
        if ($memory_usage > $budget['memory']) {
            error_log(sprintf(
                'Status Sentry: Task exceeded memory budget (%s tier) - Used: %d MB, Budget: %d MB',
                $tier,
                $memory_usage / (1024 * 1024),
                $budget['memory'] / (1024 * 1024)
            ));
            return false;
        }

        // Check execution time
        $execution_time = microtime(true) - $start_time;
        if ($execution_time > $budget['time']) {
            error_log(sprintf(
                'Status Sentry: Task exceeded time budget (%s tier) - Used: %.2f seconds, Budget: %.2f seconds',
                $tier,
                $execution_time,
                $budget['time']
            ));
            return false;
        }

        // Check database queries
        if ($db_queries > $budget['db_queries']) {
            error_log(sprintf(
                'Status Sentry: Task exceeded database query budget (%s tier) - Used: %d queries, Budget: %d queries',
                $tier,
                $db_queries,
                $budget['db_queries']
            ));
            return false;
        }

        // Check system load
        if ($this->is_system_overloaded()) {
            error_log(sprintf(
                'Status Sentry: Task aborted due to system overload (%s tier)',
                $tier
            ));
            return false;
        }

        return true;
    }

    /**
     * Get the resource budget for a tier.
     *
     * @since    1.1.0
     * @param    string    $tier    The tier of the task.
     * @return   array              The resource budget.
     */
    public function get_budget($tier) {
        if (isset($this->budgets[$tier])) {
            return $this->budgets[$tier];
        }

        // Default to standard tier if the specified tier doesn't exist
        return $this->budgets['standard'];
    }

    /**
     * Check if the system is overloaded.
     *
     * @since    1.1.0
     * @access   private
     * @return   bool    Whether the system is overloaded.
     */
    private function is_system_overloaded() {
        // Check memory usage
        $memory_limit = $this->get_memory_limit_in_bytes();
        $memory_usage = memory_get_usage();
        $memory_usage_percent = ($memory_usage / $memory_limit);

        if ($memory_usage_percent > 0.9) {
            // If memory usage is above 90%, consider the system overloaded
            return true;
        }

        // Check if we're in a high-traffic situation
        if ($this->is_high_traffic()) {
            return true;
        }

        return false;
    }

    /**
     * Check if the site is experiencing high traffic.
     *
     * @since    1.1.0
     * @access   private
     * @return   bool    Whether the site is experiencing high traffic.
     */
    private function is_high_traffic() {
        global $wpdb;

        // Get the number of requests in the last minute
        $table_name = $wpdb->prefix . 'status_sentry_events';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $one_minute_ago = date('Y-m-d H:i:s', time() - 60);
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE event_time > %s",
                $one_minute_ago
            ));

            // If there are more than 100 requests in the last minute, consider it high traffic
            return $count > 100;
        }

        return false;
    }

    /**
     * Get the PHP memory limit in bytes.
     *
     * @since    1.1.0
     * @access   private
     * @return   int    The memory limit in bytes.
     */
    private function get_memory_limit_in_bytes() {
        $memory_limit = ini_get('memory_limit');

        // Convert memory limit to bytes
        $unit = strtolower(substr($memory_limit, -1));
        $value = (int) substr($memory_limit, 0, -1);

        switch ($unit) {
            case 'g':
                $value *= 1024;
                // Fall through
            case 'm':
                $value *= 1024;
                // Fall through
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Trigger garbage collection.
     *
     * This method triggers PHP's garbage collection mechanism to free up memory.
     * It can be called manually or automatically when memory usage exceeds a threshold.
     *
     * @since    1.2.0
     * @param    bool     $force    Whether to force garbage collection regardless of memory usage.
     * @return   bool               Whether garbage collection was triggered.
     */
    public function trigger_gc($force = false) {
        // Check if we should trigger garbage collection
        if (!$force) {
            $memory_limit = $this->get_memory_limit_in_bytes();
            $memory_usage = memory_get_usage();
            $memory_usage_percent = ($memory_usage / $memory_limit);

            if ($memory_usage_percent < $this->gc_settings['memory_threshold']) {
                // Memory usage is below threshold, no need to trigger GC
                return false;
            }
        }

        // Log garbage collection
        error_log(sprintf(
            'Status Sentry: Triggering garbage collection - Memory usage: %.2f MB (%.1f%%)',
            memory_get_usage() / (1024 * 1024),
            (memory_get_usage() / $this->get_memory_limit_in_bytes()) * 100
        ));

        // Run multiple cycles of garbage collection
        $cycles = $this->gc_settings['cycles'];
        $freed_memory = 0;

        for ($i = 0; $i < $cycles; $i++) {
            $memory_before = memory_get_usage();

            // Trigger garbage collection
            if (gc_enabled()) {
                gc_collect_cycles();
            }

            $memory_after = memory_get_usage();
            $freed = $memory_before - $memory_after;
            $freed_memory += $freed;

            // Log cycle results
            error_log(sprintf(
                'Status Sentry: GC cycle %d - Freed %.2f KB',
                $i + 1,
                $freed / 1024
            ));

            // If we didn't free much memory, no need to continue
            if ($freed < 1024 * 10) { // Less than 10 KB
                break;
            }
        }

        // Log total freed memory
        error_log(sprintf(
            'Status Sentry: Garbage collection completed - Total freed: %.2f MB',
            $freed_memory / (1024 * 1024)
        ));

        return true;
    }

    /**
     * Check if garbage collection should be triggered after a task.
     *
     * @since    1.2.0
     * @param    string    $task_name    The name of the task.
     * @return   bool                    Whether garbage collection should be triggered.
     */
    public function should_trigger_gc_after_task($task_name) {
        // Check if this task is in the force_after_tasks list
        if (in_array($task_name, $this->gc_settings['force_after_tasks'])) {
            return true;
        }

        // Check memory usage
        $memory_limit = $this->get_memory_limit_in_bytes();
        $memory_usage = memory_get_usage();
        $memory_usage_percent = ($memory_usage / $memory_limit);

        return $memory_usage_percent >= $this->gc_settings['memory_threshold'];
    }

    /**
     * Get the current CPU load.
     *
     * @since    1.2.0
     * @return   float|false    The current CPU load as a percentage (0-1) or false if not available.
     */
    public function get_cpu_load() {
        // Try to get CPU load from system
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if (is_array($load) && isset($load[0])) {
                // Get number of CPU cores
                $cores = $this->get_cpu_cores();

                // Calculate load per core
                return $load[0] / $cores;
            }
        }

        return false;
    }

    /**
     * Get the CPU threshold.
     *
     * @since    1.5.0
     * @return   float    The CPU threshold (0-1).
     */
    public function get_cpu_threshold() {
        return $this->cpu_threshold;
    }

    /**
     * Get the current system load.
     *
     * This method returns a normalized system load value between 0 and 1,
     * taking into account CPU load, memory usage, and database activity.
     *
     * @since    1.4.0
     * @return   float    The current system load as a value between 0 and 1.
     */
    public function get_system_load() {
        // Start with a base load of 0
        $load = 0;

        // Add CPU load component (weight: 40%)
        $cpu_load = $this->get_cpu_load();
        if ($cpu_load !== false) {
            // Cap CPU load at 1.0 for calculation purposes
            $cpu_load = min(1.0, $cpu_load);
            $load += $cpu_load * 0.4;
        }

        // Add memory usage component (weight: 40%)
        $memory_limit = $this->get_memory_limit_in_bytes();
        $memory_usage = memory_get_usage();
        $memory_usage_percent = ($memory_usage / $memory_limit);
        $load += $memory_usage_percent * 0.4;

        // Add database activity component (weight: 20%)
        if ($this->is_high_traffic()) {
            $load += 0.2;
        }

        // Ensure the load is between 0 and 1
        $load = max(0, min(1, $load));

        return $load;
    }

    /**
     * Get the number of CPU cores.
     *
     * @since    1.2.0
     * @access   private
     * @return   int    The number of CPU cores.
     */
    private function get_cpu_cores() {
        // Default to 1 core
        $cores = 1;

        // Try to get the number of cores from /proc/cpuinfo
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $cores = count($matches[0]);
        }

        // Allow the number of cores to be filtered
        return apply_filters('status_sentry_cpu_cores', $cores);
    }

    /**
     * Initialize the monitoring component.
     *
     * @since    1.3.0
     * @return   void
     */
    public function init() {
        // Nothing to initialize here, as the constructor already sets up everything
    }

    /**
     * Register event handlers with the monitoring manager.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Manager    $manager    The monitoring manager instance.
     * @return   void
     */
    public function register_handlers($manager) {
        $manager->register_handler($this);
    }

    /**
     * Process a monitoring event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The monitoring event to process.
     * @return   void
     */
    public function process_event($event) {
        // This method is called by the monitoring manager when an event is dispatched
        // We'll handle events in the handle() method instead
    }

    /**
     * Get the monitoring component's status.
     *
     * @since    1.3.0
     * @return   array    The component status as an associative array.
     */
    public function get_status() {
        return [
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
            'memory_limit' => $this->get_memory_limit_in_bytes(),
            'memory_usage_percent' => (memory_get_usage() / $this->get_memory_limit_in_bytes()) * 100,
            'cpu_load' => $this->get_cpu_load(),
            'cpu_threshold' => $this->cpu_threshold,
            'high_traffic' => $this->is_high_traffic(),
            'system_overloaded' => $this->is_system_overloaded(),
        ];
    }

    /**
     * Get the monitoring component's configuration.
     *
     * @since    1.3.0
     * @return   array    The component configuration as an associative array.
     */
    public function get_config() {
        return [
            'budgets' => $this->budgets,
            'gc_settings' => $this->gc_settings,
            'cpu_threshold' => $this->cpu_threshold,
        ];
    }

    /**
     * Update the monitoring component's configuration.
     *
     * @since    1.3.0
     * @param    array    $config    The new configuration as an associative array.
     * @return   bool                Whether the configuration was successfully updated.
     */
    public function update_config($config) {
        $updated = false;

        // Update budgets if provided
        if (isset($config['budgets']) && is_array($config['budgets'])) {
            $this->budgets = array_merge($this->budgets, $config['budgets']);
            $updated = true;
        }

        // Update GC settings if provided
        if (isset($config['gc_settings']) && is_array($config['gc_settings'])) {
            $this->gc_settings = array_merge($this->gc_settings, $config['gc_settings']);
            $updated = true;
        }

        // Update CPU threshold if provided
        if (isset($config['cpu_threshold'])) {
            $threshold = floatval($config['cpu_threshold']);
            if ($threshold >= 0.1 && $threshold <= 1.0) {
                $this->cpu_threshold = $threshold;
                $updated = true;
            }
        }

        return $updated;
    }

    /**
     * Get the handler's priority.
     *
     * @since    1.3.0
     * @return   int    The handler's priority (0-100).
     */
    public function get_priority() {
        return 60; // Medium-high priority
    }

    /**
     * Get the event types this handler can process.
     *
     * @since    1.3.0
     * @return   array    An array of event types.
     */
    public function get_handled_types() {
        return [
            Status_Sentry_Monitoring_Event::TYPE_PERFORMANCE,
            Status_Sentry_Monitoring_Event::TYPE_HEALTH,
        ];
    }

    /**
     * Check if this handler can handle the given event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The event to check.
     * @return   bool                                        Whether this handler can handle the event.
     */
    public function can_handle($event) {
        // We handle performance events related to resource usage
        if ($event->get_type() === Status_Sentry_Monitoring_Event::TYPE_PERFORMANCE) {
            $data = $event->get_data();
            return isset($data['memory_usage']) || isset($data['execution_time']);
        }

        // We handle health check events
        if ($event->get_type() === Status_Sentry_Monitoring_Event::TYPE_HEALTH &&
            $event->get_context() === 'health_check') {
            return true;
        }

        return false;
    }

    /**
     * Handle a monitoring event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The event to handle.
     * @return   bool                                        Whether the event was successfully handled.
     */
    public function handle($event) {
        $data = $event->get_data();

        // Handle performance events
        if ($event->get_type() === Status_Sentry_Monitoring_Event::TYPE_PERFORMANCE) {
            // Check if memory usage is high
            if (isset($data['memory_usage'])) {
                $memory_limit = $this->get_memory_limit_in_bytes();
                $memory_usage_percent = ($data['memory_usage'] / $memory_limit);

                if ($memory_usage_percent > $this->gc_settings['memory_threshold']) {
                    // Memory usage is high, trigger garbage collection
                    $this->trigger_gc();
                    return true;
                }
            }

            return false; // No action needed
        }

        // Handle health check events
        if ($event->get_type() === Status_Sentry_Monitoring_Event::TYPE_HEALTH &&
            $event->get_context() === 'health_check') {

            // Check system health
            $status = $this->get_status();

            // If system is overloaded, emit a warning event
            if ($status['system_overloaded']) {
                $manager = Status_Sentry_Monitoring_Manager::get_instance();

                $manager->emit(
                    Status_Sentry_Monitoring_Event::TYPE_WARNING,
                    'resource_manager',
                    'system_overload',
                    'System is overloaded',
                    $status,
                    Status_Sentry_Monitoring_Event::PRIORITY_HIGH
                );

                return true;
            }

            return false; // No action needed
        }

        return false;
    }
}

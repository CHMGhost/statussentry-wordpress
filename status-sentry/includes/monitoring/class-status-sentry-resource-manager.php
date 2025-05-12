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
 *
 * @since      1.1.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Resource_Manager {

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
}

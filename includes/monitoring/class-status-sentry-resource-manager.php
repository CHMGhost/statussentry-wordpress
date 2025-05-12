<?php
/**
 * Resource Manager Component
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */

/**
 * Resource Manager Component
 *
 * This class implements the resource management component for Status Sentry.
 * It monitors and manages resource usage by the plugin.
 *
 * Key responsibilities:
 * - Monitor memory usage
 * - Monitor CPU usage
 * - Implement resource budgets for tasks
 * - Trigger garbage collection when needed
 * - Throttle resource-intensive operations
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Resource_Manager implements Status_Sentry_Monitoring_Interface {

    /**
     * Resource budgets for different task types.
     *
     * @since    1.3.0
     * @access   private
     * @var      array    $budgets    Resource budgets.
     */
    private $budgets;

    /**
     * Garbage collection settings.
     *
     * @since    1.3.0
     * @access   private
     * @var      array    $gc_settings    Garbage collection settings.
     */
    private $gc_settings;

    /**
     * CPU load threshold.
     *
     * @since    1.3.0
     * @access   private
     * @var      float    $cpu_threshold    CPU load threshold.
     */
    private $cpu_threshold;

    /**
     * Configuration options for the resource manager component.
     *
     * @since    1.3.0
     * @access   private
     * @var      array    $config    Configuration options.
     */
    private $config;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.3.0
     */
    public function __construct() {
        // Default resource budgets (in MB and seconds)
        $this->budgets = [
            'critical' => [
                'memory' => 32,
                'time' => 10,
            ],
            'standard' => [
                'memory' => 64,
                'time' => 30,
            ],
            'intensive' => [
                'memory' => 128,
                'time' => 60,
            ],
            'report' => [
                'memory' => 256,
                'time' => 300,
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

        // Default configuration
        $this->config = [
            'enabled' => true,
            'monitor_memory' => true,
            'monitor_cpu' => true,
            'enforce_budgets' => true,
            'auto_gc' => true,
        ];

        // Load saved configuration
        $saved_config = get_option('status_sentry_resource_manager_config', []);
        if (!empty($saved_config)) {
            $this->config = array_merge($this->config, $saved_config);
        }

        // Allow budgets to be filtered
        $this->budgets = apply_filters('status_sentry_resource_budgets', $this->budgets);

        // Allow GC settings to be filtered
        $this->gc_settings = apply_filters('status_sentry_gc_settings', $this->gc_settings);

        // Allow CPU threshold to be filtered
        $this->cpu_threshold = apply_filters('status_sentry_cpu_threshold', $this->cpu_threshold);
    }

    /**
     * Initialize the monitoring component.
     *
     * @since    1.3.0
     * @return   void
     */
    public function init() {
        // Register hooks for resource monitoring
        if ($this->config['monitor_memory']) {
            add_action('status_sentry_before_task_execution', [$this, 'check_memory_before_task'], 5, 2);
            add_action('status_sentry_after_task_execution', [$this, 'check_memory_after_task'], 5, 3);
        }

        // Register hooks for garbage collection
        if ($this->config['auto_gc']) {
            add_action('status_sentry_after_task_execution', [$this, 'maybe_run_garbage_collection'], 999, 3);
        }
    }

    /**
     * Register event handlers with the monitoring manager.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Manager    $manager    The monitoring manager instance.
     * @return   void
     */
    public function register_handlers($manager) {
        // Register handlers for resource events
        $manager->register_handler('resource_usage', [$this, 'process_event']);
        $manager->register_handler('resource_limit', [$this, 'process_event']);
    }

    /**
     * Process a monitoring event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The monitoring event to process.
     * @return   void
     */
    public function process_event($event) {
        // Skip processing if resource management is disabled
        if (!$this->config['enabled']) {
            return;
        }

        $type = $event->get_type();
        $data = $event->get_data();

        if ($type === 'resource_usage') {
            // Process resource usage event
            if (isset($data['memory_usage']) && $this->config['monitor_memory']) {
                $this->check_memory_usage($data['memory_usage'], $data['task_id'] ?? 'unknown');
            }

            if (isset($data['cpu_usage']) && $this->config['monitor_cpu']) {
                $this->check_cpu_usage($data['cpu_usage'], $data['task_id'] ?? 'unknown');
            }
        } elseif ($type === 'resource_limit') {
            // Process resource limit event
            if (isset($data['limit_type'], $data['task_id'])) {
                $this->handle_resource_limit($data['limit_type'], $data['task_id'], $data['value'] ?? 0);
            }
        }
    }

    /**
     * Get the monitoring component's status.
     *
     * @since    1.3.0
     * @return   array    The component status as an associative array.
     */
    public function get_status() {
        return [
            'enabled' => $this->config['enabled'],
            'memory_usage' => $this->get_memory_usage(),
            'memory_limit' => $this->get_memory_limit(),
            'cpu_usage' => $this->get_cpu_usage(),
            'cpu_threshold' => $this->cpu_threshold,
            'budgets' => $this->budgets,
            'gc_settings' => $this->gc_settings,
        ];
    }

    /**
     * Get the monitoring component's configuration.
     *
     * @since    1.3.0
     * @return   array    The component configuration as an associative array.
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Update the monitoring component's configuration.
     *
     * @since    1.3.0
     * @param    array    $config    The new configuration as an associative array.
     * @return   bool                Whether the configuration was successfully updated.
     */
    public function update_config($config) {
        // Update configuration
        $this->config = array_merge($this->config, $config);

        // Save configuration
        update_option('status_sentry_resource_manager_config', $this->config);

        return true;
    }

    /**
     * Check memory usage before task execution.
     *
     * @since    1.3.0
     * @param    string    $task_id    The task ID.
     * @param    array     $args       The task arguments.
     * @return   void
     */
    public function check_memory_before_task($task_id, $args) {
        // Store initial memory usage
        $memory_usage = memory_get_usage();
        set_transient('status_sentry_memory_before_' . $task_id, $memory_usage, 3600);

        // Check if we have enough memory for this task
        if ($this->config['enforce_budgets']) {
            $task_type = $this->get_task_type($task_id);
            $budget = $this->get_budget($task_type);
            $available_memory = $this->get_available_memory();

            if ($available_memory < $budget['memory'] * 1024 * 1024) {
                // Not enough memory available for this task
                $this->handle_resource_limit('memory', $task_id, $available_memory);
                
                // Create a resource limit event
                $event = new Status_Sentry_Monitoring_Event(
                    'resource_limit',
                    'resource_manager',
                    'scheduler',
                    sprintf('Insufficient memory for task %s: %d MB available, %d MB required', $task_id, $available_memory / (1024 * 1024), $budget['memory']),
                    [
                        'limit_type' => 'memory',
                        'task_id' => $task_id,
                        'value' => $available_memory,
                        'budget' => $budget['memory'] * 1024 * 1024,
                    ],
                    Status_Sentry_Monitoring_Event::PRIORITY_HIGH
                );

                // Dispatch the event
                Status_Sentry_Monitoring_Manager::get_instance()->dispatch($event);
            }
        }
    }

    /**
     * Check memory usage after task execution.
     *
     * @since    1.3.0
     * @param    string    $task_id    The task ID.
     * @param    array     $args       The task arguments.
     * @param    bool      $success    Whether the task was successful.
     * @return   void
     */
    public function check_memory_after_task($task_id, $args, $success) {
        // Get initial memory usage
        $initial_memory = get_transient('status_sentry_memory_before_' . $task_id);
        delete_transient('status_sentry_memory_before_' . $task_id);

        if ($initial_memory !== false) {
            $current_memory = memory_get_usage();
            $memory_used = $current_memory - $initial_memory;

            // Create a resource usage event
            $event = new Status_Sentry_Monitoring_Event(
                'resource_usage',
                'resource_manager',
                'scheduler',
                sprintf('Task %s used %d MB of memory', $task_id, $memory_used / (1024 * 1024)),
                [
                    'task_id' => $task_id,
                    'memory_usage' => $memory_used,
                    'initial_memory' => $initial_memory,
                    'final_memory' => $current_memory,
                ],
                Status_Sentry_Monitoring_Event::PRIORITY_NORMAL
            );

            // Dispatch the event
            Status_Sentry_Monitoring_Manager::get_instance()->dispatch($event);
        }
    }

    /**
     * Maybe run garbage collection after task execution.
     *
     * @since    1.3.0
     * @param    string    $task_id    The task ID.
     * @param    array     $args       The task arguments.
     * @param    bool      $success    Whether the task was successful.
     * @return   void
     */
    public function maybe_run_garbage_collection($task_id, $args, $success) {
        // Check if we should force GC after this task
        $force_gc = in_array($task_id, $this->gc_settings['force_after_tasks']);

        // Check if memory usage is above threshold
        $memory_usage = $this->get_memory_usage();
        $memory_threshold = $this->gc_settings['memory_threshold'];

        if ($force_gc || $memory_usage > $memory_threshold) {
            $this->run_garbage_collection();
        }
    }

    /**
     * Run garbage collection.
     *
     * @since    1.3.0
     * @return   void
     */
    public function run_garbage_collection() {
        $initial_memory = memory_get_usage();

        // Run multiple cycles of garbage collection
        for ($i = 0; $i < $this->gc_settings['cycles']; $i++) {
            gc_collect_cycles();
        }

        $final_memory = memory_get_usage();
        $memory_freed = $initial_memory - $final_memory;

        // Create a resource usage event
        $event = new Status_Sentry_Monitoring_Event(
            'resource_usage',
            'resource_manager',
            'garbage_collection',
            sprintf('Garbage collection freed %d MB of memory', $memory_freed / (1024 * 1024)),
            [
                'initial_memory' => $initial_memory,
                'final_memory' => $final_memory,
                'memory_freed' => $memory_freed,
                'cycles' => $this->gc_settings['cycles'],
            ],
            Status_Sentry_Monitoring_Event::PRIORITY_NORMAL
        );

        // Dispatch the event
        Status_Sentry_Monitoring_Manager::get_instance()->dispatch($event);
    }

    /**
     * Get memory usage as a fraction of the limit.
     *
     * @since    1.3.0
     * @return   float    Memory usage as a fraction (0-1).
     */
    public function get_memory_usage() {
        $usage = memory_get_usage();
        $limit = $this->get_memory_limit();
        return $limit > 0 ? $usage / $limit : 0;
    }

    /**
     * Get memory limit in bytes.
     *
     * @since    1.3.0
     * @return   int    Memory limit in bytes.
     */
    public function get_memory_limit() {
        $limit = ini_get('memory_limit');
        return $this->return_bytes($limit);
    }

    /**
     * Get available memory in bytes.
     *
     * @since    1.3.0
     * @return   int    Available memory in bytes.
     */
    public function get_available_memory() {
        $limit = $this->get_memory_limit();
        $usage = memory_get_usage();
        return $limit - $usage;
    }

    /**
     * Get CPU usage as a fraction.
     *
     * @since    1.3.0
     * @return   float    CPU usage as a fraction (0-1), or -1 if not available.
     */
    public function get_cpu_usage() {
        // This is a simplified implementation that may not work on all systems
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if (is_array($load) && isset($load[0])) {
                // Get number of CPU cores
                $cores = $this->get_cpu_cores();
                return $cores > 0 ? $load[0] / $cores : $load[0];
            }
        }
        return -1;
    }

    /**
     * Get number of CPU cores.
     *
     * @since    1.3.0
     * @return   int    Number of CPU cores, or 1 if not available.
     */
    private function get_cpu_cores() {
        // This is a simplified implementation that may not work on all systems
        if (is_file('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            return count($matches[0]);
        }
        return 1;
    }

    /**
     * Convert PHP ini memory value to bytes.
     *
     * @since    1.3.0
     * @param    string    $val    The memory value (e.g., '128M').
     * @return   int               The memory value in bytes.
     */
    private function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;
        switch ($last) {
            case 'g':
                $val *= 1024;
                // no break
            case 'm':
                $val *= 1024;
                // no break
            case 'k':
                $val *= 1024;
        }
        return $val;
    }

    /**
     * Get the task type based on the task ID.
     *
     * @since    1.3.0
     * @param    string    $task_id    The task ID.
     * @return   string                The task type.
     */
    private function get_task_type($task_id) {
        // Map task IDs to types
        $critical_tasks = ['health_check', 'heartbeat'];
        $intensive_tasks = ['process_queue', 'cleanup', 'sync'];
        $report_tasks = ['generate_report', 'export_data'];

        if (in_array($task_id, $critical_tasks)) {
            return 'critical';
        } elseif (in_array($task_id, $intensive_tasks)) {
            return 'intensive';
        } elseif (in_array($task_id, $report_tasks)) {
            return 'report';
        } else {
            return 'standard';
        }
    }

    /**
     * Get the budget for a task type.
     *
     * @since    1.3.0
     * @param    string    $task_type    The task type.
     * @return   array                   The budget for the task type.
     */
    private function get_budget($task_type) {
        return isset($this->budgets[$task_type]) ? $this->budgets[$task_type] : $this->budgets['standard'];
    }

    /**
     * Check memory usage and take action if needed.
     *
     * @since    1.3.0
     * @param    int       $memory_usage    The memory usage in bytes.
     * @param    string    $task_id         The task ID.
     * @return   void
     */
    private function check_memory_usage($memory_usage, $task_id) {
        $memory_limit = $this->get_memory_limit();
        $memory_usage_fraction = $memory_limit > 0 ? $memory_usage / $memory_limit : 0;

        if ($memory_usage_fraction > $this->gc_settings['memory_threshold']) {
            // Memory usage is above threshold, run garbage collection
            $this->run_garbage_collection();
        }
    }

    /**
     * Check CPU usage and take action if needed.
     *
     * @since    1.3.0
     * @param    float     $cpu_usage    The CPU usage as a fraction.
     * @param    string    $task_id      The task ID.
     * @return   void
     */
    private function check_cpu_usage($cpu_usage, $task_id) {
        if ($cpu_usage > $this->cpu_threshold) {
            // CPU usage is above threshold, create a resource limit event
            $event = new Status_Sentry_Monitoring_Event(
                'resource_limit',
                'resource_manager',
                'scheduler',
                sprintf('High CPU usage detected: %.2f%%', $cpu_usage * 100),
                [
                    'limit_type' => 'cpu',
                    'task_id' => $task_id,
                    'value' => $cpu_usage,
                    'threshold' => $this->cpu_threshold,
                ],
                Status_Sentry_Monitoring_Event::PRIORITY_HIGH
            );

            // Dispatch the event
            Status_Sentry_Monitoring_Manager::get_instance()->dispatch($event);
        }
    }

    /**
     * Handle a resource limit event.
     *
     * @since    1.3.0
     * @param    string    $limit_type    The type of limit (memory, cpu, time).
     * @param    string    $task_id       The task ID.
     * @param    float     $value         The current value.
     * @return   void
     */
    private function handle_resource_limit($limit_type, $task_id, $value) {
        // Log the resource limit event
        error_log(sprintf('Status Sentry Resource Limit (%s): Task %s exceeded %s limit with value %s', $limit_type, $task_id, $limit_type, $value));

        // Take action based on the limit type
        switch ($limit_type) {
            case 'memory':
                // Run garbage collection
                $this->run_garbage_collection();
                break;
            case 'cpu':
                // Nothing to do here, just log it
                break;
            case 'time':
                // Nothing to do here, just log it
                break;
        }
    }
}

<?php
/**
 * Scheduler class.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes
 */

/**
 * Scheduler Class
 *
 * This class manages WordPress cron jobs for the Status Sentry plugin. It handles
 * scheduling, unscheduling, and execution of background tasks such as processing
 * the event queue and cleaning up old data.
 *
 * Key responsibilities:
 * - Register custom cron schedules (e.g., every 5 minutes)
 * - Schedule recurring tasks during plugin activation
 * - Unschedule tasks during plugin deactivation
 * - Process the event queue at regular intervals
 * - Clean up old data to prevent database bloat
 * - Handle errors and provide detailed logging
 * - Enforce resource budgets for tasks
 * - Track task execution history
 * - Implement tiered scheduling with different priorities
 * - Provide per-task locking to prevent concurrent execution
 * - Support resumable tasks with state persistence
 * - Trigger garbage collection after resource-intensive tasks
 * - Adapt scheduling based on system load
 *
 * The scheduler uses WordPress's built-in cron system (WP-Cron) which runs
 * when a page is loaded. For high-traffic sites, this works well. For low-traffic
 * sites, consider setting up a server cron job to trigger WP-Cron regularly.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes
 * @author     Status Sentry Team
 */
class Status_Sentry_Scheduler {

    /**
     * Task registry.
     *
     * @since    1.1.0
     * @access   private
     * @var      array    $tasks    The registered tasks.
     */
    private static $tasks = [];

    /**
     * Task dependencies.
     *
     * @since    1.4.0
     * @access   private
     * @var      array    $dependencies    The task dependencies.
     */
    private static $dependencies = [];

    /**
     * Self-monitor instance.
     *
     * @since    1.1.0
     * @access   private
     * @var      Status_Sentry_Self_Monitor    $self_monitor    The self-monitor instance.
     */
    private static $self_monitor = null;

    /**
     * Resource manager instance.
     *
     * @since    1.1.0
     * @access   private
     * @var      Status_Sentry_Resource_Manager    $resource_manager    The resource manager instance.
     */
    private static $resource_manager = null;

    /**
     * Task state manager instance.
     *
     * @since    1.2.0
     * @access   private
     * @var      Status_Sentry_Task_State_Manager    $task_state_manager    The task state manager instance.
     */
    private static $task_state_manager = null;

    /**
     * Cron logger instance.
     *
     * @since    1.4.0
     * @access   private
     * @var      Status_Sentry_Cron_Logger    $cron_logger    The cron logger instance.
     */
    private static $cron_logger = null;

    /**
     * Health checker instance.
     *
     * @since    1.4.0
     * @access   private
     * @var      Status_Sentry_Health_Checker    $health_checker    The health checker instance.
     */
    private static $health_checker = null;

    /**
     * Initialize the scheduler.
     *
     * @since    1.1.0
     */
    public static function init() {
        // Load interfaces first
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/interface-status-sentry-monitoring.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/interface-status-sentry-monitoring-handler.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-monitoring-event.php';

        // Load monitoring components
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-self-monitor.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-resource-manager.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-task-state-manager.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-cron-logger.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-health-checker.php';

        // Initialize monitoring components
        self::$self_monitor = new Status_Sentry_Self_Monitor();
        self::$resource_manager = new Status_Sentry_Resource_Manager();
        self::$task_state_manager = new Status_Sentry_Task_State_Manager();
        self::$cron_logger = new Status_Sentry_Cron_Logger();
        self::$health_checker = new Status_Sentry_Health_Checker();

        // Initialize monitoring components
        self::$cron_logger->init();
        self::$health_checker->init();

        // Register default tasks
        self::register_task('process_queue', 'status_sentry_process_queue', 'standard', 'five_minutes');
        self::register_task('cleanup', 'status_sentry_cleanup', 'intensive', 'daily');

        // Register new tasks for 1.2.0
        self::register_task('cleanup_expired_cache', 'status_sentry_cleanup_expired_cache', 'standard', 'hourly');
        self::register_task('cleanup_expired_task_state', 'status_sentry_cleanup_expired_task_state', 'standard', 'daily');

        // Register baseline update task - runs every 15 minutes to keep metrics fresh
        self::register_task('update_baselines', 'status_sentry_update_baselines', 'standard', 'fifteen_minutes');

        // Allow other components to register tasks
        do_action('status_sentry_register_tasks');
    }

    /**
     * Register a task.
     *
     * @since    1.1.0
     * @param    string    $task_name      The name of the task.
     * @param    string    $hook           The WordPress hook to trigger.
     * @param    string    $tier           The tier of the task (critical, standard, intensive, report).
     * @param    string    $schedule       The schedule for the task (e.g., 'five_minutes', 'hourly', 'daily').
     * @param    array     $callback       Optional. The callback function to execute. Default is [self::class, $task_name].
     * @param    array     $args           Optional. Arguments to pass to the callback. Default is [].
     * @param    array     $dependencies   Optional. Task dependencies. Default is [].
     * @return   bool                      Whether the task was successfully registered.
     */
    public static function register_task($task_name, $hook, $tier, $schedule, $callback = null, $args = [], $dependencies = []) {
        // Validate tier
        $valid_tiers = ['critical', 'standard', 'intensive', 'report'];
        if (!in_array($tier, $valid_tiers)) {
            error_log(sprintf('Status Sentry: Invalid tier "%s" for task "%s", defaulting to "standard"', $tier, $task_name));
            $tier = 'standard';
        }

        // Set default callback if not provided
        if ($callback === null) {
            $callback = [self::class, $task_name];
        }

        // Register the task
        self::$tasks[$task_name] = [
            'hook' => $hook,
            'tier' => $tier,
            'schedule' => $schedule,
            'callback' => $callback,
            'args' => $args,
        ];

        // Register dependencies
        if (!empty($dependencies)) {
            self::$dependencies[$task_name] = $dependencies;
            error_log(sprintf('Status Sentry: Registered dependencies for task "%s": %s', $task_name, implode(', ', $dependencies)));
        }

        // Register the callback
        add_action($hook, function() use ($task_name, $callback, $args) {
            // Check if dependencies are satisfied before executing
            if (self::check_dependencies($task_name)) {
                self::execute_task($task_name, $callback, $args);
            } else {
                error_log(sprintf('Status Sentry: Skipping task "%s" because dependencies are not satisfied', $task_name));

                // Log the skipped execution
                if (self::$cron_logger !== null) {
                    $hook = self::get_task_hook($task_name);
                    self::$cron_logger->start_log($hook, $task_name);
                    self::$cron_logger->end_log($hook, 'skipped', 'Dependencies not satisfied');
                }
            }
        });

        return true;
    }

    /**
     * Check if dependencies for a task are satisfied.
     *
     * @since    1.4.0
     * @access   private
     * @param    string    $task_name    The name of the task.
     * @return   bool                    Whether the dependencies are satisfied.
     */
    private static function check_dependencies($task_name) {
        // If no dependencies, return true
        if (!isset(self::$dependencies[$task_name]) || empty(self::$dependencies[$task_name])) {
            return true;
        }

        // Check each dependency
        foreach (self::$dependencies[$task_name] as $dependency) {
            // Skip if dependency doesn't exist
            if (!isset(self::$tasks[$dependency])) {
                error_log(sprintf('Status Sentry: Dependency "%s" for task "%s" not found in registry', $dependency, $task_name));
                continue;
            }

            // Get the hook for the dependency
            $hook = self::$tasks[$dependency]['hook'];

            // Check if the dependency has been run recently
            global $wpdb;
            $cron_logs_table = $wpdb->prefix . 'status_sentry_cron_logs';

            // Skip if the table doesn't exist yet
            if ($wpdb->get_var("SHOW TABLES LIKE '$cron_logs_table'") != $cron_logs_table) {
                continue;
            }

            // Check for successful completion in the last 24 hours
            $recent_success = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $cron_logs_table WHERE hook = %s AND status = 'complete' AND completion_time > %s",
                $hook,
                date('Y-m-d H:i:s', time() - 86400) // Last 24 hours
            ));

            if ($recent_success == 0) {
                error_log(sprintf('Status Sentry: Dependency "%s" for task "%s" has not completed successfully in the last 24 hours', $dependency, $task_name));
                return false;
            }
        }

        return true;
    }

    /**
     * Execute a task with monitoring and resource management.
     *
     * @since    1.1.0
     * @access   private
     * @param    string    $task_name    The name of the task.
     * @param    callable  $callback     The callback function to execute.
     * @param    array     $args         Arguments to pass to the callback.
     * @return   mixed                   The result of the callback.
     */
    private static function execute_task($task_name, $callback, $args = []) {
        // Check if task is registered
        if (!isset(self::$tasks[$task_name])) {
            error_log(sprintf('Status Sentry: Task "%s" not found in registry', $task_name));
            return false;
        }

        $task = self::$tasks[$task_name];
        $tier = $task['tier'];

        // Check if task is already running (locking)
        if (self::is_task_running($task_name)) {
            error_log(sprintf('Status Sentry: Task "%s" is already running, skipping execution', $task_name));
            return false;
        }

        // Generate a unique key for this task instance
        $task_key = uniqid($task_name . '_', true);

        // Check if there's a saved state for this task
        $saved_state = self::$task_state_manager->get_state($task_name, 'latest');
        if ($saved_state) {
            error_log(sprintf('Status Sentry: Found saved state for task "%s", resuming from previous execution', $task_name));

            // Add the saved state to the arguments
            $args['_saved_state'] = $saved_state;
        }

        // Start monitoring
        $task_run_id = self::$self_monitor->start_task($task_name, $tier);
        $start_time = microtime(true);
        $memory_start = memory_get_usage();

        // Trigger the before task execution action for monitoring
        do_action('status_sentry_before_task_execution', $task_name, $tier);

        try {
            // Execute the callback
            error_log(sprintf('Status Sentry: Starting task "%s" (%s tier)', $task_name, $tier));
            $result = call_user_func_array($callback, $args);

            // Check if the result contains a state to save for resumption
            if (is_array($result) && isset($result['_save_state']) && $result['_save_state'] === true) {
                // Save the state for later resumption
                $state_data = isset($result['_state']) ? $result['_state'] : [];
                $ttl = isset($result['_state_ttl']) ? $result['_state_ttl'] : 3600; // Default 1 hour

                self::$task_state_manager->save_state($task_name, 'latest', $state_data, $ttl);

                error_log(sprintf('Status Sentry: Saved state for task "%s" for later resumption', $task_name));

                // End monitoring with partial completion status
                self::$self_monitor->end_task($task_run_id, 'partial');

                // Prepare execution data for monitoring
                $execution_data = [
                    'status' => 'partial',
                    'execution_time' => microtime(true) - $start_time,
                    'memory_used' => memory_get_usage() - $memory_start,
                    'saved_state' => true,
                    'continuation_scheduled' => false,
                ];

                // Schedule immediate continuation if requested
                if (isset($result['_schedule_continuation']) && $result['_schedule_continuation'] === true) {
                    $continuation_delay = isset($result['_continuation_delay']) ? $result['_continuation_delay'] : 60; // Default 1 minute
                    wp_schedule_single_event(time() + $continuation_delay, $task['hook']);

                    error_log(sprintf('Status Sentry: Scheduled continuation of task "%s" in %d seconds', $task_name, $continuation_delay));

                    $execution_data['continuation_scheduled'] = true;
                    $execution_data['continuation_delay'] = $continuation_delay;
                }

                // Trigger the after task execution action for monitoring
                do_action('status_sentry_after_task_execution', $task_name, $tier, $execution_data);

                // Return the actual result if provided
                return isset($result['_result']) ? $result['_result'] : true;
            } else {
                // Task completed successfully, delete any saved state
                if ($saved_state) {
                    self::$task_state_manager->delete_state($task_name, 'latest');
                }

                // End monitoring with success status
                self::$self_monitor->end_task($task_run_id, 'completed');

                // Check if we should trigger garbage collection
                $gc_triggered = false;
                if (self::$resource_manager->should_trigger_gc_after_task($task_name)) {
                    self::$resource_manager->trigger_gc();
                    $gc_triggered = true;
                }

                // Log completion
                $execution_time = microtime(true) - $start_time;
                $memory_used = memory_get_usage() - $memory_start;

                error_log(sprintf(
                    'Status Sentry: Task "%s" completed successfully in %.2f seconds (memory: %.2f MB)',
                    $task_name,
                    $execution_time,
                    $memory_used / (1024 * 1024)
                ));

                // Prepare execution data for monitoring
                $execution_data = [
                    'status' => 'completed',
                    'execution_time' => $execution_time,
                    'memory_used' => $memory_used,
                    'gc_triggered' => $gc_triggered,
                ];

                // Trigger the after task execution action for monitoring
                do_action('status_sentry_after_task_execution', $task_name, $tier, $execution_data);

                return $result;
            }
        } catch (Exception $e) {
            // End monitoring with failure status
            self::$self_monitor->end_task($task_run_id, 'failed', $e->getMessage());

            // Log error
            error_log(sprintf(
                'Status Sentry: Task "%s" failed with exception: %s',
                $task_name,
                $e->getMessage()
            ));

            // Try to log a stack trace if available
            if (method_exists($e, 'getTraceAsString')) {
                error_log('Status Sentry: Stack trace - ' . $e->getTraceAsString());
            }

            // Check if we should trigger garbage collection
            $gc_triggered = false;
            if (self::$resource_manager->should_trigger_gc_after_task($task_name)) {
                self::$resource_manager->trigger_gc();
                $gc_triggered = true;
            }

            // Prepare execution data for monitoring
            $execution_data = [
                'status' => 'failed',
                'execution_time' => microtime(true) - $start_time,
                'memory_used' => memory_get_usage() - $memory_start,
                'error_type' => 'exception',
                'error_message' => $e->getMessage(),
                'gc_triggered' => $gc_triggered,
            ];

            // Trigger the after task execution action for monitoring
            do_action('status_sentry_after_task_execution', $task_name, $tier, $execution_data);

            return false;
        } catch (Error $e) {
            // End monitoring with failure status
            self::$self_monitor->end_task($task_run_id, 'failed', $e->getMessage());

            // Log error
            error_log(sprintf(
                'Status Sentry: Task "%s" failed with error: %s',
                $task_name,
                $e->getMessage()
            ));

            // Try to log a stack trace if available
            if (method_exists($e, 'getTraceAsString')) {
                error_log('Status Sentry: Stack trace - ' . $e->getTraceAsString());
            }

            // Check if we should trigger garbage collection
            $gc_triggered = false;
            if (self::$resource_manager->should_trigger_gc_after_task($task_name)) {
                self::$resource_manager->trigger_gc();
                $gc_triggered = true;
            }

            // Prepare execution data for monitoring
            $execution_data = [
                'status' => 'failed',
                'execution_time' => microtime(true) - $start_time,
                'memory_used' => memory_get_usage() - $memory_start,
                'error_type' => 'error',
                'error_message' => $e->getMessage(),
                'gc_triggered' => $gc_triggered,
            ];

            // Trigger the after task execution action for monitoring
            do_action('status_sentry_after_task_execution', $task_name, $tier, $execution_data);

            return false;
        }
    }

    /**
     * Check if a task is already running.
     *
     * @since    1.1.0
     * @access   private
     * @param    string    $task_name    The name of the task.
     * @return   bool                    Whether the task is already running.
     */
    private static function is_task_running($task_name) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'status_sentry_task_runs';

        // Check if the table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return false;
        }

        // Check for running tasks
        $running_tasks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE task_name = %s AND status = 'running' AND start_time > %s",
            $task_name,
            date('Y-m-d H:i:s', time() - 3600) // Only consider tasks started in the last hour
        ));

        return $running_tasks > 0;
    }

    /**
     * Schedule background tasks.
     *
     * This method sets up the recurring background tasks needed by the plugin.
     * It registers custom cron schedules and schedules all registered tasks.
     *
     * @since    1.0.0
     * @return   bool    Whether all tasks were successfully scheduled.
     */
    public static function schedule_tasks() {
        $success = true;

        // Initialize the scheduler
        self::init();

        // Register custom cron schedules
        add_filter('cron_schedules', [self::class, 'add_cron_schedules']);

        // Get schedule intervals for offset calculation
        $schedule_intervals = [];
        $wp_schedules = wp_get_schedules();
        foreach ($wp_schedules as $name => $schedule) {
            $schedule_intervals[$name] = $schedule['interval'];
        }

        // Schedule all registered tasks
        foreach (self::$tasks as $task_name => $task) {
            // Calculate offset based on task tier and schedule interval
            $interval = isset($schedule_intervals[$task['schedule']]) ? $schedule_intervals[$task['schedule']] : 3600;
            $max_offset_percent = 0.25; // Maximum offset is 25% of the interval

            // Adjust max offset based on tier
            switch ($task['tier']) {
                case 'critical':
                    $max_offset_percent = 0.05; // 5% for critical tasks
                    break;
                case 'standard':
                    $max_offset_percent = 0.15; // 15% for standard tasks
                    break;
                case 'intensive':
                    $max_offset_percent = 0.25; // 25% for intensive tasks
                    break;
                case 'report':
                    $max_offset_percent = 0.35; // 35% for report tasks
                    break;
            }

            // Calculate max offset in seconds
            $max_offset = (int)($interval * $max_offset_percent);

            // Generate random offset between 0 and max_offset
            $offset = mt_rand(0, $max_offset);

            // Add load-aware delay if system is under high load
            if (self::$resource_manager !== null && method_exists(self::$resource_manager, 'get_system_load')) {
                $load = self::$resource_manager->get_system_load();
                $cpu_threshold = self::$resource_manager->get_cpu_threshold();

                // Use the CPU threshold from resource manager instead of hardcoded value
                if ($load > $cpu_threshold) {
                    // Add additional delay proportional to the load
                    $load_factor = ($load - $cpu_threshold) / (1 - $cpu_threshold); // Normalize to 0-1 range
                    $load_delay = (int)($max_offset * $load_factor * 5); // Increased scale factor for more aggressive throttling

                    // Apply different delay strategies based on task tier
                    switch ($task['tier']) {
                        case 'critical':
                            // Critical tasks get minimal delay
                            $load_delay = (int)($load_delay * 0.5);
                            break;
                        case 'intensive':
                            // Intensive tasks get maximum delay
                            $load_delay = (int)($load_delay * 1.5);
                            break;
                        case 'report':
                            // Report tasks get even more delay
                            $load_delay = (int)($load_delay * 2.0);
                            break;
                    }

                    $offset += $load_delay;
                    error_log(sprintf(
                        'Status Sentry: Adding load-aware delay of %d seconds for task "%s" (load: %.2f, threshold: %.2f, tier: %s)',
                        $load_delay,
                        $task_name,
                        $load,
                        $cpu_threshold,
                        $task['tier']
                    ));
                }
            }

            if (!wp_next_scheduled($task['hook'])) {
                $result = wp_schedule_event(time() + $offset, $task['schedule'], $task['hook']);
                if ($result === false) {
                    error_log(sprintf('Status Sentry: Failed to schedule task "%s"', $task_name));
                    $success = false;
                } else {
                    error_log(sprintf('Status Sentry: Successfully scheduled task "%s" (every %s) with offset of %d seconds', $task_name, $task['schedule'], $offset));
                }
            } else {
                error_log(sprintf('Status Sentry: Task "%s" already scheduled', $task_name));
            }
        }

        // Register recovery task hook
        add_action('status_sentry_recover_task', [self::$health_checker, 'recover_task'], 10, 2);

        // Schedule health check task if not already scheduled
        if (!wp_next_scheduled('status_sentry_cron_health_check')) {
            wp_schedule_event(time() + 300, 'thirty_minutes', 'status_sentry_cron_health_check');
            error_log('Status Sentry: Scheduled cron health check task');
        }

        // Schedule cron logs cleanup if not already scheduled
        if (!wp_next_scheduled('status_sentry_cleanup_cron_logs')) {
            wp_schedule_event(time() + 3600, 'daily', 'status_sentry_cleanup_cron_logs');
            error_log('Status Sentry: Scheduled cron logs cleanup task');
        }

        // Verify that the tasks were scheduled
        self::verify_scheduled_tasks();

        return $success;
    }

    /**
     * Verify that tasks are properly scheduled.
     *
     * This method checks if the required cron tasks are properly scheduled
     * and logs any issues it finds. It's useful for debugging cron problems.
     *
     * @since    1.0.0
     * @access   private
     * @return   bool    Whether all required tasks are properly scheduled.
     */
    private static function verify_scheduled_tasks() {
        $all_scheduled = true;
        $cron_array = _get_cron_array();

        if (empty($cron_array)) {
            error_log('Status Sentry: No cron jobs found in WordPress');
            return false;
        }

        // Check each registered task
        foreach (self::$tasks as $task_name => $task) {
            $task_scheduled = false;
            foreach ($cron_array as $timestamp => $cron_job) {
                if (isset($cron_job[$task['hook']])) {
                    $task_scheduled = true;
                    $next_run = date('Y-m-d H:i:s', $timestamp);
                    error_log(sprintf('Status Sentry: Task "%s" scheduled to run at %s', $task_name, $next_run));
                    break;
                }
            }

            if (!$task_scheduled) {
                error_log(sprintf('Status Sentry: Task "%s" not found in scheduled cron jobs', $task_name));
                $all_scheduled = false;
            }
        }

        return $all_scheduled;
    }

    /**
     * Unschedule background tasks.
     *
     * This method removes all scheduled tasks created by the plugin.
     * It's typically called during plugin deactivation to clean up
     * any scheduled cron jobs.
     *
     * @since    1.0.0
     * @return   bool    Whether all tasks were successfully unscheduled.
     */
    public static function unschedule_tasks() {
        $success = true;

        // Initialize the scheduler to ensure tasks are registered
        self::init();

        // Unschedule all registered tasks
        foreach (self::$tasks as $task_name => $task) {
            $timestamp = wp_next_scheduled($task['hook']);
            if ($timestamp) {
                $result = wp_unschedule_event($timestamp, $task['hook']);
                if ($result === false) {
                    error_log(sprintf('Status Sentry: Failed to unschedule task "%s"', $task_name));
                    $success = false;
                } else {
                    error_log(sprintf('Status Sentry: Successfully unscheduled task "%s"', $task_name));
                }
            } else {
                error_log(sprintf('Status Sentry: No task "%s" found to unschedule', $task_name));
            }

            // Alternative approach: clear all hooks
            $result = wp_clear_scheduled_hook($task['hook']);
            if ($result > 0) {
                error_log(sprintf('Status Sentry: Cleared %d "%s" tasks using wp_clear_scheduled_hook', $result, $task_name));
            }
        }

        return $success;
    }

    /**
     * Add custom cron schedules.
     *
     * @since    1.0.0
     * @param    array    $schedules    The existing cron schedules.
     * @return   array                  The modified cron schedules.
     */
    public static function add_cron_schedules($schedules) {
        // Add a 5-minute schedule
        $schedules['five_minutes'] = [
            'interval' => 300, // 5 minutes in seconds
            'display' => __('Every 5 Minutes', 'status-sentry-wp'),
        ];

        // Add a 15-minute schedule
        $schedules['fifteen_minutes'] = [
            'interval' => 900, // 15 minutes in seconds
            'display' => __('Every 15 Minutes', 'status-sentry-wp'),
        ];

        // Add a 30-minute schedule
        $schedules['thirty_minutes'] = [
            'interval' => 1800, // 30 minutes in seconds
            'display' => __('Every 30 Minutes', 'status-sentry-wp'),
        ];

        // Add a twice-daily schedule
        $schedules['twice_daily'] = [
            'interval' => 43200, // 12 hours in seconds
            'display' => __('Twice Daily', 'status-sentry-wp'),
        ];

        // Add a weekly schedule
        $schedules['weekly'] = [
            'interval' => 604800, // 7 days in seconds
            'display' => __('Once Weekly', 'status-sentry-wp'),
        ];

        return $schedules;
    }

    /**
     * Get the hook for a task.
     *
     * @since    1.4.0
     * @param    string    $task_name    The name of the task.
     * @return   string|null             The hook for the task, or null if the task doesn't exist.
     */
    public static function get_task_hook($task_name) {
        return isset(self::$tasks[$task_name]) ? self::$tasks[$task_name]['hook'] : null;
    }

    /**
     * Get the dependencies for a task.
     *
     * @since    1.4.0
     * @param    string    $task_name    The name of the task.
     * @return   array                   The dependencies for the task, or an empty array if none.
     */
    public static function get_task_dependencies($task_name) {
        return isset(self::$dependencies[$task_name]) ? self::$dependencies[$task_name] : [];
    }

    /**
     * Process the event queue.
     *
     * This method is called by WordPress cron to process events in the queue.
     * It creates an instance of the EventProcessor class and calls its
     * process_events method to handle the actual processing.
     *
     * The method includes error handling to catch and log any exceptions
     * that occur during processing, ensuring that the cron job doesn't
     * fail silently.
     *
     * @since    1.0.0
     * @param    int     $batch_size    Optional. The number of events to process in a batch. Default 100.
     * @return   int                    The number of events processed.
     */
    public static function process_queue($batch_size = 100) {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        $db_queries_start = get_num_queries();

        error_log('Status Sentry: Starting queue processing');

        try {
            // Validate batch size
            $batch_size = absint($batch_size);
            if ($batch_size <= 0) {
                $batch_size = 100; // Default to 100 if invalid
            }

            // Create an event processor
            $processor = new Status_Sentry_Event_Processor();

            // Process events with resource monitoring
            $processed_count = 0;
            $remaining = $batch_size;

            // Process in smaller batches to allow for resource checks
            $batch_chunk_size = min(25, $batch_size);

            while ($remaining > 0) {
                // Check if we should continue based on resource usage
                if (!self::$resource_manager->should_continue(
                    'standard',
                    $start_time,
                    $memory_start,
                    get_num_queries() - $db_queries_start
                )) {
                    error_log('Status Sentry: Stopping queue processing early due to resource constraints');
                    break;
                }

                // Process a chunk of events
                $chunk_size = min($batch_chunk_size, $remaining);
                $chunk_processed = $processor->process_events($chunk_size);
                $processed_count += $chunk_processed;

                // If we processed fewer events than requested, the queue is empty
                if ($chunk_processed < $chunk_size) {
                    break;
                }

                $remaining -= $chunk_size;
            }

            // Calculate execution time
            $execution_time = microtime(true) - $start_time;
            $memory_used = memory_get_usage() - $memory_start;
            $db_queries = get_num_queries() - $db_queries_start;

            // Log the result
            if ($processed_count > 0) {
                error_log(sprintf(
                    'Status Sentry: Processed %d events from the queue in %.2f seconds (memory: %d MB, queries: %d).',
                    $processed_count,
                    $execution_time,
                    $memory_used / (1024 * 1024),
                    $db_queries
                ));
            } else {
                error_log(sprintf(
                    'Status Sentry: No events processed from the queue (took %.2f seconds).',
                    $execution_time
                ));
            }

            return $processed_count;
        } catch (Exception $e) {
            error_log('Status Sentry: Exception during queue processing - ' . $e->getMessage());

            // Try to log a stack trace if available
            if (method_exists($e, 'getTraceAsString')) {
                error_log('Status Sentry: Stack trace - ' . $e->getTraceAsString());
            }

            return 0;
        } catch (Error $e) {
            error_log('Status Sentry: Error during queue processing - ' . $e->getMessage());

            // Try to log a stack trace if available
            if (method_exists($e, 'getTraceAsString')) {
                error_log('Status Sentry: Stack trace - ' . $e->getTraceAsString());
            }

            return 0;
        } finally {
            // Always log completion, even if an error occurred
            $total_time = microtime(true) - $start_time;
            error_log(sprintf('Status Sentry: Queue processing completed in %.2f seconds', $total_time));

            // Record metrics for baseline
            if (self::$self_monitor !== null) {
                self::$self_monitor->baseline->record_metric('queue_processing_time', 'standard', $total_time);
                self::$self_monitor->baseline->record_metric('queue_processing_memory', 'standard', memory_get_peak_usage() - $memory_start);
                self::$self_monitor->baseline->record_metric('queue_processing_queries', 'standard', get_num_queries() - $db_queries_start);
            }
        }
    }

    /**
     * Clean up expired cache entries.
     *
     * This method is called by WordPress cron to clean up expired cache entries.
     * It helps prevent database bloat from old cache data.
     *
     * @since    1.2.0
     * @return   int    The number of expired cache entries deleted.
     */
    public static function cleanup_expired_cache() {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();

        error_log('Status Sentry: Starting expired cache cleanup');

        try {
            // Create a query cache instance
            require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/db/class-status-sentry-query-cache.php';
            $query_cache = new Status_Sentry_Query_Cache();

            // Clean up expired cache entries
            $deleted = $query_cache->cleanup_expired();

            // Log the result
            error_log(sprintf(
                'Status Sentry: Deleted %d expired cache entries in %.2f seconds',
                $deleted,
                microtime(true) - $start_time
            ));

            return $deleted;
        } catch (Exception $e) {
            error_log('Status Sentry: Exception during cache cleanup - ' . $e->getMessage());
            return 0;
        } catch (Error $e) {
            error_log('Status Sentry: Error during cache cleanup - ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean up expired task state.
     *
     * This method is called by WordPress cron to clean up expired task state.
     * It helps prevent database bloat from old task state data.
     *
     * @since    1.2.0
     * @return   int    The number of expired task states deleted.
     */
    public static function cleanup_expired_task_state() {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();

        error_log('Status Sentry: Starting expired task state cleanup');

        try {
            // Clean up expired task state
            $deleted = self::$task_state_manager->cleanup_expired();

            // Log the result
            error_log(sprintf(
                'Status Sentry: Deleted %d expired task states in %.2f seconds',
                $deleted,
                microtime(true) - $start_time
            ));

            return $deleted;
        } catch (Exception $e) {
            error_log('Status Sentry: Exception during task state cleanup - ' . $e->getMessage());
            return 0;
        } catch (Error $e) {
            error_log('Status Sentry: Error during task state cleanup - ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean up old data.
     *
     * This method is called by WordPress cron to clean up old data from the database.
     * It removes:
     * - Events older than 30 days from the events table
     * - Processed queue items older than 7 days from the queue table
     * - Failed queue items older than 14 days from the queue table
     * - Task runs older than 30 days from the task_runs table
     *
     * The method includes error handling and detailed logging to track
     * the cleanup process and any issues that occur.
     *
     * @since    1.0.0
     * @return   array    Statistics about the cleanup operation.
     */
    public static function cleanup() {
        global $wpdb;
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        $db_queries_start = get_num_queries();

        $stats = [
            'events_deleted' => 0,
            'processed_queue_items_deleted' => 0,
            'failed_queue_items_deleted' => 0,
            'task_runs_deleted' => 0,
            'errors' => 0,
        ];

        error_log('Status Sentry: Starting database cleanup');

        try {
            // Get retention settings (could be made configurable in the future)
            $events_retention_days = apply_filters('status_sentry_events_retention_days', 30);
            $processed_queue_retention_days = apply_filters('status_sentry_processed_queue_retention_days', 7);
            $failed_queue_retention_days = apply_filters('status_sentry_failed_queue_retention_days', 14);
            $task_runs_retention_days = apply_filters('status_sentry_task_runs_retention_days', 30);

            error_log(sprintf(
                'Status Sentry: Using retention periods - Events: %d days, Processed queue: %d days, Failed queue: %d days, Task runs: %d days',
                $events_retention_days,
                $processed_queue_retention_days,
                $failed_queue_retention_days,
                $task_runs_retention_days
            ));

            // Clean up old events
            $events_table = $wpdb->prefix . 'status_sentry_events';
            if ($wpdb->get_var("SHOW TABLES LIKE '$events_table'") == $events_table) {
                // Check if we should continue based on resource usage
                if (!self::$resource_manager->should_continue('intensive', $start_time, $memory_start, get_num_queries() - $db_queries_start)) {
                    error_log('Status Sentry: Skipping events cleanup due to resource constraints');
                } else {
                    // Get table size before cleanup
                    $table_size_before = $wpdb->get_var("SELECT COUNT(*) FROM $events_table");

                    // Delete events older than retention period
                    $cutoff_date = date('Y-m-d H:i:s', time() - ($events_retention_days * 86400));
                    $deleted = $wpdb->query($wpdb->prepare(
                        "DELETE FROM $events_table WHERE event_time < %s LIMIT 5000",
                        $cutoff_date
                    ));

                    if ($deleted === false) {
                        error_log('Status Sentry: Error deleting old events - ' . $wpdb->last_error);
                        $stats['errors']++;
                    } else {
                        $stats['events_deleted'] = $deleted;
                        error_log(sprintf(
                            'Status Sentry: Deleted %d old events (older than %s).',
                            $deleted,
                            $cutoff_date
                        ));

                        // Get table size after cleanup
                        $table_size_after = $wpdb->get_var("SELECT COUNT(*) FROM $events_table");
                        error_log(sprintf(
                            'Status Sentry: Events table size: %d rows before, %d rows after cleanup',
                            $table_size_before,
                            $table_size_after
                        ));
                    }
                }
            } else {
                error_log('Status Sentry: Events table does not exist, skipping cleanup');
            }

            // Clean up old queue items
            $queue_table = $wpdb->prefix . 'status_sentry_queue';
            if ($wpdb->get_var("SHOW TABLES LIKE '$queue_table'") == $queue_table) {
                // Check if we should continue based on resource usage
                if (!self::$resource_manager->should_continue('intensive', $start_time, $memory_start, get_num_queries() - $db_queries_start)) {
                    error_log('Status Sentry: Skipping queue cleanup due to resource constraints');
                } else {
                    // Get table size before cleanup
                    $table_size_before = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table");

                    // Delete processed queue items older than retention period
                    $processed_cutoff_date = date('Y-m-d H:i:s', time() - ($processed_queue_retention_days * 86400));
                    $deleted = $wpdb->query($wpdb->prepare(
                        "DELETE FROM $queue_table WHERE status = 'processed' AND created_at < %s LIMIT 5000",
                        $processed_cutoff_date
                    ));

                    if ($deleted === false) {
                        error_log('Status Sentry: Error deleting old processed queue items - ' . $wpdb->last_error);
                        $stats['errors']++;
                    } else {
                        $stats['processed_queue_items_deleted'] = $deleted;
                        error_log(sprintf(
                            'Status Sentry: Deleted %d old processed queue items (older than %s).',
                            $deleted,
                            $processed_cutoff_date
                        ));
                    }

                    // Delete failed queue items older than retention period
                    $failed_cutoff_date = date('Y-m-d H:i:s', time() - ($failed_queue_retention_days * 86400));
                    $deleted = $wpdb->query($wpdb->prepare(
                        "DELETE FROM $queue_table WHERE status = 'failed' AND created_at < %s LIMIT 5000",
                        $failed_cutoff_date
                    ));

                    if ($deleted === false) {
                        error_log('Status Sentry: Error deleting old failed queue items - ' . $wpdb->last_error);
                        $stats['errors']++;
                    } else {
                        $stats['failed_queue_items_deleted'] = $deleted;
                        error_log(sprintf(
                            'Status Sentry: Deleted %d old failed queue items (older than %s).',
                            $deleted,
                            $failed_cutoff_date
                        ));
                    }

                    // Get table size after cleanup
                    $table_size_after = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table");
                    error_log(sprintf(
                        'Status Sentry: Queue table size: %d rows before, %d rows after cleanup',
                        $table_size_before,
                        $table_size_after
                    ));
                }
            } else {
                error_log('Status Sentry: Queue table does not exist, skipping cleanup');
            }

            // Clean up old task runs
            $task_runs_table = $wpdb->prefix . 'status_sentry_task_runs';
            if ($wpdb->get_var("SHOW TABLES LIKE '$task_runs_table'") == $task_runs_table) {
                // Check if we should continue based on resource usage
                if (!self::$resource_manager->should_continue('intensive', $start_time, $memory_start, get_num_queries() - $db_queries_start)) {
                    error_log('Status Sentry: Skipping task runs cleanup due to resource constraints');
                } else {
                    // Get table size before cleanup
                    $table_size_before = $wpdb->get_var("SELECT COUNT(*) FROM $task_runs_table");

                    // Delete task runs older than retention period
                    $cutoff_date = date('Y-m-d H:i:s', time() - ($task_runs_retention_days * 86400));
                    $deleted = $wpdb->query($wpdb->prepare(
                        "DELETE FROM $task_runs_table WHERE start_time < %s LIMIT 5000",
                        $cutoff_date
                    ));

                    if ($deleted === false) {
                        error_log('Status Sentry: Error deleting old task runs - ' . $wpdb->last_error);
                        $stats['errors']++;
                    } else {
                        $stats['task_runs_deleted'] = $deleted;
                        error_log(sprintf(
                            'Status Sentry: Deleted %d old task runs (older than %s).',
                            $deleted,
                            $cutoff_date
                        ));

                        // Get table size after cleanup
                        $table_size_after = $wpdb->get_var("SELECT COUNT(*) FROM $task_runs_table");
                        error_log(sprintf(
                            'Status Sentry: Task runs table size: %d rows before, %d rows after cleanup',
                            $table_size_before,
                            $table_size_after
                        ));
                    }
                }
            } else {
                error_log('Status Sentry: Task runs table does not exist, skipping cleanup');
            }

            // Optimize tables if possible
            if (method_exists($wpdb, 'query') && self::$resource_manager->should_continue('intensive', $start_time, $memory_start, get_num_queries() - $db_queries_start)) {
                $wpdb->query("OPTIMIZE TABLE $events_table");
                $wpdb->query("OPTIMIZE TABLE $queue_table");
                $wpdb->query("OPTIMIZE TABLE $task_runs_table");
                error_log('Status Sentry: Optimized database tables');
            }
        } catch (Exception $e) {
            error_log('Status Sentry: Exception during cleanup - ' . $e->getMessage());
            $stats['errors']++;
        } catch (Error $e) {
            error_log('Status Sentry: Error during cleanup - ' . $e->getMessage());
            $stats['errors']++;
        } finally {
            // Always log completion, even if an error occurred
            $total_time = microtime(true) - $start_time;
            $memory_used = memory_get_peak_usage() - $memory_start;
            $db_queries = get_num_queries() - $db_queries_start;

            error_log(sprintf(
                'Status Sentry: Cleanup completed in %.2f seconds (memory: %d MB, queries: %d). Stats: %s',
                $total_time,
                $memory_used / (1024 * 1024),
                $db_queries,
                json_encode($stats)
            ));

            // Record metrics for baseline
            if (self::$self_monitor !== null) {
                self::$self_monitor->baseline->record_metric('cleanup_time', 'intensive', $total_time);
                self::$self_monitor->baseline->record_metric('cleanup_memory', 'intensive', $memory_used);
                self::$self_monitor->baseline->record_metric('cleanup_queries', 'intensive', $db_queries);
            }
        }

        return $stats;
    }

    /**
     * Update system baselines.
     *
     * This method collects current system metrics and emits resource_usage events
     * to ensure baseline metrics are regularly updated.
     *
     * @since    1.6.0
     * @param    int      $batch_size    Optional. The number of metrics to update. Default 5.
     * @return   array                   Statistics about the baseline update.
     */
    public static function update_baselines($batch_size = 5) {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        $db_queries_start = get_num_queries();

        $stats = [
            'metrics_updated' => 0,
            'errors' => 0,
        ];

        try {
            error_log('Status Sentry: Starting baseline metrics update');

            // Get the monitoring manager instance
            $monitoring_manager = Status_Sentry_Monitoring_Manager::get_instance();

            // Collect current system metrics
            $metrics = [
                'memory_usage' => memory_get_usage(),
                'memory_peak' => memory_get_peak_usage(),
            ];

            // Add CPU load if available
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                $metrics['cpu_usage'] = $load[0];
            }

            // Add database metrics
            global $wpdb;
            $metrics['db_queries'] = $wpdb->num_queries;

            // Emit a resource_usage event with the collected metrics
            $event = new Status_Sentry_Monitoring_Event(
                'resource_usage',
                'scheduler',
                'system',
                'Regular baseline metrics update',
                $metrics,
                Status_Sentry_Monitoring_Event::PRIORITY_LOW
            );

            // Dispatch the event to update baselines
            $result = $monitoring_manager->dispatch($event);

            if ($result) {
                $stats['metrics_updated']++;
                error_log('Status Sentry: Successfully emitted resource_usage event for baseline update');
            } else {
                $stats['errors']++;
                error_log('Status Sentry: Failed to emit resource_usage event for baseline update');
            }

            // Also emit a performance event for execution metrics
            $perf_event = new Status_Sentry_Monitoring_Event(
                'performance',
                'scheduler',
                'baseline_update',
                'Baseline update performance metrics',
                [
                    'execution_time' => microtime(true) - $start_time,
                    'memory_used' => memory_get_usage() - $memory_start,
                ],
                Status_Sentry_Monitoring_Event::PRIORITY_LOW
            );

            $monitoring_manager->dispatch($perf_event);

        } catch (Exception $e) {
            error_log('Status Sentry: Exception during baseline update - ' . $e->getMessage());
            $stats['errors']++;
        } finally {
            // Always log completion, even if an error occurred
            $total_time = microtime(true) - $start_time;
            error_log(sprintf(
                'Status Sentry: Baseline update completed in %.2f seconds. Stats: %s',
                $total_time,
                json_encode($stats)
            ));

            // Record metrics for baseline
            if (self::$self_monitor !== null) {
                self::$self_monitor->baseline->record_metric('baseline_update_time', 'standard', $total_time);
                self::$self_monitor->baseline->record_metric('baseline_update_memory', 'standard', memory_get_usage() - $memory_start);
            }
        }

        return $stats;
    }
}

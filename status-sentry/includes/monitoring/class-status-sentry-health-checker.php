<?php
/**
 * Health Checker Component
 *
 * This class implements health checking for WordPress cron jobs.
 * It detects stuck or failed tasks and attempts to recover them.
 *
 * Key responsibilities:
 * - Detect stuck or failed cron jobs
 * - Recover stuck jobs by rescheduling them
 * - Track recovery attempts to prevent infinite loops
 * - Provide health status reports
 * - Implement the Monitoring_Interface for centralized monitoring
 *
 * @since      1.4.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Health_Checker implements Status_Sentry_Monitoring_Interface {

    /**
     * Configuration options.
     *
     * @since    1.4.0
     * @access   private
     * @var      array    $config    Configuration options.
     */
    private $config;

    /**
     * The cron logs table name.
     *
     * @since    1.4.0
     * @access   private
     * @var      string    $cron_logs_table    The cron logs table name.
     */
    private $cron_logs_table;

    /**
     * The task runs table name.
     *
     * @since    1.4.0
     * @access   private
     * @var      string    $task_runs_table    The task runs table name.
     */
    private $task_runs_table;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.4.0
     */
    public function __construct() {
        global $wpdb;
        $this->cron_logs_table = $wpdb->prefix . 'status_sentry_cron_logs';
        $this->task_runs_table = $wpdb->prefix . 'status_sentry_task_runs';

        // Default configuration
        $this->config = [
            'enabled' => true,
            'stuck_threshold' => 3600, // 1 hour
            'max_recovery_attempts' => 3,
            'recovery_delay' => 300, // 5 minutes
            'check_interval' => 1800, // 30 minutes
        ];

        // Load saved configuration
        $saved_config = get_option('status_sentry_health_checker_config', []);
        if (!empty($saved_config)) {
            $this->config = array_merge($this->config, $saved_config);
        }
    }

    /**
     * Initialize the monitoring component.
     *
     * @since    1.4.0
     * @return   void
     */
    public function init() {
        // Schedule health check
        if (!wp_next_scheduled('status_sentry_cron_health_check')) {
            wp_schedule_event(time(), 'thirty_minutes', 'status_sentry_cron_health_check');
        }
        add_action('status_sentry_cron_health_check', [$this, 'check_cron_health']);

        // Register hooks for monitoring
        add_action('status_sentry_after_task_execution', [$this, 'on_task_end'], 20, 3);
    }

    /**
     * Register event handlers with the monitoring manager.
     *
     * @since    1.4.0
     * @param    Status_Sentry_Monitoring_Manager    $manager    The monitoring manager instance.
     * @return   void
     */
    public function register_handlers($manager) {
        $manager->register_handler('cron_health', [$this, 'process_event']);
    }

    /**
     * Process a monitoring event.
     *
     * @since    1.4.0
     * @param    Status_Sentry_Monitoring_Event    $event    The monitoring event to process.
     * @return   bool                                        Whether the event was successfully processed.
     */
    public function process_event($event) {
        // Skip processing if health checking is disabled
        if (!$this->config['enabled']) {
            return false;
        }

        $type = $event->get_type();
        $data = $event->get_data();

        if ($type === 'cron_health') {
            // Process cron health event
            if (isset($data['action']) && $data['action'] === 'check') {
                $this->check_cron_health();
                return true;
            } elseif (isset($data['action']) && $data['action'] === 'recover' && isset($data['task_name'])) {
                $this->recover_task($data['task_name'], $data['hook'] ?? null);
                return true;
            }
        }

        return false;
    }

    /**
     * Handle task end event.
     *
     * @since    1.4.0
     * @param    string    $task_name       The name of the task.
     * @param    string    $tier            The tier of the task.
     * @param    array     $execution_data  Execution data.
     * @return   void
     */
    public function on_task_end($task_name, $tier, $execution_data) {
        // Skip if health checking is disabled
        if (!$this->config['enabled']) {
            return;
        }

        // Check if the task failed
        if (isset($execution_data['status']) && $execution_data['status'] === 'failed') {
            // Get the hook from the task registry
            $hook = Status_Sentry_Scheduler::get_task_hook($task_name);
            if ($hook === null) {
                return;
            }

            // Check if we should attempt recovery
            $recovery_attempts = $this->get_recovery_attempts($task_name);
            if ($recovery_attempts < $this->config['max_recovery_attempts']) {
                // Schedule recovery
                $this->schedule_recovery($task_name, $hook);
            } else {
                // Log that we've reached the maximum recovery attempts
                error_log(sprintf(
                    'Status Sentry: Maximum recovery attempts (%d) reached for task "%s"',
                    $this->config['max_recovery_attempts'],
                    $task_name
                ));

                // Emit a critical event
                $manager = Status_Sentry_Monitoring_Manager::get_instance();
                $manager->emit(
                    Status_Sentry_Monitoring_Event::TYPE_CRITICAL,
                    'health_checker',
                    'max_recovery_attempts',
                    sprintf('Maximum recovery attempts (%d) reached for task "%s"', $this->config['max_recovery_attempts'], $task_name),
                    [
                        'task_name' => $task_name,
                        'hook' => $hook,
                        'recovery_attempts' => $recovery_attempts,
                        'last_error' => $execution_data['error_message'] ?? 'Unknown error',
                    ],
                    Status_Sentry_Monitoring_Event::PRIORITY_HIGH
                );
            }
        }
    }

    /**
     * Check cron health.
     *
     * @since    1.4.0
     * @return   array    Health check results.
     */
    public function check_cron_health() {
        global $wpdb;

        // Skip if health checking is disabled
        if (!$this->config['enabled']) {
            return ['status' => 'disabled'];
        }

        $results = [
            'status' => 'ok',
            'stuck_tasks' => [],
            'failed_tasks' => [],
            'recovered_tasks' => [],
        ];

        // Check for stuck tasks
        $stuck_threshold = date('Y-m-d H:i:s', time() - $this->config['stuck_threshold']);
        $stuck_tasks = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->cron_logs_table} WHERE status = 'running' AND execution_time < %s",
            $stuck_threshold
        ));

        if ($stuck_tasks) {
            $results['status'] = 'warning';
            foreach ($stuck_tasks as $task) {
                $results['stuck_tasks'][] = [
                    'hook' => $task->hook,
                    'task_name' => $task->task_name,
                    'execution_time' => $task->execution_time,
                    'stuck_for' => time() - strtotime($task->execution_time),
                ];

                // Attempt recovery if task_name is available
                if ($task->task_name) {
                    $recovery_attempts = $this->get_recovery_attempts($task->task_name);
                    if ($recovery_attempts < $this->config['max_recovery_attempts']) {
                        $this->recover_task($task->task_name, $task->hook);
                        $results['recovered_tasks'][] = $task->task_name;
                    } else {
                        // Log that we've reached the maximum recovery attempts
                        error_log(sprintf(
                            'Status Sentry: Maximum recovery attempts (%d) reached for stuck task "%s"',
                            $this->config['max_recovery_attempts'],
                            $task->task_name
                        ));
                    }
                }
            }
        }

        // Check for recently failed tasks
        $recent_failures = $wpdb->get_results(
            "SELECT * FROM {$this->cron_logs_table} WHERE status = 'failed' ORDER BY completion_time DESC LIMIT 10"
        );

        if ($recent_failures) {
            foreach ($recent_failures as $failure) {
                $results['failed_tasks'][] = [
                    'hook' => $failure->hook,
                    'task_name' => $failure->task_name,
                    'execution_time' => $failure->execution_time,
                    'completion_time' => $failure->completion_time,
                    'error_message' => $failure->error_message,
                ];
            }

            if (count($recent_failures) > 5) {
                $results['status'] = 'critical';
            } elseif ($results['status'] === 'ok') {
                $results['status'] = 'warning';
            }
        }

        // Emit health status event
        $manager = Status_Sentry_Monitoring_Manager::get_instance();
        $event_type = $results['status'] === 'ok' ? Status_Sentry_Monitoring_Event::TYPE_INFO :
                     ($results['status'] === 'warning' ? Status_Sentry_Monitoring_Event::TYPE_WARNING :
                     Status_Sentry_Monitoring_Event::TYPE_CRITICAL);

        $manager->emit(
            $event_type,
            'health_checker',
            'cron_health_check',
            sprintf('Cron health check: %s', $results['status']),
            $results,
            $results['status'] === 'critical' ? Status_Sentry_Monitoring_Event::PRIORITY_HIGH : Status_Sentry_Monitoring_Event::PRIORITY_NORMAL
        );

        return $results;
    }

    /**
     * Recover a task.
     *
     * @since    1.4.0
     * @param    string    $task_name    The name of the task.
     * @param    string    $hook         Optional. The hook to use. Default null.
     * @return   bool                    Whether the task was successfully recovered.
     */
    public function recover_task($task_name, $hook = null) {
        // Skip if health checking is disabled
        if (!$this->config['enabled']) {
            return false;
        }

        // Get the hook if not provided
        if ($hook === null) {
            $hook = Status_Sentry_Scheduler::get_task_hook($task_name);
            if ($hook === null) {
                error_log(sprintf('Status Sentry: Failed to get hook for task "%s"', $task_name));
                return false;
            }
        }

        // Increment recovery attempts
        $recovery_attempts = $this->increment_recovery_attempts($task_name);

        // Clear any existing scheduled events for this hook
        wp_clear_scheduled_hook($hook);

        // Schedule the task to run again after a delay
        $result = wp_schedule_single_event(time() + $this->config['recovery_delay'], $hook);

        // Log the recovery attempt
        error_log(sprintf(
            'Status Sentry: Recovery attempt %d for task "%s" (hook: %s) - %s',
            $recovery_attempts,
            $task_name,
            $hook,
            $result ? 'scheduled' : 'failed to schedule'
        ));

        // Emit recovery event
        $manager = Status_Sentry_Monitoring_Manager::get_instance();
        $manager->emit(
            Status_Sentry_Monitoring_Event::TYPE_WARNING,
            'health_checker',
            'task_recovery',
            sprintf('Recovery attempt %d for task "%s"', $recovery_attempts, $task_name),
            [
                'task_name' => $task_name,
                'hook' => $hook,
                'recovery_attempts' => $recovery_attempts,
                'scheduled' => $result,
                'next_run' => date('Y-m-d H:i:s', time() + $this->config['recovery_delay']),
            ],
            Status_Sentry_Monitoring_Event::PRIORITY_HIGH
        );

        return $result;
    }

    /**
     * Schedule recovery for a task.
     *
     * @since    1.4.0
     * @access   private
     * @param    string    $task_name    The name of the task.
     * @param    string    $hook         The hook to use.
     * @return   bool                    Whether the recovery was successfully scheduled.
     */
    private function schedule_recovery($task_name, $hook) {
        // Skip if health checking is disabled
        if (!$this->config['enabled']) {
            return false;
        }

        // Schedule recovery event
        $result = wp_schedule_single_event(
            time() + $this->config['recovery_delay'],
            'status_sentry_recover_task',
            [$task_name, $hook]
        );

        // Log the scheduled recovery
        error_log(sprintf(
            'Status Sentry: Scheduled recovery for task "%s" (hook: %s) in %d seconds',
            $task_name,
            $hook,
            $this->config['recovery_delay']
        ));

        return $result;
    }

    /**
     * Get recovery attempts for a task.
     *
     * @since    1.4.0
     * @access   private
     * @param    string    $task_name    The name of the task.
     * @return   int                     The number of recovery attempts.
     */
    private function get_recovery_attempts($task_name) {
        $recovery_attempts = get_option('status_sentry_recovery_attempts', []);
        return isset($recovery_attempts[$task_name]) ? $recovery_attempts[$task_name] : 0;
    }

    /**
     * Increment recovery attempts for a task.
     *
     * @since    1.4.0
     * @access   private
     * @param    string    $task_name    The name of the task.
     * @return   int                     The new number of recovery attempts.
     */
    private function increment_recovery_attempts($task_name) {
        $recovery_attempts = get_option('status_sentry_recovery_attempts', []);
        $recovery_attempts[$task_name] = isset($recovery_attempts[$task_name]) ? $recovery_attempts[$task_name] + 1 : 1;
        update_option('status_sentry_recovery_attempts', $recovery_attempts);
        return $recovery_attempts[$task_name];
    }

    /**
     * Reset recovery attempts for a task.
     *
     * @since    1.4.0
     * @param    string    $task_name    The name of the task.
     * @return   bool                    Whether the recovery attempts were successfully reset.
     */
    public function reset_recovery_attempts($task_name) {
        $recovery_attempts = get_option('status_sentry_recovery_attempts', []);
        if (isset($recovery_attempts[$task_name])) {
            unset($recovery_attempts[$task_name]);
            update_option('status_sentry_recovery_attempts', $recovery_attempts);
            return true;
        }
        return false;
    }

    /**
     * Get the monitoring component's status.
     *
     * @since    1.4.0
     * @return   array    The component status as an associative array.
     */
    public function get_status() {
        // Get health check results
        $health_check = $this->check_cron_health();

        // Get recovery attempts
        $recovery_attempts = get_option('status_sentry_recovery_attempts', []);

        return [
            'enabled' => $this->config['enabled'],
            'health_status' => $health_check['status'],
            'stuck_tasks_count' => count($health_check['stuck_tasks']),
            'failed_tasks_count' => count($health_check['failed_tasks']),
            'recovered_tasks_count' => count($health_check['recovered_tasks']),
            'recovery_attempts' => $recovery_attempts,
            'stuck_threshold' => $this->config['stuck_threshold'],
            'max_recovery_attempts' => $this->config['max_recovery_attempts'],
            'recovery_delay' => $this->config['recovery_delay'],
        ];
    }

    /**
     * Get the monitoring component's configuration.
     *
     * @since    1.4.0
     * @return   array    The component configuration as an associative array.
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Update the monitoring component's configuration.
     *
     * @since    1.4.0
     * @param    array    $config    The new configuration as an associative array.
     * @return   bool                Whether the configuration was successfully updated.
     */
    public function update_config($config) {
        $updated = false;

        // Update enabled status if provided
        if (isset($config['enabled'])) {
            $this->config['enabled'] = (bool)$config['enabled'];
            $updated = true;
        }

        // Update stuck threshold if provided
        if (isset($config['stuck_threshold']) && is_numeric($config['stuck_threshold'])) {
            $this->config['stuck_threshold'] = max(300, (int)$config['stuck_threshold']); // Minimum 5 minutes
            $updated = true;
        }

        // Update max recovery attempts if provided
        if (isset($config['max_recovery_attempts']) && is_numeric($config['max_recovery_attempts'])) {
            $this->config['max_recovery_attempts'] = max(1, (int)$config['max_recovery_attempts']);
            $updated = true;
        }

        // Update recovery delay if provided
        if (isset($config['recovery_delay']) && is_numeric($config['recovery_delay'])) {
            $this->config['recovery_delay'] = max(60, (int)$config['recovery_delay']); // Minimum 1 minute
            $updated = true;
        }

        // Update check interval if provided
        if (isset($config['check_interval']) && is_numeric($config['check_interval'])) {
            $this->config['check_interval'] = max(300, (int)$config['check_interval']); // Minimum 5 minutes
            $updated = true;
        }

        // Save the updated configuration
        if ($updated) {
            update_option('status_sentry_health_checker_config', $this->config);
        }

        return $updated;
    }
}

<?php
/**
 * Task State Manager Component
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */

/**
 * Task State Manager Component
 *
 * This class implements the task state management component for Status Sentry.
 * It tracks the state of scheduled tasks and provides persistence across runs.
 *
 * Key responsibilities:
 * - Track task state across executions
 * - Provide persistence for long-running tasks
 * - Support resumable tasks
 * - Track task progress
 * - Manage task dependencies
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Task_State_Manager implements Status_Sentry_Monitoring_Interface {

    /**
     * The task state table name.
     *
     * @since    1.3.0
     * @access   private
     * @var      string    $table_name    The table name.
     */
    private $table_name;

    /**
     * Configuration options for the task state manager component.
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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'status_sentry_task_states';

        // Default configuration
        $this->config = [
            'enabled' => true,
            'state_ttl' => 86400, // 24 hours
            'cleanup_interval' => 3600, // 1 hour
        ];

        // Load saved configuration
        $saved_config = get_option('status_sentry_task_state_manager_config', []);
        if (!empty($saved_config)) {
            $this->config = array_merge($this->config, $saved_config);
        }

        // Ensure the task state table exists
        $this->ensure_table_exists();
    }

    /**
     * Initialize the monitoring component.
     *
     * @since    1.3.0
     * @return   void
     */
    public function init() {
        // Schedule cleanup of old task states
        if (!wp_next_scheduled('status_sentry_cleanup_task_states')) {
            wp_schedule_event(time(), 'hourly', 'status_sentry_cleanup_task_states');
        }
        add_action('status_sentry_cleanup_task_states', [$this, 'cleanup_old_task_states']);

        // Register hooks for task state tracking
        add_action('status_sentry_before_task_execution', [$this, 'on_task_start'], 10, 2);
        add_action('status_sentry_after_task_execution', [$this, 'on_task_end'], 10, 3);
    }

    /**
     * Register event handlers with the monitoring manager.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Manager    $manager    The monitoring manager instance.
     * @return   void
     */
    public function register_handlers($manager) {
        // Register handlers for task state events
        $manager->register_handler('task_state', [$this, 'process_event']);
    }

    /**
     * Process a monitoring event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The monitoring event to process.
     * @return   void
     */
    public function process_event($event) {
        // Skip processing if task state management is disabled
        if (!$this->config['enabled']) {
            return;
        }

        $type = $event->get_type();
        $data = $event->get_data();

        if ($type === 'task_state') {
            // Process task state event
            if (isset($data['task_id'], $data['state'])) {
                $this->update_task_state(
                    $data['task_id'],
                    $data['state'],
                    $data['progress'] ?? 0,
                    $data['metadata'] ?? []
                );
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
        global $wpdb;

        // Get task state statistics
        $total_states = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $active_states = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'active'");
        $completed_states = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'completed'");
        $failed_states = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'");

        return [
            'enabled' => $this->config['enabled'],
            'total_states' => $total_states,
            'active_states' => $active_states,
            'completed_states' => $completed_states,
            'failed_states' => $failed_states,
            'state_ttl' => $this->config['state_ttl'],
            'cleanup_interval' => $this->config['cleanup_interval'],
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
        // Validate configuration
        if (isset($config['state_ttl'])) {
            $config['state_ttl'] = max(3600, min(604800, intval($config['state_ttl'])));
        }

        if (isset($config['cleanup_interval'])) {
            $config['cleanup_interval'] = max(900, min(86400, intval($config['cleanup_interval'])));
        }

        // Update configuration
        $this->config = array_merge($this->config, $config);

        // Save configuration
        update_option('status_sentry_task_state_manager_config', $this->config);

        return true;
    }

    /**
     * Handle task start event.
     *
     * @since    1.3.0
     * @param    string    $task_id    The task ID.
     * @param    array     $args       The task arguments.
     * @return   void
     */
    public function on_task_start($task_id, $args) {
        // Get existing task state
        $state = $this->get_task_state($task_id);

        // Update task state
        $this->update_task_state(
            $task_id,
            [
                'status' => 'active',
                'start_time' => time(),
                'args' => $args,
            ],
            $state ? $state['progress'] : 0,
            $state ? $state['metadata'] : []
        );
    }

    /**
     * Handle task end event.
     *
     * @since    1.3.0
     * @param    string    $task_id    The task ID.
     * @param    array     $args       The task arguments.
     * @param    bool      $success    Whether the task was successful.
     * @return   void
     */
    public function on_task_end($task_id, $args, $success) {
        // Get existing task state
        $state = $this->get_task_state($task_id);

        // Update task state
        $this->update_task_state(
            $task_id,
            [
                'status' => $success ? 'completed' : 'failed',
                'end_time' => time(),
                'args' => $args,
            ],
            $success ? 100 : ($state ? $state['progress'] : 0),
            $state ? $state['metadata'] : []
        );
    }

    /**
     * Get the state of a task.
     *
     * @since    1.3.0
     * @param    string    $task_id    The task ID.
     * @return   array|bool            The task state, or false if not found.
     */
    public function get_task_state($task_id) {
        global $wpdb;

        $state = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE task_id = %s",
                $task_id
            ),
            ARRAY_A
        );

        if ($state) {
            // Decode JSON fields
            $state['state'] = json_decode($state['state'], true);
            $state['metadata'] = json_decode($state['metadata'], true);
            return $state;
        }

        return false;
    }

    /**
     * Update the state of a task.
     *
     * @since    1.3.0
     * @param    string    $task_id     The task ID.
     * @param    array     $state       The task state.
     * @param    int       $progress    The task progress (0-100).
     * @param    array     $metadata    Additional metadata.
     * @return   bool                   Whether the task state was successfully updated.
     */
    public function update_task_state($task_id, $state, $progress = 0, $metadata = []) {
        global $wpdb;

        // Validate progress
        $progress = max(0, min(100, intval($progress)));

        // Check if a state exists for this task
        $existing_state = $this->get_task_state($task_id);

        if ($existing_state) {
            // Update existing state
            $result = $wpdb->update(
                $this->table_name,
                [
                    'state' => json_encode($state),
                    'progress' => $progress,
                    'metadata' => json_encode($metadata),
                    'updated_at' => current_time('mysql'),
                ],
                [
                    'task_id' => $task_id,
                ],
                ['%s', '%d', '%s', '%s'],
                ['%s']
            );
        } else {
            // Insert new state
            $result = $wpdb->insert(
                $this->table_name,
                [
                    'task_id' => $task_id,
                    'state' => json_encode($state),
                    'progress' => $progress,
                    'metadata' => json_encode($metadata),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%d', '%s', '%s', '%s']
            );
        }

        // Create a task state event
        $event = new Status_Sentry_Monitoring_Event(
            'task_state',
            'task_state_manager',
            'scheduler',
            sprintf('Task %s state updated: %s, progress: %d%%', $task_id, $state['status'] ?? 'unknown', $progress),
            [
                'task_id' => $task_id,
                'state' => $state,
                'progress' => $progress,
                'metadata' => $metadata,
            ],
            Status_Sentry_Monitoring_Event::PRIORITY_NORMAL
        );

        // Dispatch the event
        Status_Sentry_Monitoring_Manager::get_instance()->dispatch($event);

        return ($result !== false);
    }

    /**
     * Clean up old task states.
     *
     * @since    1.3.0
     * @return   int    The number of rows deleted.
     */
    public function cleanup_old_task_states() {
        global $wpdb;

        $cutoff_time = date('Y-m-d H:i:s', time() - $this->config['state_ttl']);

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE updated_at < %s AND status != 'active'",
                $cutoff_time
            )
        );

        return $result;
    }

    /**
     * Ensure the task state table exists.
     *
     * @since    1.3.0
     * @access   private
     * @return   bool    Whether the table exists or was successfully created.
     */
    private function ensure_table_exists() {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            // Table doesn't exist, create it
            require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/db/migrations/006_create_task_states_table.php';
            $migration = new Status_Sentry_Migration_CreateTaskStatesTable();
            return $migration->up();
        }

        return true;
    }
}

<?php
/**
 * Task State Manager class.
 *
 * @since      1.2.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */

/**
 * Task State Manager class.
 *
 * This class manages the state of long-running tasks, allowing them to be
 * resumed if they are interrupted or exceed their resource budget. It provides
 * a simple API for saving and retrieving task state.
 *
 * Key responsibilities:
 * - Save task state to the database
 * - Retrieve task state from the database
 * - Clean up expired task state
 * - Provide a simple API for task checkpointing
 * - Implement the Monitoring_Interface for centralized monitoring
 *
 * @since      1.2.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Task_State_Manager implements Status_Sentry_Monitoring_Interface {

    /**
     * The table name.
     *
     * @since    1.2.0
     * @access   private
     * @var      string    $table_name    The table name.
     */
    private $table_name;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.2.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'status_sentry_task_state';
    }

    /**
     * Save task state.
     *
     * @since    1.2.0
     * @param    string    $task_name     The name of the task.
     * @param    string    $task_key      A unique key for this task instance.
     * @param    array     $state         The state to save.
     * @param    int       $ttl           Optional. Time to live in seconds. Default 3600 (1 hour).
     * @return   bool                     Whether the state was successfully saved.
     */
    public function save_state($task_name, $task_key, $state, $ttl = 3600) {
        global $wpdb;

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return false;
        }

        // Serialize the state
        $state_json = wp_json_encode($state);
        if ($state_json === false) {
            error_log('Status Sentry: Failed to encode task state as JSON');
            return false;
        }

        // Calculate expiration time
        $expires_at = date('Y-m-d H:i:s', time() + $ttl);

        // Check if the state already exists
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE task_name = %s AND task_key = %s",
                $task_name,
                $task_key
            )
        );

        if ($existing) {
            // Update existing state
            $result = $wpdb->update(
                $this->table_name,
                [
                    'state' => $state_json,
                    'updated_at' => current_time('mysql'),
                    'expires_at' => $expires_at,
                ],
                [
                    'task_name' => $task_name,
                    'task_key' => $task_key,
                ],
                ['%s', '%s', '%s'],
                ['%s', '%s']
            );
        } else {
            // Insert new state
            $result = $wpdb->insert(
                $this->table_name,
                [
                    'task_name' => $task_name,
                    'task_key' => $task_key,
                    'state' => $state_json,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                    'expires_at' => $expires_at,
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s']
            );
        }

        if ($result === false) {
            error_log('Status Sentry: Failed to save task state - ' . $wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * Get task state.
     *
     * @since    1.2.0
     * @param    string    $task_name    The name of the task.
     * @param    string    $task_key     A unique key for this task instance.
     * @return   array|false             The task state or false if not found.
     */
    public function get_state($task_name, $task_key) {
        global $wpdb;

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return false;
        }

        // Get the state
        $state_json = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT state FROM {$this->table_name} WHERE task_name = %s AND task_key = %s AND expires_at > %s",
                $task_name,
                $task_key,
                current_time('mysql')
            )
        );

        if ($state_json === null) {
            return false;
        }

        // Decode the state
        $state = json_decode($state_json, true);
        if ($state === null) {
            error_log('Status Sentry: Failed to decode task state from JSON');
            return false;
        }

        return $state;
    }

    /**
     * Delete task state.
     *
     * @since    1.2.0
     * @param    string    $task_name    The name of the task.
     * @param    string    $task_key     A unique key for this task instance.
     * @return   bool                    Whether the state was successfully deleted.
     */
    public function delete_state($task_name, $task_key) {
        global $wpdb;

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return false;
        }

        // Delete the state
        $result = $wpdb->delete(
            $this->table_name,
            [
                'task_name' => $task_name,
                'task_key' => $task_key,
            ],
            ['%s', '%s']
        );

        if ($result === false) {
            error_log('Status Sentry: Failed to delete task state - ' . $wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * Clean up expired task state.
     *
     * @since    1.2.0
     * @return   int    The number of expired states deleted.
     */
    public function cleanup_expired() {
        global $wpdb;

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return 0;
        }

        // Delete expired states
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE expires_at < %s",
                current_time('mysql')
            )
        );

        if ($result === false) {
            error_log('Status Sentry: Failed to clean up expired task state - ' . $wpdb->last_error);
            return 0;
        }

        return $result;
    }

    /**
     * Ensure the table exists.
     *
     * @since    1.2.0
     * @access   private
     * @return   bool    Whether the table exists.
     */
    private function ensure_table_exists() {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            // Table doesn't exist, create it
            require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/db/migrations/007_create_task_state_table.php';
            $migration = new Status_Sentry_Migration_CreateTaskStateTable();
            return $migration->up();
        }

        return true;
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
        // Task State Manager doesn't handle events directly
    }

    /**
     * Process a monitoring event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The monitoring event to process.
     * @return   void
     */
    public function process_event($event) {
        // Task State Manager doesn't process events directly
    }

    /**
     * Get the monitoring component's status.
     *
     * @since    1.3.0
     * @return   array    The component status as an associative array.
     */
    public function get_status() {
        global $wpdb;

        // Get task state count
        $count = 0;
        $expired_count = 0;

        if ($this->ensure_table_exists()) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
            $expired_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} WHERE expires_at < %s",
                    current_time('mysql')
                )
            );
        }

        return [
            'state_count' => $count,
            'expired_count' => $expired_count,
            'table_exists' => $this->ensure_table_exists(),
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
            'default_ttl' => 3600, // Default time to live (1 hour)
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
        // Task State Manager doesn't have configurable options yet
        return true;
    }
}

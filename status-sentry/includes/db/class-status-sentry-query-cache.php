<?php
/**
 * Query Cache class.
 *
 * @since      1.2.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db
 */

/**
 * Query Cache class.
 *
 * This class provides a simple caching mechanism for database queries to reduce
 * database load for frequently executed queries. It includes automatic expiration
 * to ensure data freshness.
 *
 * Key responsibilities:
 * - Cache query results in the database
 * - Retrieve cached results
 * - Automatically expire old cache entries
 * - Provide a simple API for query caching
 *
 * @since      1.2.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db
 */
class Status_Sentry_Query_Cache {

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
        $this->table_name = $wpdb->prefix . 'status_sentry_query_cache';
    }

    /**
     * Get a cached value.
     *
     * @since    1.2.0
     * @param    string    $key      The cache key.
     * @param    string    $group    Optional. The cache group. Default 'default'.
     * @return   mixed|false         The cached value or false if not found.
     */
    public function get($key, $group = 'default') {
        global $wpdb;

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return false;
        }

        // Get the cached value
        $cache_data = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT cache_data FROM {$this->table_name} WHERE cache_key = %s AND cache_group = %s AND expires_at > %s",
                $key,
                $group,
                current_time('mysql')
            )
        );

        if ($cache_data === null) {
            return false;
        }

        // Decode the cached value
        $value = maybe_unserialize($cache_data);
        if ($value === null) {
            error_log('Status Sentry: Failed to unserialize cached value');
            return false;
        }

        return $value;
    }

    /**
     * Set a cached value.
     *
     * @since    1.2.0
     * @param    string    $key      The cache key.
     * @param    mixed     $value    The value to cache.
     * @param    string    $group    Optional. The cache group. Default 'default'.
     * @param    int       $ttl      Optional. Time to live in seconds. Default 3600 (1 hour).
     * @return   bool                Whether the value was successfully cached.
     */
    public function set($key, $value, $group = 'default', $ttl = 3600) {
        global $wpdb;

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return false;
        }

        // Serialize the value
        $cache_data = maybe_serialize($value);
        if ($cache_data === false) {
            error_log('Status Sentry: Failed to serialize value for caching');
            return false;
        }

        // Calculate expiration time
        $expires_at = date('Y-m-d H:i:s', time() + $ttl);

        // Check if the cache entry already exists
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE cache_key = %s AND cache_group = %s",
                $key,
                $group
            )
        );

        if ($existing) {
            // Update existing cache entry
            $result = $wpdb->update(
                $this->table_name,
                [
                    'cache_data' => $cache_data,
                    'created_at' => current_time('mysql'),
                    'expires_at' => $expires_at,
                ],
                [
                    'cache_key' => $key,
                    'cache_group' => $group,
                ],
                ['%s', '%s', '%s'],
                ['%s', '%s']
            );
        } else {
            // Insert new cache entry
            $result = $wpdb->insert(
                $this->table_name,
                [
                    'cache_key' => $key,
                    'cache_group' => $group,
                    'cache_data' => $cache_data,
                    'created_at' => current_time('mysql'),
                    'expires_at' => $expires_at,
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );
        }

        if ($result === false) {
            error_log('Status Sentry: Failed to set cache value - ' . $wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * Delete a cached value.
     *
     * @since    1.2.0
     * @param    string    $key      The cache key.
     * @param    string    $group    Optional. The cache group. Default 'default'.
     * @return   bool                Whether the value was successfully deleted.
     */
    public function delete($key, $group = 'default') {
        global $wpdb;

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return false;
        }

        // Delete the cache entry
        $result = $wpdb->delete(
            $this->table_name,
            [
                'cache_key' => $key,
                'cache_group' => $group,
            ],
            ['%s', '%s']
        );

        if ($result === false) {
            error_log('Status Sentry: Failed to delete cache value - ' . $wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * Delete all cached values for a group.
     *
     * @since    1.2.0
     * @param    string    $group    The cache group.
     * @return   int                 The number of cache entries deleted.
     */
    public function delete_group($group) {
        global $wpdb;

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return 0;
        }

        // Delete all cache entries for the group
        $result = $wpdb->delete(
            $this->table_name,
            ['cache_group' => $group],
            ['%s']
        );

        if ($result === false) {
            error_log('Status Sentry: Failed to delete cache group - ' . $wpdb->last_error);
            return 0;
        }

        return $result;
    }

    /**
     * Clean up expired cache entries.
     *
     * @since    1.2.0
     * @return   int    The number of expired cache entries deleted.
     */
    public function cleanup_expired() {
        global $wpdb;

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return 0;
        }

        // Delete expired cache entries
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE expires_at < %s",
                current_time('mysql')
            )
        );

        if ($result === false) {
            error_log('Status Sentry: Failed to clean up expired cache entries - ' . $wpdb->last_error);
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
            error_log('Status Sentry: Query cache table does not exist');
            return false;
        }

        return true;
    }
}

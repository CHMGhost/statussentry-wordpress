<?php
/**
 * Migration to create the query_cache table.
 *
 * @since      1.2.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */

/**
 * Migration to create the query_cache table.
 *
 * This table stores cached query results to reduce database load for
 * frequently executed queries. It includes automatic expiration to
 * ensure data freshness.
 *
 * @since      1.2.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */
class Status_Sentry_Migration_CreateQueryCacheTable {
    
    /**
     * Run the migration.
     *
     * @since    1.2.0
     * @return   bool    Whether the migration was successfully run.
     */
    public function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_query_cache';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            cache_group varchar(100) NOT NULL,
            cache_data longtext NOT NULL,
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY cache_key_group (cache_key, cache_group),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        return dbDelta($sql) ? true : false;
    }
    
    /**
     * Reverse the migration.
     *
     * @since    1.2.0
     * @return   bool    Whether the migration was successfully reversed.
     */
    public function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_query_cache';
        
        $sql = "DROP TABLE IF EXISTS $table_name";
        
        return $wpdb->query($sql) !== false;
    }
}

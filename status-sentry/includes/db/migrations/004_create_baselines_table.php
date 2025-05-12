<?php
/**
 * Migration to create the baselines table.
 *
 * @since      1.1.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */

/**
 * Migration to create the baselines table.
 *
 * This table stores system performance baselines for various metrics,
 * allowing the plugin to detect abnormal behavior and adjust its
 * resource usage accordingly.
 *
 * @since      1.1.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */
class Status_Sentry_Migration_CreateBaselinesTable {

    /**
     * Run the migration.
     *
     * @since    1.1.0
     * @return   bool    Whether the migration was successfully run.
     */
    public function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_baselines';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            metric_name varchar(100) NOT NULL,
            metric_context varchar(100) NOT NULL,
            value float NOT NULL,
            sample_count int(11) NOT NULL,
            last_updated datetime NOT NULL,
            metadata longtext NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY metric_context (metric_name, metric_context),
            KEY metric_name (metric_name),
            KEY last_updated (last_updated)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        return dbDelta($sql) ? true : false;
    }

    /**
     * Reverse the migration.
     *
     * @since    1.1.0
     * @return   bool    Whether the migration was successfully reversed.
     */
    public function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_baselines';
        
        $sql = "DROP TABLE IF EXISTS $table_name;";
        
        return $wpdb->query($sql) !== false;
    }
}

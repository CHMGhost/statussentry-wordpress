<?php
/**
 * Mock WordPress Upgrade API
 *
 * This file provides mock implementations of WordPress upgrade functions
 * for testing purposes.
 */

if (!function_exists('dbDelta')) {
    /**
     * Mock implementation of dbDelta function
     *
     * @param string|array $queries SQL queries
     * @param bool $execute Whether to execute the query
     * @return array Array of results
     */
    function dbDelta($queries, $execute = true) {
        if (!is_array($queries)) {
            $queries = explode(';', $queries);
        }
        
        return ['created' => count($queries)];
    }
}

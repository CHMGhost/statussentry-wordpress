=== Status Sentry WP ===
Contributors: statussentryteam
Tags: monitoring, performance, hooks, diagnostics, debugging
Requires at least: 5.6
Tested up to: 6.2
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive WordPress plugin for monitoring site health, capturing performance data, and detecting plugin conflicts with minimal impact.

== Description ==

Status Sentry WP provides deep insights into your WordPress site's performance and health through sophisticated monitoring capabilities. By leveraging an advanced hook management system with intelligent sampling, the plugin captures critical data with minimal performance overhead.

The plugin's data pipeline processes information asynchronously, ensuring your site remains responsive while still collecting the data you need to identify issues, optimize performance, and prevent conflicts.

= Key Features =

* **Core Monitoring**: Track WordPress core hooks and events to understand the loading sequence and identify bottlenecks
* **Database Monitoring**: Monitor database queries, execution times, and performance patterns with anonymized query logging
* **Conflict Detection**: Automatically detect plugin conflicts and compatibility issues before they affect your site
* **Performance Monitoring**: Capture detailed performance metrics including memory usage, load times, and resource utilization
* **Adaptive Sampling**: Intelligently adjust data collection rates based on server load to minimize performance impact
* **JSON-Powered Storage**: Leverage MySQL 5.7's native JSON capabilities for flexible, efficient data storage
* **Asynchronous Processing**: Queue events for background processing to maintain site responsiveness
* **Robust Error Handling**: Comprehensive error handling and recovery mechanisms throughout the pipeline
* **Resource-Aware Processing**: Intelligent batch processing that respects memory limits and execution time constraints
* **Detailed Logging**: Extensive logging for debugging and monitoring system health
* **Configurable Retention**: Customizable data retention policies through WordPress filters

= Technical Details =

Status Sentry WP is built with a modular, extensible architecture that follows WordPress best practices while implementing advanced monitoring techniques:

* **Hook Management Core**: Sophisticated control over data collection with prioritized hook registration and conditional execution
* **Data Capture Pipeline**: Multi-stage pipeline for efficient data processing and storage
* **Intelligent Sampling**: Adaptive sampling rates based on server load and event priority
* **Background Processing**: Asynchronous event processing using WP-Cron with detailed logging
* **Flexible Storage**: JSON-based storage for maximum flexibility and efficiency
* **Database Migrations**: Versioned, transaction-based database schema management
* **Resource Monitoring**: Automatic adjustment based on memory usage and execution time
* **Error Recovery**: Graceful handling of failures with detailed diagnostics
* **Optimized Performance**: Caching and batching for efficient operation
* **Performance Benchmarking**: Comprehensive benchmarking with defined targets for memory usage and execution time
* **Plugin Compatibility**: Tested compatibility with popular WordPress plugins

= Use Cases =

* **Performance Optimization**: Identify slow hooks, queries, and processes
* **Conflict Resolution**: Detect and diagnose plugin conflicts
* **Security Monitoring**: Track suspicious activities and changes
* **Debugging**: Gain insights into WordPress core behavior
* **Site Health**: Monitor overall site performance and health

= Requirements =

* WordPress 5.6 or higher
* PHP 7.4 or higher
* MySQL 5.7 or higher (for native JSON support)
* WP-Cron enabled or alternative cron setup

== Installation ==

1. Upload the `status-sentry` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'Status Sentry' in the admin menu to configure settings

== Frequently Asked Questions ==

= Will this plugin slow down my site? =

Status Sentry WP is designed with performance in mind. It uses intelligent sampling and asynchronous processing to minimize the impact on your site's performance. You can also adjust sampling rates and disable features you don't need to further reduce any potential impact.

= How much database space does this plugin use? =

The amount of database space used depends on your site's traffic, the enabled features, and your data retention settings. By default, the plugin automatically cleans up old data to prevent database bloat. You can adjust the retention period in the settings.

= Can I export the collected data? =

Yes, you can export event data from the Events page for external analysis.

= Does this plugin work with multisite? =

Yes, Status Sentry WP is fully compatible with WordPress multisite installations.

= How do I interpret the collected data? =

The plugin provides a dashboard with summaries and visualizations to help you interpret the data. You can also view detailed event information on the Events page. The documentation includes guidelines for identifying common issues.

= Can I extend the plugin with custom monitoring? =

Yes, Status Sentry WP is designed to be extensible. You can register custom hooks and add custom processing logic using the provided filters and actions.

== Screenshots ==

1. Dashboard overview showing site health and performance metrics
2. Events page with detailed event information
3. Settings page for configuring monitoring features
4. Diagnostic tools for troubleshooting

== Changelog ==

= 1.3.0 =
* Added centralized monitoring system with standardized interfaces
* Implemented monitoring manager for event handling and dispatching
* Added monitoring event class for standardized event representation
* Added baseline monitoring component for performance tracking
* Added self-monitor component for plugin health monitoring
* Added resource manager component for resource usage tracking
* Added task state manager component for task state persistence
* Added conflict detector component for plugin conflict detection
* Improved error handling and reporting throughout the plugin
* Enhanced performance monitoring with baseline comparisons
* Added support for circuit breakers and throttling in monitoring system

= 1.1.0 =
* Enhanced error handling throughout the data pipeline
* Added resource-aware batch processing to prevent memory exhaustion
* Implemented transaction support for database operations
* Added detailed logging for debugging and monitoring
* Improved cron job scheduling with verification
* Added configurable retention policies through WordPress filters
* Enhanced database migration system with transaction support
* Added database table optimization after cleanup operations
* Improved JSON encoding error handling with fallback mechanisms
* Added caching for database checks to improve performance

= 1.0.0 =
* Initial release
* Core monitoring features
* Database monitoring
* Conflict detection
* Performance monitoring

== Upgrade Notice ==

= 1.3.0 =
This update adds a comprehensive monitoring system with standardized interfaces, event handling, and multiple monitoring components for improved plugin health and performance tracking.

= 1.1.0 =
This update significantly improves error handling, performance, and reliability. It adds resource-aware processing, transaction support, and configurable retention policies.

= 1.0.0 =
Initial release of Status Sentry WP.

== Developer Documentation ==

Status Sentry WP is designed to be extensible. Here are some examples of how you can extend the plugin:

= Registering Custom Hooks =

```php
// Register a custom hook
add_filter('status_sentry_hook_config', function($hooks) {
    $hooks['my_feature']['my_custom_hook'] = [
        'callback' => 'my_custom_callback',
        'priority' => 10,
        'sampling_rate' => 0.5,
    ];
    return $hooks;
});
```

= Processing Custom Event Data =

```php
// Process custom event data
add_filter('status_sentry_process_event', function($data, $feature, $hook) {
    if ($feature === 'my_feature') {
        // Add custom processing logic
        $data['my_custom_field'] = 'custom value';
    }
    return $data;
}, 10, 3);
```

= Comprehensive Documentation =

The plugin includes detailed documentation to help you understand and extend its functionality:

* **Core Framework**: See `docs/CORE-FRAMEWORK.md` for details on the core framework components
* **Resource Management**: See `docs/RESOURCE-MANAGEMENT.md` for information on resource management features
* **Performance Benchmarking**: See `docs/PERFORMANCE-BENCHMARKING.md` for guidance on performance benchmarking
* **Production Hardening**: See `docs/PRODUCTION-HARDENING.md` for best practices on hardening the plugin for production
* **Getting Started with Extensions**: See `docs/GETTING-STARTED-EXTENDING.md` for a quick guide to extending the plugin

For more information, please see the [full documentation](https://github.com/status-sentry/status-sentry-wp).

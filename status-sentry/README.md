# Status Sentry WP

A comprehensive WordPress plugin for monitoring site health, capturing performance data, and detecting plugin conflicts with advanced hook management and minimal performance impact.

![Status Sentry Dashboard](assets/images/dashboard-preview.png)

## Description

Status Sentry WP provides deep insights into your WordPress site's performance and health through sophisticated monitoring capabilities. By leveraging an advanced hook management system with intelligent sampling, the plugin captures critical data with minimal performance overhead.

The plugin's data pipeline processes information asynchronously, ensuring your site remains responsive while still collecting the data you need to identify issues, optimize performance, and prevent conflicts.

## Key Features

- **Core Monitoring**: Track WordPress core hooks and events to understand the loading sequence and identify bottlenecks
- **Database Monitoring**: Monitor database queries, execution times, and performance patterns with anonymized query logging
- **Conflict Detection**: Automatically detect plugin conflicts and compatibility issues before they affect your site
- **Performance Monitoring**: Capture detailed performance metrics including memory usage, load times, and resource utilization
- **Adaptive Sampling**: Intelligently adjust data collection rates based on server load to minimize performance impact
- **JSON-Powered Storage**: Leverage MySQL 5.7's native JSON capabilities for flexible, efficient data storage
- **Asynchronous Processing**: Queue events for background processing to maintain site responsiveness
- **Robust Error Handling**: Comprehensive error handling and recovery mechanisms throughout the pipeline
- **Resource-Aware Processing**: Intelligent batch processing that respects memory limits and execution time constraints
- **Detailed Logging**: Extensive logging for debugging and monitoring system health
- **Configurable Retention**: Customizable data retention policies through WordPress filters

## Technical Architecture

Status Sentry WP is built with a modular, extensible architecture that follows WordPress best practices while implementing advanced monitoring techniques.

### Hook Management Core

The plugin's hook management system provides sophisticated control over data collection:

- **Prioritized Hook Registration**: Hooks are registered with specific priorities to ensure they execute at the optimal time
- **Conditional Execution**: Hooks only execute when relevant, based on context, user roles, and system state
- **Feature Flags**: Individual monitoring features can be enabled/disabled through the admin interface
- **Dynamic Hook Configuration**: Hook definitions are centralized and can be modified programmatically
- **Group-Based Organization**: Hooks are organized by functional groups for better management

```php
// Example of hook registration with the Hook Manager
$hook_manager->register_hook('core_monitoring', 'init', [
    'callback' => 'capture_init',
    'priority' => 999,
    'sampling_rate' => 0.1, // 10% sampling
]);
```

### Data Capture Pipeline

Data flows through a sophisticated pipeline designed for efficiency and data integrity:

1. **DataCapture**: Captures raw data from hooks and adds basic metadata
   - Enriches data with context (user, request, environment)
   - Normalizes data format for consistent processing

2. **DataFilter**: Filters and sanitizes captured data
   - Removes sensitive information (passwords, keys, tokens)
   - Sanitizes user input to prevent security issues
   - Truncates large data sets to prevent database bloat

3. **SamplingManager**: Applies intelligent sampling logic
   - Adjusts sampling rates based on server load
   - Ensures high-priority events are always captured
   - Implements adaptive sampling strategies

4. **EventQueue**: Queues events for asynchronous processing
   - Stores events in a dedicated queue table
   - Minimizes impact on the main request cycle
   - Handles queue management and prioritization

```php
// Example of the data flow through the pipeline
$data = $data_capture->capture($feature, $hook, $raw_data);
// Data is filtered, sampled, and queued automatically
```

### Database Structure

The plugin leverages MySQL 5.7's native JSON capabilities for flexible, efficient data storage. The database schema is managed through a robust migration system that ensures safe, versioned updates.

#### Queue Table (`wp_status_sentry_queue`)
- `id`: Unique identifier for the queue item
- `feature`: The monitoring feature (e.g., 'core_monitoring')
- `hook`: The WordPress hook that triggered the event
- `data`: JSON-encoded event data
- `created_at`: When the event was captured
- `status`: Processing status ('pending', 'processed', 'failed')

#### Events Table (`wp_status_sentry_events`)
- `id`: Unique identifier for the event
- `feature`: The monitoring feature
- `hook`: The WordPress hook
- `data`: JSON-encoded event data with enrichments
- `event_time`: When the event occurred
- `processed_time`: When the event was processed

### Database Migrations

The plugin includes a sophisticated database migration system:

- **Versioned Migrations**: Each schema change is versioned and applied sequentially
- **Transaction Support**: Migrations use transactions when available for safety
- **Idempotent Operations**: Migrations are designed to be safely re-runnable
- **Detailed Logging**: Comprehensive logging of migration operations
- **Error Recovery**: Graceful handling of migration failures with rollback support

```sql
-- Example query to retrieve recent performance events
SELECT * FROM wp_status_sentry_events
WHERE feature = 'performance_monitoring'
ORDER BY event_time DESC LIMIT 10;
```

### Event Processing

The `EventProcessor` handles the transformation of raw queue items into enriched, persistent events:

- **Batch Processing**: Processes multiple events in a single operation with transaction support
- **Data Enrichment**: Adds additional context and derived metrics to events
- **Comprehensive Error Handling**: Gracefully handles processing failures with detailed logging
- **Resource-Aware Processing**: Monitors memory usage and execution time to prevent resource exhaustion
- **Intelligent Caching**: Caches database checks to improve performance
- **JSON Validation**: Handles JSON encoding errors with fallback mechanisms
- **Retention Management**: Implements configurable data retention policies

### WP-Cron Integration

The plugin leverages WordPress cron for reliable background processing:

- **Scheduled Processing**: Processes queued events every 5 minutes with detailed logging
- **Automatic Cleanup**: Removes old data based on configurable retention policies with statistics
- **Custom Schedules**: Implements custom cron schedules for optimal timing
- **Failure Recovery**: Detects and recovers from failed cron jobs with detailed error reporting
- **Task Verification**: Verifies that scheduled tasks are properly registered
- **Resource Monitoring**: Monitors resource usage during task execution
- **Database Optimization**: Automatically optimizes database tables after cleanup operations

```php
// Example of how cron jobs are scheduled with verification
$result = wp_schedule_event(time(), 'five_minutes', 'status_sentry_process_queue');
if ($result === false) {
    error_log('Status Sentry: Failed to schedule process_queue task');
}
```

## Installation

### Standard Installation

1. Download the latest release from the [releases page](https://github.com/status-sentry/status-sentry-wp/releases)
2. Upload the `status-sentry` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to 'Status Sentry' in the admin menu to configure settings

### Composer Installation

```bash
composer require status-sentry/status-sentry-wp
```

### Manual Installation from Source

```bash
# Clone the repository
git clone https://github.com/status-sentry/status-sentry-wp.git

# Navigate to the plugin directory
cd status-sentry-wp

# Install dependencies (if applicable)
composer install --no-dev --optimize-autoloader
```

## Requirements

- WordPress 5.6 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher (for native JSON support)
- WP-Cron enabled or alternative cron setup

## Usage

### Dashboard

The Status Sentry dashboard provides an overview of your site's health and performance:

1. Navigate to **Status Sentry → Dashboard** in the WordPress admin menu
2. View event summaries grouped by monitoring feature
3. Check recent events to identify potential issues
4. Use the quick action buttons to access detailed reports

### Configuration

Configure Status Sentry to match your site's needs:

1. Navigate to **Status Sentry → Settings**
2. Enable or disable specific monitoring features
3. Adjust sampling rates for performance optimization
4. Configure data retention policies
5. Save your changes

### Viewing Events

Access detailed event information:

1. Navigate to **Status Sentry → Events**
2. Filter events by feature, hook, or time period
3. Click on an event to view detailed information
4. Export event data for external analysis

### Interpreting Data

- **High Memory Usage**: Look for events with memory usage above 50MB
- **Slow Database Queries**: Check for queries taking longer than 1 second
- **Plugin Conflicts**: Watch for errors occurring after plugin activation/deactivation
- **Performance Bottlenecks**: Identify hooks with consistently high execution times

## Troubleshooting

### Common Issues

#### Events Not Being Captured

- Verify that the relevant monitoring feature is enabled in Settings
- Check that WP-Cron is functioning correctly using the built-in verification tools
- Ensure database tables were created successfully by checking the migration logs
- Review the error logs for any JSON encoding or processing errors

#### High Server Load

- Reduce sampling rates in the Settings page
- Disable monitoring features you don't need
- Increase the event processing interval
- Adjust the batch size for event processing
- Check the resource monitoring logs for memory or time limit issues

#### Database Growth

- Adjust data retention policies using the WordPress filters:
  - `status_sentry_events_retention_days` (default: 30)
  - `status_sentry_processed_queue_retention_days` (default: 7)
  - `status_sentry_failed_queue_retention_days` (default: 14)
- Schedule regular database maintenance
- Consider using an external logging solution for long-term storage
- Monitor database size using the cleanup statistics

#### Cron Job Issues

- Verify scheduled tasks using the built-in verification tool
- Check for errors in the cron execution logs
- Consider setting up a server-level cron job to trigger WordPress cron
- Manually trigger the cron jobs for testing purposes

### Diagnostic Tools

Status Sentry includes built-in diagnostic tools:

1. Navigate to **Status Sentry → Settings → Diagnostics**
2. Run the system check to verify configuration
3. View the diagnostic log for detailed information
4. Use the repair tools to fix common issues
5. Check the WordPress debug log for detailed error messages
6. Verify cron tasks and their next scheduled run times

## Development

### Project Structure

```
status-sentry/
├── assets/               # CSS, JS, and images
├── includes/             # Core plugin classes
│   ├── admin/            # Admin interface classes
│   ├── data/             # Data pipeline classes
│   ├── db/               # Database migrations and models
│   │   └── migrations/   # Database migrations
│   └── hooks/            # Hook management classes
├── languages/            # Translation files
├── status-sentry-wp.php  # Main plugin file
└── README.md             # This file
```

### Building from Source

1. Clone the repository
2. Install development dependencies:
   ```bash
   composer install
   ```
3. Make your changes
4. Run tests:
   ```bash
   composer test
   ```
5. Build the distribution package:
   ```bash
   composer build
   ```

### Extending the Plugin

Status Sentry is designed to be extensible:

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

// Process custom event data
add_filter('status_sentry_process_event', function($data, $feature, $hook) {
    if ($feature === 'my_feature') {
        // Add custom processing logic
        $data['my_custom_field'] = 'custom value';
    }
    return $data;
}, 10, 3);
```

## Contributing

We welcome contributions to Status Sentry! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on how to contribute.

## License

This plugin is licensed under the GPL v2 or later. See [LICENSE](LICENSE) for details.

## Credits

Developed by the Status Sentry Team.

## Changelog

### 1.1.0 (2023-06-15)
- Enhanced error handling throughout the data pipeline
- Added resource-aware batch processing to prevent memory exhaustion
- Implemented transaction support for database operations
- Added detailed logging for debugging and monitoring
- Improved cron job scheduling with verification
- Added configurable retention policies through WordPress filters
- Enhanced database migration system with transaction support
- Added database table optimization after cleanup operations
- Improved JSON encoding error handling with fallback mechanisms
- Added caching for database checks to improve performance

### 1.0.0 (2023-05-15)
- Initial release
- Core monitoring features
- Database monitoring
- Conflict detection
- Performance monitoring

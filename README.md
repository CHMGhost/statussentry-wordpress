# Status Sentry WP

A comprehensive WordPress plugin for monitoring site health, capturing performance data, and detecting plugin conflicts with advanced hook management and minimal performance impact.


## Description

Status Sentry WP provides deep insights into your WordPress site's performance and health through sophisticated monitoring capabilities. By leveraging an advanced hook management system with intelligent sampling, the plugin captures critical data with minimal performance overhead.

### Key Features

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

## Installation

### Requirements

- WordPress 5.6 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher (for native JSON support)
- WP-Cron enabled or alternative cron setup

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

## Usage

### Dashboard

The Status Sentry dashboard provides an overview of your site's health and performance:

1. Navigate to **Status Sentry → Dashboard** in the WordPress admin menu
2. View event summaries grouped by monitoring feature
3. Check recent events to identify potential issues
4. Use the quick action buttons to access detailed reports

### Admin Dashboard Widget

Status Sentry includes a dashboard widget that provides at-a-glance information about your site's health:

1. The widget displays a summary of events by category
2. Recent events are listed with their type, source, and timestamp
3. Quick links provide access to detailed reports and settings

### Monitoring System

The monitoring system is the core of Status Sentry, providing:

1. Centralized event tracking with standardized interfaces
2. Event types (info, warning, error, critical, conflict)
3. Priority levels for proper handling of events
4. Context and source tracking for detailed analysis

### Benchmarking

Status Sentry includes a benchmarking system to help you understand your site's performance:

1. Navigate to **Status Sentry → Benchmarks** in the admin menu
2. Run benchmarks to measure performance metrics
3. Compare results over time to track improvements
4. Use the "Toggle Full Width" feature for better visualization

## Configuration

### Settings

Configure Status Sentry through the Settings page:

1. **API Connection**: Configure connection to external monitoring services
2. **Monitoring Options**: Enable/disable specific monitoring features
3. **Sampling Rates**: Adjust sampling rates for different hooks
4. **Retention Policies**: Configure how long data is kept
5. **Notification Settings**: Set up alerts for critical events

### Advanced Configuration

For advanced configuration, Status Sentry provides several WordPress filters:

```php
// Adjust sampling rates
add_filter('status_sentry_sampling_rates', function($rates) {
    $rates['core_monitoring'] = 0.05; // 5% sampling
    return $rates;
});

// Configure retention policy
add_filter('status_sentry_retention_days', function($days) {
    return 14; // Keep data for 14 days
});
```

## Extending

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

// Add a custom event handler
add_filter('status_sentry_event_handlers', function($handlers) {
    $handlers['error'][] = 'my_custom_error_handler';
    return $handlers;
});
```

## Frequently Asked Questions

### Will this plugin slow down my site?

Status Sentry is designed with performance in mind. It uses intelligent sampling and asynchronous processing to minimize the impact on your site's performance. You can also adjust sampling rates and disable features you don't need to further reduce any potential impact.

### How much database space does this plugin use?

The amount of database space used depends on your site's traffic, the enabled features, and your data retention settings. By default, the plugin automatically cleans up old data to prevent database bloat. You can adjust the retention period in the settings.

### Can I export the collected data?

Yes, you can export event data from the Events page for external analysis.

### Does this plugin work with multisite?

Yes, Status Sentry is fully compatible with WordPress multisite installations.

## Documentation

For more detailed documentation, please see:

- **Core Framework**: See `docs/CORE-FRAMEWORK.md` for details on the core framework components
- **Resource Management**: See `docs/RESOURCE-MANAGEMENT.md` for information on resource management features
- **Performance Benchmarking**: See `docs/PERFORMANCE-BENCHMARKING.md` for guidance on performance benchmarking
- **Production Hardening**: See `docs/PRODUCTION-HARDENING.md` for best practices on hardening the plugin for production
- **Getting Started with Extensions**: See `docs/GETTING-STARTED-EXTENDING.md` for a quick guide to extending the plugin

## Development Environment

This repository includes a Docker-based development environment for WordPress:

### Requirements

- Docker
- Docker Compose

### Getting Started

1. Create a `.env` file based on `.env.example`
2. Start the environment:

```bash
docker-compose up -d
```

3. Access WordPress at http://localhost:8000
4. Access phpMyAdmin at http://localhost:8080

### Stopping the Environment

```bash
docker-compose down
```

To remove all data (including the database):

```bash
docker-compose down -v
```

## License

This project is licensed under the GPL v2 or later - see the LICENSE file for details.

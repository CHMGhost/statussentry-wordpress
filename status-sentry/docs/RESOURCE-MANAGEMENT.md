# Status Sentry Resource Management

This document provides an overview of the resource management features in the Status Sentry WordPress plugin. These features help ensure that the plugin operates efficiently and doesn't overload the server, even under high traffic or resource-constrained environments.

## Memory Management

### Memory Budgets

The plugin uses tiered memory budgets for different types of tasks:

| Tier      | Memory Budget | Time Budget | DB Queries Budget |
|-----------|--------------|-------------|-------------------|
| Critical  | 10 MB        | 10 seconds  | 50 queries        |
| Standard  | 20 MB        | 20 seconds  | 100 queries       |
| Intensive | 50 MB        | 45 seconds  | 500 queries       |
| Report    | 100 MB       | 120 seconds | 1000 queries      |

These budgets can be customized using the `status_sentry_resource_budgets` filter:

```php
add_filter('status_sentry_resource_budgets', function($budgets) {
    // Increase memory budget for intensive tasks
    $budgets['intensive']['memory'] = 75 * 1024 * 1024; // 75 MB
    return $budgets;
});
```

### Garbage Collection

The plugin implements intelligent garbage collection to free up memory when needed:

- GC is triggered when memory usage exceeds 80% of the PHP memory limit
- GC is forced after specific tasks like 'cleanup' and 'process_queue'
- Multiple GC cycles (default: 3) are run to maximize memory recovery
- GC stops early if it's not freeing significant memory

GC settings can be customized using the `status_sentry_gc_settings` filter:

```php
add_filter('status_sentry_gc_settings', function($settings) {
    // Trigger GC at 75% memory usage instead of 80%
    $settings['memory_threshold'] = 0.75;
    // Add more tasks that should force GC
    $settings['force_after_tasks'][] = 'my_custom_task';
    return $settings;
});
```

## CPU Load Monitoring

The plugin monitors CPU load and adapts its behavior accordingly:

- Default CPU threshold is 70% (0.7)
- Tasks are aborted when system is overloaded
- Scheduler adds delays proportional to system load
- Different task tiers get different delay strategies

The CPU threshold can be customized using the `status_sentry_cpu_threshold` filter:

```php
add_filter('status_sentry_cpu_threshold', function($threshold) {
    // Set a more aggressive threshold of 60%
    return 0.6;
});
```

## Database Operations

### Query Caching

The plugin implements a database-backed query cache to reduce database load:

- Default TTL is 1 hour (3600 seconds)
- Automatic cleanup of expired cache entries
- Group-based caching for better organization
- Efficient indexing for fast retrieval

Cache TTL can be customized using filters:

```php
// Change default TTL to 2 hours
add_filter('status_sentry_query_cache_default_ttl', function($ttl) {
    return 7200; // 2 hours
});

// Set different TTL for specific cache groups
add_filter('status_sentry_query_cache_ttl', function($ttl, $group) {
    if ($group === 'status_pages') {
        return 1800; // 30 minutes for status pages
    }
    return $ttl;
}, 10, 2);
```

### Batch Processing

The plugin uses batch processing for database operations:

- Default batch size is 100 items
- Configurable via settings and filters
- Automatic scheduling of immediate processing when queue gets too large
- Resumable processing for large datasets

Batch settings can be customized:

```php
// Change queue threshold for immediate processing
add_filter('status_sentry_queue_threshold', function($threshold) {
    return 50; // Schedule processing when 50+ items are in queue
});

// Change database batch size
add_filter('status_sentry_db_batch_size', function($size) {
    return 50; // Process 50 items at a time
});
```

## Resumable Processing

The plugin supports resumable processing for large datasets:

1. When processing events, it checks resource usage (memory, time, CPU)
2. If resources are constrained, it saves its state and returns a special array
3. The scheduler detects this and schedules a continuation task
4. When the task runs again, it retrieves the saved state and resumes from where it left off

This approach ensures that large datasets can be processed efficiently without exhausting server resources, and processing can be resumed after interruptions.

## Monitoring and Logging

The plugin includes comprehensive monitoring and logging:

- Detailed logging of resource usage
- Tracking of task execution times and memory usage
- Automatic detection of stuck or failed tasks
- Health checks to ensure the system is operating correctly

## Configuration

Most resource management settings can be configured via:

1. WordPress filters (as shown in examples above)
2. The Status Sentry settings page in the WordPress admin
3. Direct API calls to the resource management classes

## Best Practices

1. **Monitor resource usage**: Check the Status Sentry logs to see how much memory and CPU your tasks are using.
2. **Adjust budgets as needed**: If you see tasks consistently hitting resource limits, consider increasing their budgets.
3. **Use appropriate tiers**: Assign tasks to the appropriate tier based on their resource needs.
4. **Implement caching**: Use the query cache for frequently accessed data.
5. **Batch operations**: Use batch processing for large datasets.
6. **Schedule intensive tasks during off-peak hours**: Use the scheduler to run resource-intensive tasks when the server is less busy.

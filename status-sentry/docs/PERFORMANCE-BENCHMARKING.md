# Status Sentry Performance Benchmarking

This document outlines the performance benchmarking process for the Status Sentry WordPress plugin. Performance benchmarking is essential to ensure that the plugin operates efficiently and doesn't negatively impact the WordPress site's performance.

## Performance Targets

Status Sentry defines specific performance targets for key operations to ensure consistent performance across different environments. These targets are defined in `tests/test-performance-benchmark.php` and include:

| Operation | Memory Target | Time Target | Description |
|-----------|---------------|-------------|-------------|
| Resource Manager should_continue() | 2 MB | 10 ms | Resource Manager's should_continue() method |
| Event Processor process_events() | 10 MB | 500 ms | Event Processor's process_events() method with 10 events |
| Event Queue enqueue() | 1 MB | 50 ms | Event Queue's enqueue() method |
| Query Cache get() | 1 MB | 10 ms | Query Cache's get() method |
| Query Cache set() | 1 MB | 10 ms | Query Cache's set() method |
| Data Capture capture() | 2 MB | 50 ms | Data Capture's capture() method |

These targets represent the maximum acceptable memory usage and execution time for each operation. They are designed to ensure that the plugin remains efficient even on resource-constrained environments.

## Running Benchmarks

### Standard Benchmarks

To run the standard performance benchmarks, use the following command:

```bash
php status-sentry/run-tests.php
```

This will execute all tests, including the performance benchmarks defined in `tests/test-performance-benchmark.php`.

### Comparative Benchmarks

To compare performance across different configurations, use the comparative benchmark script:

```bash
php status-sentry/tests/test-performance-comparison.php
```

This script runs benchmarks with multiple configurations and generates a comparison table showing the impact of different settings on performance.

### Sample Standard Benchmark Output

```
Running performance benchmark tests...
Testing Resource Manager performance...
Resource Manager should_continue() performance:
  Memory usage: 0.0123 MB (target: 2 MB) - PASSED
  Execution time: 0.0456 ms (target: 10 ms) - PASSED
Testing Event Processor performance...
Event Processor process_events() performance:
  Memory usage: 3.4567 MB (target: 10 MB) - PASSED
  Execution time: 123.4567 ms (target: 500 ms) - PASSED
Testing Query Cache performance...
Query Cache set() performance:
  Memory usage: 0.1234 MB (target: 1 MB) - PASSED
  Execution time: 0.2345 ms (target: 10 ms) - PASSED
Query Cache get() performance:
  Memory usage: 0.0123 MB (target: 1 MB) - PASSED
  Execution time: 0.1234 ms (target: 10 ms) - PASSED

Performance benchmark results: 3/3 tests passed.
```

### Sample Comparative Benchmark Output

```
Performance Comparison Results
=============================

| Operation | Metric | Default | High Performance | Low Memory | Balanced |
|-----------|--------|------------|------------|------------|------------|
| Resource Manager should_continue() | Memory (MB) | 0.0123 | 0.0098 | 0.0087 | 0.0105 |
| Resource Manager should_continue() | Time (ms) | 0.0456 | 0.0412 | 0.0478 | 0.0432 |
| Event Processor process_events() | Memory (MB) | 3.4567 | 3.1245 | 2.8976 | 3.2134 |
| Event Processor process_events() | Time (ms) | 123.4567 | 98.7654 | 145.6789 | 110.3456 |
| Event Queue enqueue() | Memory (MB) | 0.2345 | 0.2123 | 0.1987 | 0.2234 |
| Event Queue enqueue() | Time (ms) | 1.2345 | 1.0123 | 1.3456 | 1.1234 |
| Query Cache set() | Memory (MB) | 0.1234 | 0.1345 | N/A | 0.1289 |
| Query Cache set() | Time (ms) | 0.2345 | 0.1987 | N/A | 0.2123 |
| Query Cache get() | Memory (MB) | 0.0123 | 0.0134 | N/A | 0.0129 |
| Query Cache get() | Time (ms) | 0.1234 | 0.0987 | N/A | 0.1123 |
| Data Capture capture() | Memory (MB) | 0.3456 | 0.3123 | 0.2987 | 0.3234 |
| Data Capture capture() | Time (ms) | 2.3456 | 2.0123 | 2.5678 | 2.1234 |

Summary
-------

Default: 6/6 tests passed
High Performance: 6/6 tests passed
Low Memory: 4/4 tests passed
Balanced: 6/6 tests passed
```

## Interpreting Results

The benchmark results show:

1. **Memory Usage**: The amount of memory used by each operation, measured in megabytes (MB).
2. **Execution Time**: The time taken to execute each operation, measured in milliseconds (ms).
3. **Pass/Fail Status**: Whether the operation meets the defined targets.

If an operation fails to meet its targets, you should investigate the cause and optimize the code accordingly.

## Collecting and Tracking Results

It's important to track benchmark results over time to identify performance regressions. Create a results table like the following:

| Date | Version | Environment | Operation | Memory (MB) | Time (ms) | Status |
|------|---------|-------------|-----------|-------------|-----------|--------|
| 2023-06-01 | 1.3.0 | PHP 7.4, WP 6.0 | Resource Manager should_continue() | 0.0123 | 0.0456 | PASSED |
| 2023-06-01 | 1.3.0 | PHP 7.4, WP 6.0 | Event Processor process_events() | 3.4567 | 123.4567 | PASSED |
| 2023-06-01 | 1.3.0 | PHP 7.4, WP 6.0 | Query Cache set() | 0.1234 | 0.2345 | PASSED |
| 2023-06-01 | 1.3.0 | PHP 7.4, WP 6.0 | Query Cache get() | 0.0123 | 0.1234 | PASSED |

This table helps you track performance changes across different versions and environments.

## Optimizing Performance

If an operation fails to meet its targets, consider the following optimization strategies:

### Memory Optimization

1. **Reduce Object Creation**: Minimize the creation of unnecessary objects.
2. **Use Generators**: Replace arrays with generators for large datasets.
3. **Implement Garbage Collection**: Trigger garbage collection at appropriate points.
4. **Optimize Data Structures**: Use more memory-efficient data structures.
5. **Limit Variable Scope**: Unset variables when they're no longer needed.

### Time Optimization

1. **Batch Operations**: Process multiple items in a single database operation.
2. **Implement Caching**: Cache frequently accessed data.
3. **Optimize Database Queries**: Ensure queries are efficient and properly indexed.
4. **Reduce External API Calls**: Minimize calls to external APIs.
5. **Use Asynchronous Processing**: Move time-consuming operations to background processes.

## Updating Performance Targets

Performance targets should be reviewed and updated periodically based on:

1. **Hardware Improvements**: As hardware improves, targets can be made more stringent.
2. **Code Optimizations**: As the code is optimized, targets can be adjusted to reflect the improvements.
3. **User Feedback**: If users report performance issues, targets may need to be adjusted.

To update the targets, modify the `$performance_targets` array in `tests/test-performance-benchmark.php`.

## Benchmark Environment

When running benchmarks, document the environment to ensure consistent comparisons:

- PHP version
- WordPress version
- MySQL version
- Server specifications (CPU, memory)
- Other active plugins
- Caching configuration

## Continuous Integration

Consider integrating performance benchmarks into your continuous integration (CI) pipeline to automatically detect performance regressions. This can be done by:

1. Running benchmarks on each commit
2. Comparing results against previous runs
3. Failing the build if performance degrades significantly

## Creating Custom Benchmark Configurations

You can create custom configurations for comparative benchmarking by modifying the `$test_configurations` array in `tests/test-performance-comparison.php`. Each configuration should include:

- `label`: A short name for the configuration
- `description`: A longer description of the configuration
- `settings`: An array of plugin settings to apply during benchmarking

Example of adding a new configuration:

```php
$test_configurations['custom_config'] = [
    'label' => 'Custom Config',
    'description' => 'My custom configuration for testing',
    'settings' => [
        'db_batch_size' => 150,
        'memory_threshold' => 75,
        'gc_cycles' => 4,
        'cpu_threshold' => 65,
        'enable_query_cache' => 1,
        'query_cache_ttl' => 5400,
        'enable_resumable_tasks' => 1
    ]
];
```

The available settings that can be modified include:

| Setting | Description | Default |
|---------|-------------|---------|
| db_batch_size | Number of items to process in a single database operation | 100 |
| memory_threshold | Percentage of memory limit at which garbage collection is triggered | 80 |
| gc_cycles | Number of garbage collection cycles to run when triggered | 3 |
| cpu_threshold | Percentage of CPU load at which tasks are delayed | 70 |
| enable_query_cache | Whether to cache frequent database queries | 1 (enabled) |
| query_cache_ttl | Time to live for cached queries in seconds | 3600 |
| enable_resumable_tasks | Whether to allow long-running tasks to be resumed | 1 (enabled) |
| events_retention_days | Number of days to keep events in the database | 30 |
| processed_queue_retention_days | Number of days to keep processed queue items | 7 |
| failed_queue_retention_days | Number of days to keep failed queue items | 14 |
| task_runs_retention_days | Number of days to keep task run history | 30 |

## References

- [Status Sentry Performance Benchmark Test](../tests/test-performance-benchmark.php)
- [Status Sentry Comparative Benchmark Test](../tests/test-performance-comparison.php)
- [Status Sentry Benchmark Runner](../includes/benchmarking/class-status-sentry-benchmark-runner.php)
- [Status Sentry Run Tests Script](../run-tests.php)
- [PHP Memory Management](https://www.php.net/manual/en/features.gc.php)
- [WordPress Performance Best Practices](https://developer.wordpress.org/plugins/performance/)

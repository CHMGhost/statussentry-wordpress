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

To run the performance benchmarks, use the following command:

```bash
php status-sentry/run-tests.php
```

This will execute all tests, including the performance benchmarks defined in `tests/test-performance-benchmark.php`.

### Sample Output

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

## References

- [Status Sentry Performance Benchmark Test](../tests/test-performance-benchmark.php)
- [Status Sentry Run Tests Script](../run-tests.php)
- [PHP Memory Management](https://www.php.net/manual/en/features.gc.php)
- [WordPress Performance Best Practices](https://developer.wordpress.org/plugins/performance/)

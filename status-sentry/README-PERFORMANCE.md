# Status Sentry Performance Enhancements

Version 1.2.0 introduces significant performance improvements to the Status Sentry WordPress plugin. These enhancements focus on memory efficiency, database optimization, and resource management to ensure the plugin operates efficiently even on resource-constrained environments.

## Key Enhancements

### 1. Memory Management

- **Explicit Garbage Collection**: The Resource Manager now includes explicit garbage collection triggers based on configurable memory thresholds.
- **Memory Usage Monitoring**: Real-time memory usage tracking prevents excessive memory consumption.
- **GC Cycles**: Configurable number of garbage collection cycles to optimize memory reclamation.

### 2. Database Optimizations

- **Batch Database Operations**: All database operations now support batching to reduce the number of queries.
- **Query Caching**: Frequently executed queries are now cached to reduce database load.
- **Composite Indexes**: New database indexes improve query performance for common operations.

### 3. Task Processing

- **Resumable Tasks**: Long-running tasks can now be paused and resumed if they exceed resource limits.
- **Task State Persistence**: Task state is saved to the database, allowing for seamless resumption.
- **CPU Load Monitoring**: Tasks can be delayed based on system CPU load to prevent overloading the server.

### 4. Event Processing

- **Generator-Based Processing**: Events are now processed using generators to reduce memory usage.
- **Streaming Support**: Large datasets are processed in a streaming fashion to maintain a small memory footprint.
- **Adaptive Batch Sizes**: Batch sizes are adjusted based on available resources.

### 5. Data Retention

- **Configurable Retention Policies**: All data retention periods are now configurable through the admin interface.
- **Automatic Cleanup**: Expired data is automatically cleaned up to prevent database bloat.
- **Tiered Retention**: Different types of data have different retention periods based on their importance.

## Configuration Options

All performance enhancements can be configured through the new "Performance" tab in the Status Sentry settings page. The following options are available:

### Database Operations

- **Batch Size**: Number of items to process in a single database operation (default: 100)
- **Enable Query Cache**: Cache frequent database queries (default: enabled)
- **Query Cache TTL**: Time to live for cached queries in seconds (default: 3600)

### Memory Management

- **Memory Threshold**: Percentage of memory limit at which garbage collection is triggered (default: 80%)
- **Garbage Collection Cycles**: Number of garbage collection cycles to run when triggered (default: 3)

### CPU Management

- **CPU Threshold**: Percentage of CPU load at which tasks are delayed (default: 70%)

### Task Processing

- **Enable Resumable Tasks**: Allow long-running tasks to be resumed if they exceed resource limits (default: enabled)

### Data Retention

- **Events Retention**: Number of days to keep events in the database (default: 30 days)
- **Processed Queue Items Retention**: Number of days to keep processed queue items (default: 7 days)
- **Failed Queue Items Retention**: Number of days to keep failed queue items (default: 14 days)
- **Task Runs Retention**: Number of days to keep task run history (default: 30 days)

## Technical Implementation

### New Database Tables

- **Task State Table**: Stores the state of long-running tasks for resumption
- **Query Cache Table**: Stores cached query results with automatic expiration

### New Classes

- **Status_Sentry_Task_State_Manager**: Manages the persistence and retrieval of task state
- **Status_Sentry_Query_Cache**: Provides a simple API for query caching

### Enhanced Classes

- **Status_Sentry_Resource_Manager**: Now includes garbage collection and CPU load monitoring
- **Status_Sentry_Scheduler**: Now supports resumable tasks and adaptive scheduling
- **Status_Sentry_Event_Processor**: Now uses generators and batch processing
- **Status_Sentry_Event_Queue**: Now supports batch operations and ID-based retrieval

## Best Practices

1. **Adjust Batch Sizes**: For high-traffic sites, increase batch sizes to reduce the number of database operations. For low-memory environments, decrease batch sizes to reduce memory usage.

2. **Memory Threshold**: Set the memory threshold based on your server's available memory. Lower values (e.g., 70%) are more conservative but may trigger garbage collection more frequently.

3. **CPU Threshold**: Set the CPU threshold based on your server's available CPU resources. Lower values (e.g., 50%) are more conservative but may delay tasks more frequently.

4. **Data Retention**: Adjust data retention periods based on your needs. Shorter retention periods reduce database size but limit historical data availability.

5. **Query Cache**: Enable query caching for frequently accessed data, but be aware that it increases memory usage.

## Troubleshooting

If you experience performance issues with the Status Sentry plugin, try the following:

1. **Reduce Batch Sizes**: Lower the batch size to reduce memory usage during processing.
2. **Lower Memory Threshold**: Decrease the memory threshold to trigger garbage collection earlier.
3. **Increase GC Cycles**: Increase the number of garbage collection cycles to reclaim more memory.
4. **Disable Query Cache**: If memory usage is a concern, disable the query cache.
5. **Shorten Retention Periods**: Reduce data retention periods to decrease database size.

## Changelog

### Version 1.2.0

- Added explicit garbage collection support
- Added query caching
- Added resumable task support
- Added generator-based event processing
- Added batch database operations
- Added composite database indexes
- Added CPU load monitoring
- Added configurable data retention
- Added performance settings UI

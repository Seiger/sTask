---
title: Developer Guide
sidebar_label: Developer Guide
sidebar_position: 3
---

# Developer Guide

## Creating Custom Workers

To create a custom worker, implement the `TaskInterface`:

```php
<?php namespace YourNamespace\Workers;

use Seiger\sTask\Contracts\TaskInterface;

class EmailWorker implements TaskInterface
{
    /**
     * Execute the task with given data
     */
    public function execute(array $data): bool
    {
        try {
            // Your task logic here
            $recipients = $data['recipients'] ?? [];
            $subject = $data['subject'] ?? 'Default Subject';
            $template = $data['template'] ?? 'default';
            
            foreach ($recipients as $email) {
                $this->sendEmail($email, $subject, $template);
            }
            
            return true;
        } catch (\Exception $e) {
            // Log error
            \Log::error('Email worker failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get the unique type/identifier of the worker
     */
    public static function getType(): string
    {
        return 'email_campaign';
    }
    
    /**
     * Get a human-readable description of the worker
     */
    public static function getDescription(): string
    {
        return 'Send bulk email campaigns';
    }
    
    private function sendEmail(string $email, string $subject, string $template): void
    {
        // Email sending logic
    }
}
```

## Worker Discovery

Workers are automatically discovered if they:
1. Implement the `TaskInterface`
2. Are not abstract classes
3. Are not in excluded namespaces
4. Can be instantiated

### Excluded Namespaces

The following namespaces are excluded from automatic discovery:
- `Illuminate\`
- `Symfony\`
- `EvolutionCMS\`
- `Composer\`
- `Doctrine\`
- `GuzzleHttp\`
- `Monolog\`
- `Psr\`
- `Tracy\`
- `voku\`
- `Webmozart\`
- `Seiger\sTask\`

## Task Management API

### Creating Tasks

```php
use Seiger\sTask\Facades\sTask;

// Basic task creation
$task = sTask::create(
    identifier: 'worker_identifier',
    action: 'action_name',
    data: ['key' => 'value']
);

// Advanced task creation with all options
$task = sTask::create(
    identifier: 'worker_identifier',
    action: 'action_name',
    data: ['key' => 'value'],
    priority: 'high', // low, normal, high
    userId: 123
);
```

### Task Execution

```php
// Execute a specific task
$success = sTask::execute($task);

// Process all pending tasks
$processedCount = sTask::processPendingTasks(10); // batch size
```

### Worker Management

```php
// Discover new workers
$registered = sTask::discoverWorkers();

// Register a specific worker
$worker = sTask::registerWorker('YourNamespace\\Workers\\EmailWorker');

// Activate/deactivate workers
sTask::activateWorker('email_campaign');
sTask::deactivateWorker('email_campaign');

// Get all workers
$workers = sTask::getWorkers();
$activeWorkers = sTask::getWorkers(true); // active only
```

### Task Statistics

```php
$stats = sTask::getStats();
/*
Returns:
[
    'pending' => 5,
    'running' => 2,
    'completed' => 150,
    'failed' => 3,
    'cancelled' => 1,
    'total' => 161
]
*/
```

### Logging

```php
// Log a message for a task
sTask::log($task, 'info', 'Task started processing');
sTask::log($task, 'error', 'Task failed', ['error' => $exception->getMessage()]);

// Get task logs
$logs = $task->getLogs();
$lastLogs = $task->getLastLogs(10);
$errorLogs = $task->getErrorLogs();
```

## Database Schema

### s_workers Table

```sql
CREATE TABLE `s_workers` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `uuid` char(36) DEFAULT NULL,
    `identifier` varchar(255) NOT NULL,
    `class` varchar(255) NOT NULL,
    `active` tinyint(1) NOT NULL DEFAULT 0,
    `position` int(10) unsigned NOT NULL DEFAULT 0,
    `settings` json DEFAULT NULL,
    `hidden` int(10) unsigned NOT NULL DEFAULT 0,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `s_workers_identifier_unique` (`identifier`)
);
```

### s_tasks Table

```sql
CREATE TABLE `s_tasks` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `identifier` varchar(255) NOT NULL,
    `action` varchar(255) NOT NULL,
    `status` smallint(5) unsigned NOT NULL DEFAULT 10,
    `message` varchar(255) DEFAULT NULL,
    `started_by` int(10) unsigned DEFAULT NULL,
    `meta` longtext DEFAULT NULL,
    `result` longtext DEFAULT NULL,
    `start_at` timestamp NULL DEFAULT NULL,
    `finished_at` timestamp NULL DEFAULT NULL,
    `attempts` int(11) NOT NULL DEFAULT 0,
    `max_attempts` int(11) NOT NULL DEFAULT 3,
    `priority` varchar(255) NOT NULL DEFAULT 'normal',
    `progress` int(11) NOT NULL DEFAULT 0,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `s_tasks_identifier_action_index` (`identifier`, `action`),
    KEY `s_tasks_status_index` (`status`)
);
```

## Task Status Codes

- `10` - Pending (waiting to be executed)
- `20` - Running (currently executing)
- `30` - Completed (successfully finished)
- `40` - Failed (encountered an error)
- `50` - Cancelled (manually cancelled)

## Priority Levels

- `low` - Lowest priority, executed last
- `normal` - Default priority
- `high` - Highest priority, executed first

## Events and Hooks

sTask integrates with Evolution CMS events. You can listen to task events:

```php
// Listen to task completion
evo()->event->listen('sTask.taskCompleted', function($task) {
    // Handle task completion
});

// Listen to task failure
evo()->event->listen('sTask.taskFailed', function($task) {
    // Handle task failure
});
```

## Best Practices

### Worker Design
1. **Single Responsibility**: Each worker should handle one specific type of task
2. **Error Handling**: Always handle exceptions gracefully
3. **Progress Updates**: Update task progress for long-running operations
4. **Resource Management**: Clean up resources after task completion

### Task Data
1. **Serializable**: Task data must be serializable (no closures or resources)
2. **Minimal**: Only include necessary data to reduce memory usage
3. **Validated**: Validate task data before execution

### Performance
1. **Batch Processing**: Process data in batches for large datasets
2. **Memory Management**: Monitor memory usage in long-running tasks
3. **Timeout Handling**: Set appropriate timeouts for external operations

## Troubleshooting

### Common Issues

1. **Worker Not Discovered**
   - Check if class implements `TaskInterface`
   - Verify namespace is not excluded
   - Ensure class is autoloaded

2. **Task Not Executing**
   - Check worker is active
   - Verify task data is valid
   - Check for PHP errors in logs

3. **Memory Issues**
   - Monitor memory usage in workers
   - Use batch processing for large datasets
   - Increase PHP memory limit if needed

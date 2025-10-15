---
title: Admin Interface Guide
sidebar_label: Admin Interface
sidebar_position: 4
---

# Admin Interface Guide

## Accessing sTask Manager

After installation, access the sTask admin interface through:

**Manager → Tools → Task Manager**

The interface consists of three main sections:
- **Dashboard** - Overview of tasks and statistics
- **Workers** - Worker management and discovery
- **Statistics** - Detailed analytics and reports

## Dashboard Tab

![sTask Dashboard](/img/admin/stask.jpg)

### Overview Widgets

The dashboard displays five key metrics:

1. **Pending Tasks** - Tasks waiting for execution
2. **Running Tasks** - Tasks currently being processed
3. **Completed Tasks** - Successfully finished tasks
4. **Failed Tasks** - Tasks that encountered errors
5. **Total Tasks** - All tasks in the system

### Recent Tasks Table

Shows the 10 most recent tasks with:
- **Task ID** - Unique identifier
- **Worker** - Worker identifier and scope
- **Action** - Action being performed
- **Status** - Current status with color coding
  - Blue - Pending
  - Yellow - Running
  - Green - Completed
  - Red - Failed
  - Gray - Cancelled
- **Progress** - Visual progress bar (0-100%)
- **Created** - Creation timestamp
- **Actions** - Quick action buttons

### Dashboard Actions

**Process Tasks** button:
- Processes all pending tasks immediately
- Shows notification with number of tasks processed
- Refreshes the page after completion

**Clean Old Tasks** button:
- Removes completed tasks older than 30 days
- Cleans log files older than 30 days
- Shows notification with cleanup results

## Workers Tab

![sTask Workers](/img/admin/workers.jpg)

### Worker List

Displays all registered workers with:
- **Icon** - Visual identifier for the worker
- **Identifier** - Unique worker ID
- **Title** - Human-readable name
- **Scope** - Module/package the worker belongs to
- **Description** - Detailed description of worker functionality
- **Status** - Active/Inactive indicator
- **Tasks Count** - Number of tasks created by this worker
- **Actions** - Activate/Deactivate toggle

### Worker Management Actions

**Discover Workers** button:
- Scans all installed Composer packages
- Finds classes implementing `TaskInterface`
- Registers new workers automatically
- Shows notification with discovery results

**Rescan Workers** button:
- Updates metadata for existing workers
- Useful after worker code updates
- Shows notification with updated count

**Clean Orphaned** button:
- Removes workers whose classes no longer exist
- Useful after uninstalling packages
- Shows notification with deletion count

### Worker Status

**Active Workers** (Green):
- Available for task creation
- Will process assigned tasks
- Visible in worker selection

**Inactive Workers** (Gray):
- Not available for new tasks
- Existing tasks can still complete
- Hidden from worker selection

### Activating/Deactivating Workers

Click the toggle button next to a worker to:
- **Activate** - Enable worker for task creation
- **Deactivate** - Disable worker temporarily

## Creating Tasks

### From Admin Interface

Currently, tasks are created programmatically. Future versions will include a task creation form in the admin interface.

### From Code

```php
use Seiger\sTask\Facades\sTask;

$task = sTask::create(
    identifier: 'product',
    action: 'import',
    data: ['file' => 'products.csv'],
    priority: 'high',
    userId: evo()->getLoginUserID()
);
```

## Monitoring Task Execution

### Real-time Progress

Task progress is tracked in real-time through:
- **Progress Bar** - Visual indicator (0-100%)
- **Status Updates** - Color-coded status badges
- **Message Updates** - Current operation description

### Task Details

Click on a task to view:
- Full execution logs
- Progress history
- Error messages
- Task metadata
- Result files

### Log Viewing

Each task has detailed logs showing:
- **Timestamp** - When the log entry was created
- **Level** - info, warning, or error
- **Message** - Log message
- **Context** - Additional data (JSON)

Filter logs by level:
- **All** - Show all log entries
- **Info** - Informational messages
- **Warning** - Non-critical issues
- **Error** - Critical errors

### Downloading Logs

Click the **Download Logs** button to:
- Export task logs as `.log` file
- Useful for debugging
- Share with support team

## Task Actions

### Execute Task

Manually trigger task execution:
- Click **Execute** button
- Task status changes to "running"
- Progress updates in real-time
- Completion notification shown

### Cancel Task

Stop a running or pending task:
- Click **Cancel** button
- Task status changes to "cancelled"
- Task will not be processed
- Can be restarted later

### Retry Task

Retry a failed task:
- Click **Retry** button
- Task status resets to "pending"
- Will be processed in next batch
- Attempt counter increments

### View Details

Open detailed task view:
- Click **Details** button or task row
- Shows full task information
- Displays execution logs
- Shows progress history

## Statistics Tab

### Overview Statistics

Comprehensive metrics including:
- **Total Tasks** - All tasks in system
- **Success Rate** - Percentage of completed tasks
- **Failure Rate** - Percentage of failed tasks
- **Average Duration** - Average task execution time
- **Tasks by Status** - Distribution chart

### Worker Statistics

Per-worker metrics:
- **Tasks Created** - Total tasks for each worker
- **Success/Failure Ratio** - Worker reliability
- **Average Execution Time** - Performance metrics
- **Last Execution** - Most recent task

### Time-based Statistics

Task activity over time:
- **Today** - Tasks created/completed today
- **This Week** - Weekly activity
- **This Month** - Monthly activity
- **Custom Range** - Select date range

## Best Practices

### Task Management

1. **Clean Old Tasks Regularly**
   - Use the "Clean Old Tasks" button
   - Or schedule automated cleanup
   - Prevents database bloat

2. **Monitor Failed Tasks**
   - Check failed tasks daily
   - Investigate error patterns
   - Fix underlying issues

3. **Use Appropriate Priorities**
   - `high` - Critical operations
   - `normal` - Standard tasks
   - `low` - Background operations

### Worker Management

1. **Activate Only Needed Workers**
   - Deactivate unused workers
   - Reduces clutter
   - Improves performance

2. **Regular Worker Discovery**
   - Run after package updates
   - Ensures new workers are registered
   - Updates worker metadata

3. **Clean Orphaned Workers**
   - Run after uninstalling packages
   - Prevents database clutter
   - Maintains system cleanliness

### Performance Optimization

1. **Batch Processing**
   - Process tasks in batches
   - Use appropriate batch sizes
   - Balance performance vs. resources

2. **Progress Updates**
   - Update progress periodically (not every item)
   - Reduces file I/O overhead
   - Improves overall performance

3. **Log Management**
   - Clean old logs regularly
   - Use appropriate log levels
   - Avoid excessive logging

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `R` | Refresh current view |
| `D` | Go to Dashboard |
| `W` | Go to Workers |
| `S` | Go to Statistics |
| `?` | Show help |

## Troubleshooting

### Tasks Stuck in "Running" Status

If a task is stuck in running status:

1. Check if the worker process crashed
2. View task logs for errors
3. Manually cancel the task
4. Restart the task worker

```php
// Reset stuck tasks
\Seiger\sTask\Models\sTaskModel::where('status', 20)
    ->where('updated_at', '<', now()->subHours(1))
    ->update(['status' => 40, 'message' => 'Task timeout']);
```

### Workers Not Appearing

If workers don't appear in the list:

1. Click "Discover Workers"
2. Check if worker implements `TaskInterface`
3. Verify worker is in a Composer package
4. Check application logs for errors

### Permission Errors

If you see permission errors:

1. Check storage directory permissions:
```console
chmod -R 755 storage/stask
```

2. Verify web server user ownership:
```console
chown -R www-data:www-data storage/stask
```

3. Check PHP file creation permissions

## Admin Interface Customization

### Localization

sTask supports multiple languages:
- English (`en`)
- Ukrainian (`uk`)
- Russian (`ru`)

Add your own translations in `lang/{locale}/global.php`

## Security Considerations

### Access Control

sTask admin interface is protected by:
- Evolution CMS authentication
- Manager role requirements
- Session validation

### Task Permissions

Tasks track which user created them:
```php
$task->started_by // User ID who created the task
```

Implement custom permission checks:
```php
if ($task->started_by !== evo()->getLoginUserID() && !evo()->hasPermission('admin')) {
    throw new \Exception('Unauthorized');
}
```

### Sensitive Data

Avoid storing sensitive data in task metadata:
- Don't store passwords
- Don't store API keys
- Use worker settings for credentials
- Sanitize user input

```php
// BAD
$task = sTask::create('api', 'sync', [
    'api_key' => 'secret123' // Don't do this!
]);

// GOOD
class ApiWorker extends BaseWorker
{
    public function settings(): array
    {
        return [
            'api_key' => config('services.api.key') // Store in config
        ];
    }
}
```

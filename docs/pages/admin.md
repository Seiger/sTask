---
title: Admin UI
sidebar_label: Admin UI
sidebar_position: 4
---

# Admin UI

## Dashboard
The main dashboard provides an overview of your task system:

- **Task Statistics**: Pending, running, completed, failed, and total task counts
- **Recent Activity**: Latest 10 tasks with their status and progress
- **Quick Actions**: Process pending tasks and clean old tasks
- **Task Details**: View individual task information, logs, and execution history

### Dashboard Widgets
- 📊 **Pending Tasks**: Tasks waiting to be executed
- ⚡ **Running Tasks**: Currently executing tasks
- ✅ **Completed Tasks**: Successfully finished tasks
- ❌ **Failed Tasks**: Tasks that encountered errors
- 📋 **Total Tasks**: Overall task count

### Recent Activity Table
Shows the most recent tasks with:
- Task ID and identifier
- Action being performed
- Current status with color-coded badges
- Progress bar (0-100%)
- User who started the task
- Creation timestamp
- Quick actions (view details)

## Workers
The Workers tab allows you to manage task workers:

- **Worker List**: All discovered and registered workers
- **Worker Status**: Active/inactive status for each worker
- **Worker Discovery**: Automatically find new workers from installed packages
- **Worker Management**: Activate, deactivate, and manage worker configurations

### Worker Operations

#### Discover Workers
Automatically scans installed packages for classes that implement the `TaskInterface` and registers them as available workers.

#### Rescan Workers
Updates existing worker configurations and checks for changes in worker classes.

#### Clean Orphaned Workers
Removes workers whose classes no longer exist in the system.

#### Activate/Deactivate Workers
Control which workers are available for task execution.

### Worker Information
Each worker displays:
- **Identifier**: Unique identifier for the worker
- **Description**: Human-readable description
- **Class**: Full PHP class name
- **Status**: Active/inactive state
- **Settings**: Configuration options

## Task Management

### Creating Tasks
Tasks can be created programmatically:

```php
use Seiger\sTask\Facades\sTask;

$task = sTask::create(
    identifier: 'email_campaign',
    action: 'send_bulk',
    data: [
        'recipients' => 1000,
        'template' => 'newsletter',
        'subject' => 'Weekly Newsletter'
    ],
    priority: 'normal',
    userId: evo()->getLoginUserID()
);
```

### Task Execution
Tasks are executed automatically when you run:

```php
$processedCount = sTask::processPendingTasks();
```

Or manually through the admin interface.

### Task Monitoring
Monitor task execution through:
- **Real-time Status**: View current task status
- **Progress Tracking**: Monitor task completion percentage
- **Log Files**: Detailed execution logs for each task
- **Error Handling**: Automatic retry and error reporting

## Task Logs

### Viewing Logs
Each task generates detailed log files stored in `storage/stask/`:
- Task start/completion times
- Progress updates
- Error messages and stack traces
- Contextual information

### Log Management
- **Download Logs**: Export task logs for analysis
- **Clear Logs**: Remove log files for specific tasks
- **Automatic Cleanup**: Remove old log files automatically

### Log Levels
- **INFO**: General information about task execution
- **WARNING**: Non-critical issues
- **ERROR**: Critical errors that may cause task failure

## System Operations

### Process Pending Tasks
Execute all pending tasks in priority order:
1. High priority tasks first
2. Normal priority tasks
3. Low priority tasks last

### Clean Old Tasks
Remove completed tasks older than specified days (default: 30 days).

### Clean Old Logs
Remove log files older than specified days (default: 30 days).

## Integration

### Menu Integration
sTask automatically adds a "Task Manager" menu item to the Evolution CMS Tools menu with:
- Custom logo that changes color on hover
- Direct access to dashboard and worker management
- Integration with Evolution CMS theme system

### Asset Management
Assets are automatically published and updated:
- CSS and JavaScript files
- Images and icons
- Version-based asset updates
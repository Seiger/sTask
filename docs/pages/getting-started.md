---
title: Getting Started
sidebar_label: Getting Started
sidebar_position: 2
---

## Requirements

- Evolution CMS **3.2.0+**
- PHP **8.2+**
- Composer **2.2+**
- One of: **MySQL 8.0+** / **MariaDB 10.5+** / **PostgreSQL 10+** / **SQLite 3.25+**

## Installation

### Step 1: Navigate to Core Directory

```console
cd core
```

### Step 2: Update Composer

```console
composer update
```

### Step 3: Install Package

```console
php artisan package:installrequire seiger/stask "*"
```

### Step 4: Publish Assets

```console
php artisan vendor:publish --provider="Seiger\sTask\sTaskServiceProvider"
```

This command will publish:
- Configuration files to `core/config/app/aliases/`
- Public assets (CSS, JS, images) to `public/assets/site/`
- Creates storage directory at `storage/stask/`

### Step 5: Run Migrations

```console
php artisan migrate
```

This creates two database tables:
- `s_workers` - Worker configurations
- `s_tasks` - Task records and execution history

### Step 6: Setup Task Worker

The task worker command processes pending tasks automatically. Add it to your cron or task scheduler:

```console
php artisan stask:worker
```

For continuous processing, add to your crontab:

```cron
* * * * * cd /path/to/your/project && php artisan stask:worker >> /dev/null 2>&1
```

> **Note:** Workers are automatically discovered when you access the Workers tab in the admin interface. No manual discovery needed!

## Where to Find the Module

After installation, access sTask through:

**Manager → Tools → Task Manager**

You'll see:
- **Dashboard Tab** - Task statistics and recent tasks
- **Workers Tab** - Worker management and automatic discovery

## Quick Start Guide

### 1. Create Your First Task (Programmatically)

```php
use Seiger\sTask\Facades\sTask;

// Create a simple task
$task = sTask::create(
    identifier: 'product_sync',  // Worker identifier
    action: 'import',             // Action to perform
    data: [                        // Task data
        'file' => '/path/to/products.csv',
        'delimiter' => ',',
        'skip_first_row' => true
    ],
    priority: 'normal',           // 'low', 'normal', 'high'
    userId: evo()->getLoginUserID()
);

echo "Task #{$task->id} created successfully!\n";
```

### 2. Check Task Status

```php
// Get task by ID
$task = \Seiger\sTask\Models\sTaskModel::find(1);

// Check status
if ($task->isPending()) {
    echo "Task is waiting to be processed\n";
}

if ($task->isRunning()) {
    echo "Task is currently executing\n";
    echo "Progress: {$task->progress}%\n";
}

if ($task->isFinished()) {
    echo "Task is completed\n";
    echo "Status: {$task->status_text}\n";
    echo "Message: {$task->message}\n";
}

// Get detailed information
echo "Worker: {$task->identifier}\n";
echo "Action: {$task->action}\n";
echo "Created: {$task->created_at}\n";
echo "Started by: User #{$task->started_by}\n";
```

### 3. Process Pending Tasks

Tasks are automatically processed by the worker command (`php artisan stask:worker`), but you can also process them programmatically:

```php
// Process all pending tasks
$processedCount = sTask::processPendingTasks();
echo "Processed {$processedCount} tasks\n";

// Or process with custom batch size
$processedCount = sTask::processPendingTasks(batchSize: 5);
```

### 4. View Task Logs

```php
$task = \Seiger\sTask\Models\sTaskModel::find(1);

// Get all logs
$logs = $task->getLogs();
foreach ($logs as $log) {
    echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}\n";
}

// Get last 10 logs
$recentLogs = $task->getLastLogs(10);

// Get only errors
$errorLogs = $task->getErrorLogs();
```

## Basic Usage Examples

### Example 1: Import Products

```php
use Seiger\sTask\Facades\sTask;

// Create import task
$task = sTask::create(
    identifier: 'product',
    action: 'import',
    data: [
        'file' => storage_path('imports/products_2025.csv'),
        'update_existing' => true,
        'create_new' => true
    ],
    priority: 'high'
);

echo "Import task created: #{$task->id}\n";

// Task will be processed automatically by worker
// You can monitor progress in admin interface
```

### Example 2: Export Data

```php
// Create export task
$task = sTask::create(
    identifier: 'product',
    action: 'export',
    data: [
        'format' => 'csv',
        'filters' => [
            'category_id' => 5,
            'active' => true
        ],
        'fields' => ['id', 'sku', 'name', 'price', 'stock']
    ],
    priority: 'normal'
);

// Wait for completion (in real scenario, check via admin or API)
while (!$task->fresh()->isFinished()) {
    sleep(1);
}

if ($task->status === 30) { // completed
    echo "Export completed!\n";
    echo "Download file: {$task->result}\n";
}
```

### Example 3: Bulk Email Campaign

```php
// Create email task
$task = sTask::create(
    identifier: 'email_campaign',
    action: 'send',
    data: [
        'template' => 'newsletter_2025',
        'recipients' => [
            ['email' => 'user1@example.com', 'name' => 'John Doe'],
            ['email' => 'user2@example.com', 'name' => 'Jane Smith'],
            // ... more recipients
        ],
        'subject' => 'Monthly Newsletter - January 2025',
        'attachments' => [
            storage_path('newsletters/january_2025.pdf')
        ]
    ],
    priority: 'normal'
);
```

### Example 4: Scheduled Cleanup

```php
// Create cleanup task scheduled for later
$task = \Seiger\sTask\Models\sTaskModel::create([
    'identifier' => 'system',
    'action' => 'cleanup',
    'status' => 10, // pending
    'priority' => 'low',
    'start_at' => now()->addHours(2), // Run in 2 hours
    'meta' => [
        'clean_logs' => true,
        'clean_cache' => true,
        'older_than_days' => 30
    ]
]);

echo "Cleanup scheduled for: {$task->start_at}\n";
```

## Worker Management

### Discover New Workers

```php
// Discover and register new workers
$registered = sTask::discoverWorkers();

echo "Found and registered " . count($registered) . " new workers:\n";
foreach ($registered as $worker) {
    echo "- {$worker->identifier} ({$worker->scope})\n";
}
```

### Rescan Existing Workers

```php
// Update metadata for existing workers
$updated = sTask::rescanWorkers();

echo "Updated " . count($updated) . " workers\n";
```

### Clean Orphaned Workers

```php
// Remove workers whose classes no longer exist
$deleted = sTask::cleanOrphanedWorkers();

echo "Removed {$deleted} orphaned workers\n";
```

### List All Workers

```php
// Get all workers
$allWorkers = sTask::getWorkers(activeOnly: false);

echo "Total workers: " . $allWorkers->count() . "\n\n";

foreach ($allWorkers as $worker) {
    echo "Worker: {$worker->identifier}\n";
    echo "  Scope: {$worker->scope}\n";
    echo "  Title: {$worker->title}\n";
    echo "  Status: " . ($worker->active ? 'Active' : 'Inactive') . "\n";
    echo "  Class: {$worker->class}\n";
    echo "\n";
}
```

### Filter Workers by Scope

```php
use Seiger\sTask\Models\sWorker;

// Get only sCommerce workers
$commerceWorkers = sWorker::byScope('scommerce')
    ->active()
    ->ordered()
    ->get();

foreach ($commerceWorkers as $worker) {
    echo "{$worker->identifier}: {$worker->title}\n";
}
```

### Activate/Deactivate Workers

```php
// Activate a worker
sTask::activateWorker('product');
echo "Product worker activated\n";

// Deactivate a worker
sTask::deactivateWorker('old_import');
echo "Old import worker deactivated\n";

// Or directly via model
$worker = sWorker::where('identifier', 'product')->first();
$worker->active = true;
$worker->save();
```

## Task Statistics

```php
// Get comprehensive statistics
$stats = sTask::getStats();

echo "Task Statistics:\n";
echo "  Pending: {$stats['pending']}\n";
echo "  Running: {$stats['running']}\n";
echo "  Completed: {$stats['completed']}\n";
echo "  Failed: {$stats['failed']}\n";
echo "  Cancelled: {$stats['cancelled']}\n";
echo "  Total: {$stats['total']}\n";

// Get pending tasks details
$pending = sTask::getPendingTasks(limit: 10);

echo "\nPending Tasks:\n";
foreach ($pending as $task) {
    echo "  #{$task->id}: {$task->identifier} -> {$task->action}\n";
    echo "    Priority: {$task->priority}\n";
    echo "    Created: {$task->created_at->diffForHumans()}\n";
}
```

## Cleanup Operations

### Clean Old Tasks

```php
// Remove completed tasks older than 30 days
$deletedTasks = sTask::cleanOldTasks(days: 30);
echo "Deleted {$deletedTasks} old tasks\n";

// Custom cleanup
use Seiger\sTask\Models\sTaskModel;

// Remove failed tasks older than 7 days
$deleted = sTaskModel::failed()
    ->where('finished_at', '<', now()->subDays(7))
    ->delete();

echo "Deleted {$deleted} old failed tasks\n";
```

### Clean Old Logs

```php
// Remove log files older than 30 days
$deletedLogs = sTask::cleanOldLogs(days: 30);
echo "Deleted {$deletedLogs} old log files\n";

// Clear specific task logs
$task = sTaskModel::find(1);
$task->clearLogs();
echo "Logs cleared for task #{$task->id}\n";
```

## Artisan Commands

### Discover Workers

```console
# Basic discovery
php artisan stask:discover-workers

# Discover with rescan
php artisan stask:discover-workers --rescan

# Discover with cleanup
php artisan stask:discover-workers --clean

# Discover with both options
php artisan stask:discover-workers --rescan --clean
```

**What it does:**
- `--rescan` - Updates metadata for existing workers
- `--clean` - Removes orphaned workers (classes no longer exist)

### Publish Assets

```console
# Publish or update assets
php artisan stask:publish
```

This command republishes all package assets:
- CSS files
- JavaScript files
- Images and icons
- Configuration files

Use this after package updates to get the latest assets.

## Configuration Check

If you're developing your own package that integrates with sTask, check if sTask is installed:

```php
// Check if sTask is installed
if (evo()->getConfig('check_sTask', false)) {
    // sTask is available
    $task = \Seiger\sTask\Facades\sTask::create(
        identifier: 'my_worker',
        action: 'process',
        data: []
    );
} else {
    // sTask not installed, use fallback
    $this->processDirectly();
}
```

You can also check the version:

```php
$version = evo()->getConfig('sTaskVer', 'unknown');
echo "sTask version: {$version}\n";
```

## Storage Structure

sTask creates the following storage structure:

```
storage/
└── stask/
    ├── 1.log           # Task #1 logs
    ├── 2.log           # Task #2 logs
    ├── 3.log           # Task #3 logs
    ├── 1.json          # Task #1 progress snapshot
    ├── 2.json          # Task #2 progress snapshot
    ├── .gc_progress    # Garbage collection marker
    └── ...
```

**Log Files** (`*.log`):
- Contain detailed execution logs
- Include timestamps, levels (info/warning/error), messages
- Automatically cleaned up after configured period

**Progress Files** (`*.json`):
- Real-time progress snapshots
- Used for progress monitoring in admin interface
- Automatically cleaned up after 24 hours

## Database Tables

### s_workers Table

Stores worker configurations:

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `uuid` | UUID | External system integration ID |
| `identifier` | VARCHAR | Unique worker identifier |
| `scope` | VARCHAR | Module/package scope |
| `class` | VARCHAR | Worker class name |
| `active` | BOOLEAN | Active status |
| `position` | INT | Display order |
| `settings` | JSON | Worker settings |
| `hidden` | INT | Visibility flag |

```php
// Query examples
use Seiger\sTask\Models\sWorker;

// Get active workers
$active = sWorker::active()->get();

// Get workers by scope
$commerce = sWorker::byScope('scommerce')->get();

// Get ordered workers
$ordered = sWorker::ordered()->get();
```

### s_tasks Table

Stores task records:

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `identifier` | VARCHAR | Worker identifier |
| `action` | VARCHAR | Action name |
| `status` | INT | Execution status (10-50) |
| `message` | VARCHAR | Status message |
| `started_by` | INT | User ID |
| `meta` | JSON | Task data |
| `result` | TEXT | Result data/file path |
| `start_at` | TIMESTAMP | Scheduled start |
| `finished_at` | TIMESTAMP | Completion time |
| `attempts` | INT | Execution attempts |
| `max_attempts` | INT | Maximum attempts |
| `priority` | VARCHAR | Task priority |
| `progress` | INT | Progress percentage |

```php
// Query examples
use Seiger\sTask\Models\sTaskModel;

// Get pending tasks
$pending = sTaskModel::pending()->get();

// Get running tasks
$running = sTaskModel::running()->get();

// Get completed tasks
$completed = sTaskModel::completed()->get();

// Get failed tasks
$failed = sTaskModel::failed()->get();

// Get tasks by identifier
$productTasks = sTaskModel::byIdentifier('product')->get();

// Get tasks by action
$imports = sTaskModel::byAction('import')->get();

// Get high priority tasks
$urgent = sTaskModel::highPriority()->get();
```

## Next Steps

- Read the [Developer Guide](./developers.md) to create custom workers
- Explore the [Admin Interface Guide](./admin.md) for managing tasks and workers
- Check the [GitHub repository](https://github.com/Seiger/sTask) for updates and examples

## Troubleshooting

### Tasks Not Processing

1. Check if workers are active:
```php
$worker = \Seiger\sTask\Models\sWorker::where('identifier', 'product')->first();
if (!$worker->active) {
    echo "Worker is inactive!\n";
}
```

2. Check task status:
```php
$task = \Seiger\sTask\Models\sTaskModel::find(1);
echo "Status: {$task->status_text}\n";
echo "Message: {$task->message}\n";
```

3. Check logs:
```php
$logs = $task->getErrorLogs();
foreach ($logs as $log) {
    echo $log['message'] . "\n";
}
```

### Workers Not Discovered

1. Run discovery manually:
```console
php artisan stask:discover-workers --clean
```

2. Check if class implements `TaskInterface`:
```php
class MyWorker implements \Seiger\sTask\Contracts\TaskInterface
{
    // ...
}
```

3. Verify your worker is in a valid Composer package and not in system namespaces (Illuminate, Symfony, etc.)

### Permission Issues

If you get permission errors for storage:

```console
chmod -R 755 storage/stask
chown -R www-data:www-data storage/stask
```

Or through PHP:
```php
$path = storage_path('stask');
if (!is_writable($path)) {
    chmod($path, 0755);
}
```

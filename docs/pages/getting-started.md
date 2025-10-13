---
title: Getting started
sidebar_label: Getting started
sidebar_position: 2
---

## Requirements
- Evolution CMS **3.2.0+**
- PHP **8.2+**
- Composer **2.2+**
- One of: **MySQL 8.0+** / **MariaDB 10.5+** / **PostgreSQL 10+** / **SQLite 3.25+**

## Install by artisan package

Go to Your `/core/` folder

```console
cd core
```

```console
composer update
```

Run php artisan commands

```console
php artisan package:installrequire seiger/stask "*"
```

```console
php artisan vendor:publish --provider="Seiger\sTask\sTaskServiceProvider"
```

```console
php artisan migrate
```

```console
php artisan stask:discover-workers
```

> The package automatically publishes assets, creates storage directories, and integrates with Evolution CMS manager interface.

That's it — you can now create and manage asynchronous tasks through the admin interface.

## Where to find the module
Manager → **Tools → Task Manager**. You'll see tabs for Dashboard and Workers.

## Basic Usage

### Creating a Task

```php
use Seiger\sTask\Facades\sTask;

// Create a new task
$task = sTask::create(
    identifier: 'product_sync',
    action: 'import',
    data: ['file' => '/path/to/products.csv'],
    priority: 'high',
    userId: evo()->getLoginUserID()
);
```

### Processing Tasks

```php
// Process pending tasks
$processedCount = sTask::processPendingTasks();

// Get task statistics
$stats = sTask::getStats();
```

### Worker Discovery

```php
// Discover and register new workers
$registered = sTask::discoverWorkers();

// Rescan existing workers
$updated = sTask::rescanWorkers();

// Clean orphaned workers
$deleted = sTask::cleanOrphanedWorkers();
```

## Artisan Commands

```console
# Discover and register workers
php artisan stask:discover-workers

# Discover with options
php artisan stask:discover-workers --rescan --clean

# Publish assets
php artisan stask:publish
```

## Configuration Check

If you write your own code that can integrate with the sTask plugin, you can check the presence of this module in the system through a configuration variable.

```php
if (evo()->getConfig('check_sTask', false)) {
    // Your code
}
```

If the plugin is installed, the result of `evo()->getConfig('check_sTask', false)` will always be `true`. Otherwise, you will get `false`.

## Storage Structure

sTask creates the following storage structure:

```
storage/
├── stask/           # Task log files
│   ├── 1.log        # Logs for task ID 1
│   ├── 2.log        # Logs for task ID 2
│   └── ...
```

## Database Tables

sTask creates the following database tables:

- `s_workers` - Worker configurations
- `s_tasks` - Task records
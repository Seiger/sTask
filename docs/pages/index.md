---
id: intro
title: sTask for Evolution CMS
slug: /
sidebar_position: 1
---

![sTask](https://github.com/user-attachments/assets/1431d4ab-c2ab-4b16-b14d-ceb49227930b)
[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/stask?label=version)](https://packagist.org/packages/seiger/stask)
[![CMS Evolution](https://img.shields.io/badge/CMS-Evolution-brightgreen.svg)](https://github.com/evolution-cms/evolution)
![PHP version](https://img.shields.io/packagist/php-v/seiger/stask)
[![License](https://img.shields.io/packagist/l/seiger/stask)](https://packagist.org/packages/seiger/stask)
[![Issues](https://img.shields.io/github/issues/Seiger/stask)](https://github.com/Seiger/stask/issues)
[![Stars](https://img.shields.io/packagist/stars/Seiger/stask)](https://packagist.org/packages/seiger/stask)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/stask)](https://packagist.org/packages/seiger/stask)

## Welcome to sTask!

**sTask** is a powerful asynchronous task management system designed specifically for Evolution CMS.
It provides a robust framework for creating, executing, and monitoring background tasks with automatic
worker discovery, comprehensive logging, and real-time progress tracking.

Whether you need to process large data imports, generate reports, send bulk emails, synchronize with external
systems, or perform any other time-consuming operations, **sTask** gives you the tools to handle these tasks
efficiently without blocking your main application.

👉 Start with **[Getting Started](./getting-started.md)** or explore **[Developer Guide](./developers.md)**.

## Key Features

### ✅ Asynchronous Task Management
- **Create and execute background tasks** - Run long operations without blocking
- **Task priority system** (low, normal, high) - Control execution order
- **Automatic retry mechanism** - Configurable retry attempts for failed tasks
- **Task progress tracking** (0-100%) - Real-time progress updates
- **Task status monitoring** - Track tasks through their lifecycle
    - `pending` - Waiting for execution
    - `running` - Currently executing
    - `completed` - Successfully finished
    - `failed` - Execution failed
    - `cancelled` - Manually cancelled

### ✅ Worker System
- **Automatic worker discovery** - Scan installed packages for workers
- **Worker registration and management** - Activate/deactivate workers
- **Worker validation** - Ensure workers meet interface requirements
- **Multiple actions per worker** - One worker can handle multiple task types
- **Scope-based organization** - Filter workers by module/package
- **Custom worker implementation** - Extend `BaseWorker` for custom logic
- **Advanced querying** - Built-in scopes for task filtering (`pending`, `running`, `incomplete`, etc.)

### ✅ File-based Logging
- **Comprehensive task execution logs** - Detailed logs for each task
- **Log filtering by level** (info, warning, error) - Find specific log entries
- **Log download and management** - Export logs for analysis
- **Automatic log cleanup** - Remove old logs automatically
- **Progress snapshots** - Real-time progress files in `storage/stask/`

### ✅ Admin Interface
- **Dashboard with task statistics** - Overview of all tasks
- **Worker management panel** - Discover, activate, manage workers
- **Real-time task monitoring** - Watch tasks execute in real-time
- **Task execution controls** - Start, stop, retry tasks
- **Clean and modern UI** - Built with Tailwind CSS

### ✅ Integration
- **Evolution CMS manager integration** - Access from Tools menu
- **Menu integration with custom logo** - Branded menu item
- **Artisan commands** - CLI tools for task management
- **Composer package** - Easy installation and updates
- **Auto-asset publishing** - Automatic resource management

### ✅ Developer-Friendly
- **Simple API** - Easy to use facade and models
- **Well-documented** - Comprehensive documentation
- **PSR-4 autoloading** - Standard PHP structure
- **Laravel integration** - Uses Laravel components
- **Extensible architecture** - Easy to extend and customize

## Architecture Overview

```
┌──────────────────────────────────────────┐
│            sTask Architecture            │
├──────────────────────────────────────────┤
│                                          │
│  ┌──────────────┐      ┌──────────────┐  │
│  │   Workers    │      │    Tasks     │  │
│  ├──────────────┤      ├──────────────┤  │
│  │ Product      │─────>│ Import CSV   │  │
│  │ Email        │─────>│ Send Campaign│  │
│  │ Report       │─────>│ Generate PDF │  │
│  │ Cleanup      │─────>│ Archive Data │  │
│  └──────────────┘      └──────────────┘  │
│         │                      │         │
│         v                      v         │
│   ┌──────────────────────────────────┐   │
│   │       BaseWorker Class           │   │
│   ├──────────────────────────────────┤   │
│   │ - createTask()                   │   │
│   │ - invokeAction()                 │   │
│   │ - pushProgress()                 │   │
│   │ - markFinished()                 │   │
│   │ - markFailed()                   │   │
│   └──────────────────────────────────┘   │
│         │                      │         │
│         v                      v         │
│  ┌──────────────┐      ┌──────────────┐  │
│  │  Progress    │      │    Logs      │  │
│  │  Tracking    │      │   System     │  │
│  ├──────────────┤      ├──────────────┤  │
│  │ storage/     │      │ storage/     │  │
│  │ stask/       │      │ stask/       │  │
│  │ {id}.json    │      │ {id}.log     │  │
│  └──────────────┘      └──────────────┘  │
│                                          │
└──────────────────────────────────────────┘
```

## Quick Example

### Create a Worker

```php
<?php namespace MyPackage\Workers;

use Seiger\sTask\Workers\BaseWorker;
use Seiger\sTask\Models\sTaskModel;

class ProductWorker extends BaseWorker
{
    public function identifier(): string { return 'product'; }
    public function scope(): string { return 'mypackage'; }
    public function icon(): string { return '<i class="fa fa-cube"></i>'; }
    public function title(): string { return 'Product Worker'; }
    public function description(): string { return 'Import and export products'; }
    
    public function renderWidget(): string
    {
        return view('mypackage::widgets.product')->render();
    }
    
    public function settings(): array
    {
        return ['batch_size' => 100];
    }
    
    // Action: Import products
    public function taskImport(sTaskModel $task, array $options = []): void
    {
        $file = $options['file'];
        $products = $this->readCsv($file);
        $total = count($products);
        
        foreach ($products as $i => $product) {
            $this->importProduct($product);
            
            // Update progress
            $this->pushProgress($task, [
                'progress' => (int)(($i + 1) / $total * 100),
                'processed' => $i + 1,
                'total' => $total,
            ]);
        }
        
        $this->markFinished($task, null, "Imported {$total} products");
    }
}
```

### Use the Worker

```php
use Seiger\sTask\Facades\sTask;

// Create task
$task = sTask::create(
    identifier: 'product',
    action: 'import',
    data: ['file' => 'products.csv'],
    priority: 'high'
);

// Process tasks
$processed = sTask::processPendingTasks();

// Check status
if ($task->fresh()->isFinished()) {
    echo "Import completed!\n";
}

// Get all incomplete tasks
$incompleteTasks = \Seiger\sTask\Models\sTaskModel::incomplete()->get();
echo "Found " . count($incompleteTasks) . " incomplete tasks\n";
```

## Use Cases

### E-commerce
- **Product imports/exports** - Bulk product data management
- **Inventory synchronization** - Sync stock with external systems
- **Order processing** - Batch order fulfillment
- **Price updates** - Mass price changes
- **Category reorganization** - Restructure product catalogs

### Content Management
- **Content migration** - Move content between systems
- **Image optimization** - Batch image processing
- **Search index rebuilding** - Update search databases
- **Sitemap generation** - Create XML sitemaps
- **Cache warming** - Pre-generate cached pages

### Marketing
- **Email campaigns** - Bulk email sending
- **Newsletter distribution** - Send to subscriber lists
- **Report generation** - Create analytics reports
- **Data export** - Export user data
- **Analytics processing** - Process tracking data

### System Maintenance
- **Database cleanup** - Remove old records
- **Log archiving** - Archive and compress logs
- **Backup operations** - Create system backups
- **File cleanup** - Remove temporary files
- **Health checks** - System monitoring tasks

## Requirements

- Evolution CMS **3.2.0+**
- PHP **8.2+**
- Composer **2.2+**
- One of: **MySQL 8.0+** / **MariaDB 10.5+** / **PostgreSQL 10+** / **SQLite 3.25+**

## Installation

```console
cd core
composer update
php artisan package:installrequire seiger/stask "*"
php artisan vendor:publish --tag=stask
php artisan migrate
```

Setup cron for task processing:
```cron
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

See **[Getting Started](./getting-started.md)** for detailed installation instructions.

## Performance

sTask is designed for efficiency:

- **File-based progress tracking** - No database overhead during execution
- **Atomic file operations** - Thread-safe progress updates
- **Automatic garbage collection** - Cleanup old progress files
- **Batch processing** - Process multiple tasks efficiently
- **Priority queue** - Execute high-priority tasks first
- **Memory management** - Suitable for large datasets

### Benchmarks

Typical performance on standard hardware:

| Operation | Speed |
|-----------|-------|
| Task creation | ~5ms |
| Progress update | ~2ms |
| Log write | ~3ms |
| Task completion | ~8ms |
| Worker discovery | ~100ms |

Processing 10,000 items:
- Small items (< 1KB): **~30 seconds**
- Medium items (< 100KB): **~2 minutes**
- Large items (< 1MB): **~5 minutes**

*Performance varies based on task complexity and system resources.*

## Future Features

- [ ] **Task scheduling** - Cron-like task scheduling
- [ ] **Task dependencies** - Chain tasks together
- [ ] **Email notifications** - Get notified on completion
- [ ] **Performance metrics** - Task analytics and insights
- [ ] **Webhook support** - External system integration
- [ ] **Task templates** - Reusable task configurations
- [ ] **Multi-server support** - Distribute tasks across servers
- [ ] **Advanced queue algorithms** - Better prioritization
- [ ] **Task groups** - Group related tasks
- [ ] **Conditional execution** - Run tasks based on conditions

## Community & Support

- **Documentation**: [https://seiger.github.io/sTask](https://seiger.github.io/sTask)
- **Issues**: [GitHub Issues](https://github.com/Seiger/sTask/issues)
- **Discussions**: [GitHub Discussions](https://github.com/Seiger/sTask/discussions)
- **Author**: [Seiger](https://github.com/Seiger)
- **License**: [MIT](https://github.com/Seiger/sTask/blob/main/LICENSE)

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Write tests if applicable
5. Submit a pull request

## License

sTask is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Credits

Developed and maintained by [Seiger](https://github.com/Seiger).

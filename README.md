# sTask for Evolution CMS

[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/stask?label=version)](https://packagist.org/packages/seiger/stask)
[![CMS Evolution](https://img.shields.io/badge/CMS-Evolution-brightgreen.svg)](https://github.com/evolution-cms/evolution)
![PHP version](https://img.shields.io/packagist/php-v/seiger/stask)
[![License](https://img.shields.io/packagist/l/seiger/stask)](https://packagist.org/packages/seiger/stask)
[![Issues](https://img.shields.io/github/issues/Seiger/stask)](https://github.com/Seiger/stask/issues)
[![Stars](https://img.shields.io/packagist/stars/Seiger/stask)](https://packagist.org/packages/seiger/stask)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/stask)](https://packagist.org/packages/seiger/stask)

## Welcome to sTask!

**sTask** is a powerful asynchronous task management system designed specifically for Evolution CMS. It provides a robust framework for creating, executing, and monitoring background tasks with automatic worker discovery and comprehensive logging capabilities.

Whether you need to process large data imports, generate reports, send emails in bulk, or perform any other time-consuming operations, **sTask** gives you the tools to handle these tasks efficiently without blocking your main application.

## Features

### ✅ Asynchronous Task Management
- Create and execute background tasks
- Task priority system (low, normal, high)
- Automatic retry mechanism with configurable attempts
- Task progress tracking (0-100%)
- Task status monitoring (pending, running, completed, failed, cancelled)

### ✅ Worker System
- Automatic worker discovery from installed packages
- Worker registration and activation/deactivation
- Worker validation and error handling
- Custom worker implementation interface

### ✅ File-based Logging
- Comprehensive task execution logs
- Log filtering by level (info, warning, error)
- Log download and management
- Automatic log cleanup

### ✅ Admin Interface
- Dashboard with task statistics
- Worker management panel
- Real-time task monitoring
- Task execution controls

### ✅ Integration
- Evolution CMS manager integration
- Menu integration with custom logo
- Artisan commands for task management
- Composer package with auto-assets publishing

## Requirements

- Evolution CMS **3.2.0+**
- PHP **8.2+**
- Composer **2.2+**
- One of: **MySQL 8.0+** / **MariaDB 10.5+** / **PostgreSQL 10+** / **SQLite 3.25+**

## Installation

Go to Your `/core/` folder:

```bash
cd core
```

```bash
composer update
```

Run php artisan commands:

```bash
php artisan package:installrequire seiger/stask "*"
```

```bash
php artisan vendor:publish --provider="Seiger\sTask\sTaskServiceProvider"
```

```bash
php artisan migrate
```

```bash
php artisan stask:discover-workers
```

## Quick Start

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

### Creating Custom Workers

```php
<?php namespace YourNamespace\Workers;

use Seiger\sTask\Contracts\TaskInterface;

class EmailWorker implements TaskInterface
{
    public function execute(array $data): bool
    {
        // Your task logic here
        return true;
    }
    
    public static function getType(): string
    {
        return 'email_campaign';
    }
    
    public static function getDescription(): string
    {
        return 'Send bulk email campaigns';
    }
}
```

## Admin Interface

Access sTask through **Manager → Tools → Task Manager**.

### Dashboard
- Task statistics (pending, running, completed, failed)
- Recent activity with progress tracking
- Quick actions for processing and cleanup

### Workers
- Automatic worker discovery
- Worker activation/deactivation
- Worker configuration management

## Artisan Commands

```bash
# Discover and register workers
php artisan stask:discover-workers

# Discover with options
php artisan stask:discover-workers --rescan --clean

# Publish assets
php artisan stask:publish
```

## Configuration

Check if sTask is installed:

```php
if (evo()->getConfig('check_sTask', false)) {
    // sTask is available
}
```

## Database Tables

sTask creates the following tables:
- `s_workers` - Worker configurations
- `s_tasks` - Task records

## Storage Structure

```
storage/
├── stask/           # Task log files
│   ├── 1.log        # Logs for task ID 1
│   ├── 2.log        # Logs for task ID 2
│   └── ...
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

## Future Features

- [ ] Task scheduling with cron integration
- [ ] Task dependencies and workflow management
- [ ] Email notifications for task completion
- [ ] Task performance metrics and analytics
- [ ] Webhook support for external integrations
- [ ] Task templates and presets
- [ ] Multi-server task distribution
- [ ] Task queue prioritization algorithms

## Documentation

📖 **[Full Documentation](https://seiger.github.io/sTask/)**

- [Getting Started](https://seiger.github.io/sTask/getting-started)
- [Admin Interface](https://seiger.github.io/sTask/admin)
- [Developer Guide](https://seiger.github.io/sTask/developers)

## Support

If you need help, please don't hesitate to **[open an issue](https://github.com/Seiger/sTask/issues)**.

## License

This project is licensed under the GPL-3.0 License - see the [LICENSE](LICENSE) file for details.

## Author

**Seiger IT Team**
- Website: [https://seigerit.com](https://seigerit.com)
- GitHub: [@Seiger](https://github.com/Seiger)

---

Made with ❤️ for Evolution CMS
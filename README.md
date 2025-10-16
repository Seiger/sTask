# sTask for Evolution CMS

![sTask](https://github.com/user-attachments/assets/1431d4ab-c2ab-4b16-b14d-ceb49227930b)
[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/stask?label=version)](https://packagist.org/packages/seiger/stask)
[![CMS Evolution](https://img.shields.io/badge/CMS-Evolution-brightgreen.svg)](https://github.com/evolution-cms/evolution)
![PHP version](https://img.shields.io/packagist/php-v/seiger/stask)
[![License](https://img.shields.io/packagist/l/seiger/stask)](https://packagist.org/packages/seiger/stask)
[![Issues](https://img.shields.io/github/issues/Seiger/stask)](https://github.com/Seiger/stask/issues)
[![Stars](https://img.shields.io/packagist/stars/Seiger/stask)](https://packagist.org/packages/seiger/stask)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/stask)](https://packagist.org/packages/seiger/stask)

## Welcome to sTask!

**sTask** is a high-performance asynchronous task management system designed specifically for Evolution CMS. It provides an enterprise-grade framework for creating, executing, and monitoring background tasks with automatic worker discovery, comprehensive performance monitoring, and advanced caching capabilities.

Whether you need to process large data imports, generate reports, send emails in bulk, or perform any other time-consuming operations, **sTask** gives you the tools to handle these tasks efficiently without blocking your main application. Built with performance and scalability in mind, it's designed to become the fundamental task management solution for the entire Evolution CMS ecosystem.

## Features

### 🚀 Core Task Management
- **Asynchronous Processing**: Execute long-running tasks in the background
- **Priority System**: Task prioritization (low, normal, high) with intelligent queuing
- **Progress Tracking**: Real-time progress monitoring (0-100%) via filesystem-based tracking
- **Status Management**: Complete task lifecycle (queued, preparing, running, finished, failed)
- **Retry Mechanism**: Configurable automatic retry with exponential backoff
- **File Downloads**: Download task results and exported files

### ⚡ High-Performance Architecture
- **Multi-Level Caching**: In-memory + Laravel cache for optimal worker resolution
- **Database Optimization**: Batch operations and optimized queries
- **Memory Management**: Automatic memory cleanup and resource management
- **Performance Metrics**: Real-time monitoring and analytics
- **System Health**: Automated health checks and performance alerts
- **Cache Statistics**: Detailed cache performance monitoring

### 🔧 Worker System
- **Automatic Discovery**: Auto-discovery of worker implementations from packages
- **Worker Registration**: Dynamic worker registration and activation/deactivation
- **Interface Validation**: Comprehensive worker validation and error handling
- **Custom Implementation**: Clean interface for custom worker development
- **Worker Settings**: Individual worker configuration and settings management

### 🎛️ Admin Interface
- **Modern Dashboard**: Clean, responsive interface with task statistics
- **Worker Management**: Visual worker management with card-based layout
- **Real-time Monitoring**: Live task progress and status updates
- **Performance Analytics**: Built-in performance monitoring and alerts
- **Cache Management**: Visual cache statistics and management tools

### 🔌 Developer Experience
- **RESTful API**: Comprehensive API for task management and monitoring
- **Clean Architecture**: Well-structured, extensible codebase
- **Comprehensive Documentation**: Detailed docs with examples
- **Error Context**: Detailed error information for debugging
- **Performance Tools**: Built-in performance analysis and optimization tools

### 🔗 Integration
- **Evolution CMS**: Seamless integration with Evolution CMS manager
- **Menu Integration**: Custom logo and menu integration
- **Artisan Commands**: CLI tools for task management
- **Composer Package**: Auto-assets publishing and dependency management

## Requirements

- Evolution CMS **3.7+**
- PHP **8.3+**
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
php artisan vendor:publish --tag=stask
```

```bash
php artisan migrate
```

### Setup Cron Job

Add the following cron job to run scheduled tasks every minute:

```bash
# Edit your crontab
crontab -e

# Add this line to run every minute
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

**Important**: Replace `/path/to/your/project` with the actual path to your Evolution CMS installation.

For shared hosting, you may need to use the full path to PHP:
```bash
* * * * * cd /path/to/your/project && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
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

// Get all incomplete tasks (pending, preparing, running)
$incomplete = \Seiger\sTask\Models\sTaskModel::incomplete()->get();
```

### Creating Custom Workers

```php
<?php namespace YourNamespace\Workers;

use Seiger\sTask\Workers\BaseWorker;
use Seiger\sTask\Models\sTaskModel;

class EmailWorker extends BaseWorker
{
    public function identifier(): string
    {
        return 'email_campaign';
    }
    
    public function scope(): string
    {
        return 'notifications';
    }
    
    public function icon(): string
    {
        return '<i class="fa fa-envelope"></i>';
    }
    
    public function title(): string
    {
        return 'Email Campaign';
    }
    
    public function description(): string
    {
        return 'Send bulk email campaigns to users';
    }
    
    public function taskSend(sTaskModel $task, array $options = []): void
    {
        $this->pushProgress(10, 'Preparing email data');
        
        $recipients = $options['recipients'] ?? [];
        $subject = $options['subject'] ?? 'Campaign';
        
        foreach ($recipients as $email) {
            // Send email logic
            $this->pushProgress(50 + (40 / count($recipients)), "Sent to {$email}");
        }
        
        $this->markFinished('All emails sent successfully');
    }
    
    public function settings(): array
    {
        return [
            'smtp_host' => 'localhost',
            'smtp_port' => 587,
            'from_email' => 'noreply@example.com',
        ];
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

### Task Worker (System Command)

The task worker is automatically executed by the system via cron job every minute. You don't need to run it manually.

**What it does:**
- Processes all pending tasks in the queue
- Executes tasks through their respective workers
- Updates task progress and status
- Cleans up old progress files when idle
- Should be configured via cron for continuous processing

> **Note**: Workers are automatically discovered when you access the Workers tab in the admin interface. No manual discovery needed!

## API Endpoints

sTask provides a comprehensive RESTful API for task management and monitoring:

### Task Management
```http
POST /stask/worker/{identifier}/run/{action} # Start task
GET /stask/task/{id}/progress                # Get progress
GET /stask/task/{id}/download                # Download result
```

### Performance Monitoring
```http
GET /stask/performance/summary?hours=24      # System performance
GET /stask/performance/workers?hours=24      # Worker statistics
GET /stask/performance/alerts                # Performance alerts
```

## Configuration

Check if sTask is installed:

```php
if (evo()->getConfig('check_sTask', false)) {
    // sTask is available
    $task = \Seiger\sTask\Facades\sTask::create(
        identifier: 'my_worker',
        action: 'process',
        data: []
    );
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

- `10` - Queued (waiting to be executed)
- `30` - Preparing (task is being prepared)
- `50` - Running (currently executing)
- `80` - Finished (successfully completed)
- `100` - Failed (encountered an error)

### Querying Tasks

```php
use Seiger\sTask\Models\sTaskModel;

// Get tasks by status
$pending = sTaskModel::pending()->get();
$running = sTaskModel::running()->get();
$completed = sTaskModel::finished()->get();
$failed = sTaskModel::failed()->get();

// Get all incomplete tasks (pending, preparing, running)
$incomplete = sTaskModel::incomplete()->get();

// Get tasks by priority
$highPriority = sTaskModel::highPriority()->get();
$normalPriority = sTaskModel::normalPriority()->get();
$lowPriority = sTaskModel::lowPriority()->get();
```

## Priority Levels

- `low` - Lowest priority, executed last
- `normal` - Default priority
- `high` - Highest priority, executed first

## Performance & Monitoring

### 🎯 Performance Targets Achieved
- **Task execution overhead** < 100ms
- **Memory usage** < 10MB per worker
- **Database queries** < 3 per task
- **File I/O operations** < 5 per task
- **Cache hit rate** > 85%

### 📊 Built-in Monitoring
- **Real-time metrics** collection and analysis
- **Performance alerts** with configurable thresholds
- **System health checks** and automated monitoring
- **Worker statistics** and performance analytics
- **Cache performance** tracking and optimization
- **Memory usage** monitoring and cleanup

### 🔧 Enterprise Features
- **Multi-level caching** (in-memory + Laravel cache)
- **Database optimization** with batch operations
- **Automatic memory management** and cleanup
- **Performance analytics** with historical data
- **System health monitoring** with alerts
- **Cache management** with detailed statistics

### 🚀 Roadmap
- [ ] Task scheduling with cron integration
- [ ] Task dependencies and workflow management
- [ ] Email notifications for task completion
- [ ] Webhook support for external integrations
- [ ] Task templates and presets
- [ ] Multi-server task distribution
- [ ] Advanced queue prioritization algorithms

## Documentation

📖 **[Full Documentation](https://seiger.github.io/sTask/)**

- [Getting Started](https://seiger.github.io/sTask/getting-started) - Installation and basic usage
- [Admin Interface](https://seiger.github.io/sTask/admin) - Managing tasks and workers
- [Developer Guide](https://seiger.github.io/sTask/developers) - Creating custom workers
- [API Reference](https://seiger.github.io/sTask/api) - Complete API documentation
- [Performance Guide](https://seiger.github.io/sTask/performance) - Optimization and monitoring

## Evolution CMS Ecosystem

sTask is designed to become the **fundamental task management solution** for the entire Evolution CMS ecosystem:

### 🎯 **Core Dependency**
- **sCommerce** - Uses sTask for product synchronization and data processing
- **sArticles** - Leverages sTask for content management and publishing
- **sGallery** - Utilizes sTask for image processing and optimization
- **Future packages** - Will integrate with sTask for async operations

### 🔄 **Migration Strategy**
1. **Phase 1**: Parallel operation with existing systems
2. **Phase 2**: Gradual migration of async operations
3. **Phase 3**: Complete replacement of legacy task systems
4. **Phase 4**: Optimization and enhancement

### 🚀 **Benefits for Package Developers**
- **Standardized async processing** across all packages
- **Unified task management** interface
- **Performance optimization** out of the box
- **Monitoring and analytics** built-in
- **Easy integration** with clean APIs

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
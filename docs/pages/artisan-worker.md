---
title: Artisan Worker
sidebar_label: Artisan Worker
sidebar_position: 4
---

# Artisan Worker

Execute Laravel Artisan commands through the sTask interface with real-time progress tracking and interactive UI.

## Features

- ✅ Execute any Artisan command from manager
- ✅ Real-time output with syntax highlighting
- ✅ Interactive command list - click to run
- ✅ Security validation and restrictions
- ✅ Audit logging
- ✅ Production-safe defaults

## Usage

### Basic

1. Navigate to **sTask** → **Dashboard**
2. Find **Artisan** widget
3. Enter command (e.g., `cache:clear`) or leave empty for list
4. Optionally add arguments
5. Click **Run Task**

### Interactive Mode

Execute without command to see all available commands. Click any command to run it immediately.

## Security

Configuration in `config/artisan_security.php`:

```php
return [
    // Forbidden in production
    'dangerous_commands' => [
        'migrate:fresh',
        'migrate:reset',
        'db:wipe',
    ],
    
    // Require confirmation
    'confirmation_required' => [
        'migrate',
        'db:seed',
    ],
    
    // Whitelist (if set, only these allowed)
    'whitelist' => [],
    
    // Blacklist (always forbidden)
    'blacklist' => [],
    
    // Enable security
    'enabled' => true,
    
    // Log executions
    'log_executions' => true,
];
```

### Security Layers

1. **Dangerous Commands** - Blocked in production
2. **Confirmation Required** - Log warnings
3. **Whitelist/Blacklist** - Flexible filtering with wildcards
4. **Injection Prevention** - Blocks special characters
5. **Audit Logging** - All executions logged

### Examples

**Whitelist only cache commands:**
```php
'whitelist' => [
    'cache:*',
    'view:clear',
],
```

**Block specific command:**
```php
'blacklist' => [
    'key:generate',
    'down',
],
```

## UI

### Console-Style Interface

- VS Code Dark+ theme
- Monospace font
- Color-coded output:
  - 🔵 Blue: Info
  - 🟢 Green: Success & commands
  - 🟡 Yellow: Warnings
  - 🔴 Red: Errors
  - ⚪ Gray: Descriptions

### Interactive Commands

- Hover to highlight
- Click to execute
- Instant feedback

## Configuration

Copy config to customize:

```bash
cp packages/sTask/config/artisan_security.php core/custom/config/artisan_security.php
```

Edit `core/custom/config/artisan_security.php` with your settings.

## API Usage

```php
use Seiger\sTask\Workers\ArtisanWorker;

$worker = new ArtisanWorker();
$task = $worker->createTask('run', [
    'command' => 'cache:clear',
    'arguments' => '--tags=views',
]);
```

## Best Practices

1. Use whitelist in production
2. Review logs regularly
3. Require confirmation for destructive operations
4. Test in staging first
5. Backup before migrations


# sTask Installation Guide

## Package Structure

```
sTask/
├── src/
│   ├── Database/
│   │   ├── Migrations/          # Database migrations
│   │   │   └── 2025_10_15_000000_create_task_tables.php
│   │   └── Seeders/             # Database seeders
│   │       └── STaskPermissionsSeeder.php
│   ├── Console/
│   │   ├── TaskWorker.php       # Task processing command
│   │   ├── PublishAssets.php    # Asset publishing command
│   │   └── SeedPermissions.php  # Manual seeding command
│   ├── sTaskServiceProvider.php # Service provider
│   └── ...other files
├── composer.json                # Package configuration
└── README.md
```

## Automatic Installation (Docker)

When installing through Docker with `EVO_AUTO_INSTALL=true`, the following happens automatically:

1. **Composer Install**
   ```bash
   composer require seiger/stask "*"
   ```

2. **Service Provider Registration**
   - ServiceProvider is auto-registered via `composer.json` extra.laravel.providers
   - Migrations are loaded via `loadMigrationsFrom(__DIR__ . '/Database/Migrations')`

3. **Asset Publishing**
   ```bash
   php artisan vendor:publish --all --force
   ```
   This publishes:
   - Configuration files to `core/config/app/aliases/`
   - Public assets (CSS, JS, images) to `public/assets/site/`
   - Optional: migrations and seeders for manual installation

4. **Database Migrations**
   ```bash
   php artisan migrate --force
   ```
   This creates:
   - `s_workers` table - Worker configurations
   - `s_tasks` table - Task records and execution history

5. **Automatic Seeding**
   - Seeders run automatically after migrations
   - Uses `afterMigrating()` callback in ServiceProvider
   - Creates `stask` permission group and permissions

## Manual Installation

### Standard Installation

```bash
cd core
composer require seiger/stask "*"
php artisan vendor:publish --tag=stask
php artisan migrate
```

**Permissions are seeded automatically!**

### Manual Seeding (if needed)

If automatic seeding fails, run:
```bash
php artisan stask:seed-permissions
```

### Publishing Migrations/Seeders Separately

For manual control over migrations and seeders:

```bash
# Publish migrations to database/migrations
php artisan vendor:publish --tag=stask-migrations

# Publish seeders to database/seeders
php artisan vendor:publish --tag=stask-seeders

# Then run manually
php artisan migrate
php artisan db:seed --class=STaskPermissionsSeeder
```

## How It Works

### ServiceProvider Boot Process

1. **Load Migrations**
   ```php
   $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
   ```

2. **Register Seeder Callback**
   ```php
   $migrator->afterMigrating(function () {
       // Runs after migrations complete
       $seeder = new STaskPermissionsSeeder();
       $seeder->run();
   });
   ```

3. **Publish Resources**
   - Assets tagged with 'stask'
   - Optional migrations/seeders for manual installation

### PSR-4 Autoloading

```json
{
  "autoload": {
    "psr-4": {
      "Seiger\\sTask\\": "src/"
    }
  }
}
```

All classes under `src/` are auto-loaded, including:
- `Seiger\sTask\Database\Migrations\*`
- `Seiger\sTask\Database\Seeders\*`
- `Seiger\sTask\Console\*`

## Troubleshooting

### Migrations not running?

Check if ServiceProvider is registered:
```bash
php artisan list
# Should show: stask:worker, stask:publish, stask:seed-permissions
```

### Seeders not running?

Run manually:
```bash
php artisan stask:seed-permissions
```

Check logs:
```bash
tail -f core/storage/logs/laravel.log
# Look for: "sTask permissions seeder executed successfully"
```

### Tables not created?

Verify database connection:
```bash
php artisan migrate:status
```

Run migrations manually:
```bash
php artisan migrate --path=vendor/seiger/stask/src/Database/Migrations
```

## Docker Environment Variables

When using Docker, these variables affect installation:

- `EVO_AUTO_INSTALL=true` - Enable automatic installation
- `EVO_EXTRAS=seiger/stask` - Auto-install sTask package
- `DB_CONNECTION=mysql` - Database type (mysql, pgsql)
- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` - Database credentials

Example `.env`:
```env
EVO_AUTO_INSTALL=true
EVO_EXTRAS=seiger/stask:*,evolution-cms-extras/tinymce5:*
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=evolution
DB_USERNAME=evolution
DB_PASSWORD=evolution
```

## Commands Reference

| Command | Description |
|---------|-------------|
| `php artisan stask:worker` | Process pending tasks (scheduled) |
| `php artisan stask:publish` | Publish package assets |
| `php artisan stask:seed-permissions` | Seed permissions manually |
| `php artisan vendor:publish --tag=stask` | Publish all assets |
| `php artisan vendor:publish --tag=stask-migrations` | Publish migrations only |
| `php artisan vendor:publish --tag=stask-seeders` | Publish seeders only |

## Support

For issues related to installation, check:
- [GitHub Issues](https://github.com/Seiger/stask/issues)
- [Documentation](https://seiger.github.io/stask/)


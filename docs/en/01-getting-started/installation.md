# Requirements, Installation and Updates

## Requirements

The current 2.x branch `composer.json` requires:

| Component | Requirement |
| --- |----------------------------|
| PHP | <code>&#94;8.4</code> |
| Evolution CMS | <code>&#94;3.5.7</code> |
| evo-ui | <code>&#94;1.0.6</code> |
| Composer | available in the `core` directory |

The HTML entity for caret is used intentionally: dDocs does not convert it to superscript and shows the exact Composer constraint.

Automatic queue processing requires a system cron or other scheduler that runs Laravel scheduler every minute. To run the task immediately from the UI, the PHP process must also have access to `exec()` or `shell_exec()`; if they are not allowed, the entry in the queue will still be created and processed by the next cron pass.

## Installation via Composer

Commands are executed from the `core` Evolution CMS directory:

```bash
cd /var/www/example/core
composer require seiger/stask:"2.x-dev"
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

What should happen:

1. Laravel package discovery connects `Seiger\sTask\sTaskServiceProvider` and alias `sTask`.
2. `migrate` triggers package migrations that the provider added via `loadMigrationsFrom()`.
3. `package:discover` updates the Laravel package discovery manifest; Composer classmap is built during install/update or `composer dump-autoload`.
4. `stask:publish` copies CSS, JavaScript, and SVG to `assets/site`.
5. A complete cache cleanup removes the old Manager Views, Routes, and Package metadata.

Do not edit files in `core/vendor/seiger/stask`: Composer will replace them during the update.

## Service provider

Provider automatically:

- registers singleton `Seiger\sTask\sTask` and alias `sTask`;
- registers `WorkerService`, `MetricsService`, `SupervisorService`;
- loads migrations, translations, Blade views, manager routes, and Livewire components;
- connects table presets `stask.tasks`, `stask.workers`, `stask.logs`;
- creates `storage/stask` if there is no directory yet;
- registers `stask:worker` and `stask:publish` in the CLI;
- adds `stask:worker` to the Laravel scheduler with a frequency of once per minute.

The Manager menu adds the package plugin `plugins/sTaskPlugin.php` to the `evolution.OnManagerMenuPrerender` event only when the user has permission `stask`. It links to the named route `sTask.index` and uses `sTaskServiceProvider::MODULE_ICON`. The `module/sTaskModule.php` file is a guarded wrapper for the Evolution module entry and renders the same controller. The protected method `registerManagerModule()` is present in the provider for installer/module flow, but does not call `boot()` it directly; Don't rely on manually calling this method.

## Migrations and permission

The package creates three tables: `s_workers`, `s_tasks`, `s_supervisor_states`. A separate idempotent migration creates a permission group `sTask`, permission key `stask` and adds its role `1` if the corresponding system tables exist.

Check:

```bash
php artisan migrate:status
php artisan route:list --path=stask
```

In the manager, the user needs permission `stask`. Manager routes are additionally protected by the middleware group `mgr`; it is not a public HTTP API.

## Post assets

Command:

```bash
php artisan stask:publish
```

publishes in particular:

- `assets/site/stask.svg`;
- `assets/site/stask.min.css`;
- `assets/site/stask-module.css`;
- `assets/site/stask-module.js`;
- `assets/site/stask.js`;
- `assets/site/seigerit.tooltip.js`.

If the module opens without styles or live progress does not update, first repeat publish and `cache:clear-full`, then check HTTP 200 for these assets.

## Cron

Recommended production recording:

```cron
* * * * * cd /var/www/example/core && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Check the absolute paths:

```bash
command -v php
cd /var/www/example/core && php artisan schedule:list
cd /var/www/example/core && php artisan stask:worker
```

Do not run multiple uncontrolled cron entries for the same installation. Regular tasks do not have a global claim-lock, so parallel `stask:worker` can create a risk of competitive execution.

## Update 2.x

```bash
cd /var/www/example/core
composer update seiger/stask --with-dependencies
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

After updating, check the actual source reference:

```bash
composer show seiger/stask --all
```

If the package documentation doesn't appear in dDocs, check not only for `docs` in the Git repository, but for the physical directory `core/vendor/seiger/stask/docs/uk`. Composer lock/dist can stay on the old commit.

## Rollback

Before upgrading, make a backup copy of the database and `core/composer.lock`. Roll back the code with Composer to a verified reference. Roll back the database schema only according to a separate plan after checking the actual set of migrations in the installed version of the package and the consequences for production data.

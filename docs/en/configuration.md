# Configuration

Use this page when installing sTask or checking why the manager module, table
presets, commands, or worker discovery do not behave as expected.

## Install And Publish

Run the package commands from the Evolution CMS `core` directory.

```console
php artisan package:installrequire seiger/stask "*"
php artisan vendor:publish --tag=stask
php artisan vendor:publish --tag=evo-ui --force
php artisan migrate
```

After permission migrations, log out and log back in. Evolution manager
permissions can stay cached until the next session.

## Manager Registration

`Seiger\sTask\sTaskServiceProvider` merges `config/sTaskCheck.php` into
`cms.settings`. The important values are the localized module title, module
icon, module order, and package alias.

If the module is installed but hidden, check `cms.settings`, the Evolution
module record, and manager permissions before changing runtime code.

## Table Presets

sTask owns three EvoUI presets:

| Config key | File | Surface |
| --- | --- | --- |
| `stask.tasks.table` | `config/tasks/table.php` | Tasks table/list. |
| `stask.workers.table` | `config/workers/table.php` | Workers table/list. |
| `stask.logs.table` | `config/logs/table.php` | Logs and details. |

Filters should stay standard EvoUI multi-select filters where the data allows
multiple values. Do not copy single-select article filters into sTask task
tables; articles use a different type model.

## Worker Discovery

`WorkerDiscovery` scans worker classes and respects `excluded_namespaces`.
Each custom worker should expose a stable identifier, scope, title,
description, settings, and `handles` action map.

## Command Security

`config/artisan_security.php` controls allowed, forbidden, dangerous, and
confirmation-required commands for `ArtisanWorker`. The manager UI must route
Artisan execution through this config instead of adding one-off buttons.

## Commands

```console
php artisan stask:worker
php artisan stask:publish
php artisan stask:publish --no-prune
```

Use `stask:publish --no-prune` when debugging published files and you need to
avoid deleting local inspection artifacts.


# Reference

This page lists stable package signals that integrations, dDocs, and migration
agents should use when checking an sTask installation.

## Service Provider

`Seiger\sTask\sTaskServiceProvider` registers the Evolution manager module,
routes, migrations, views, translations, Livewire component, console command,
and EvoUI table presets.

| Signal | Purpose |
| --- | --- |
| `cms.settings` | Merged package manager settings from `config/sTaskCheck.php`. |
| `stask.tasks.table` | EvoUI tasks table preset from `config/tasks/table.php`. |
| `stask.workers.table` | EvoUI workers table preset from `config/workers/table.php`. |
| `stask.logs.table` | EvoUI logs table preset from `config/logs/table.php`. |
| `tasks.table` | Published task table configuration file name. |
| `workers.table` | Published worker table configuration file name. |
| `logs.table` | Published log table configuration file name. |
| `sTaskAlias` | Manager plugin bridge alias used by the package. |
| `sTaskCheck` | Manager settings/config source for module registration. |

## Runtime Classes

| Class | Responsibility |
| --- | --- |
| `DashboardData` | Builds dashboard cards, recent tasks, and recent failed task rows. |
| `MetricsService` | Provides runtime metrics and statistics data. |
| `PublishAssets` | Publishes package manager assets and keeps EvoUI-facing files current. |
| `ModulePanel` | Livewire EvoUI manager panel. |
| `TasksTableData` | Task table/list data provider. |
| `WorkersTableData` | Worker table/list data provider. |
| `LogsTableData` | Log and task-detail data provider. |
| `TaskProgress` | Normalizes task progress values and messages. |
| `WorkerDiscovery` | Discovers worker classes and applies namespace exclusions. |
| `WorkerService` | Creates and controls worker task execution. |
| `BaseWorker` | Base class for custom workers. |
| `ArtisanWorker` | Built-in worker for allowed Artisan commands. |
| `ComposerUpdateWorker` | Built-in worker for package maintenance through Composer. |
| `TaskWorker` | Console worker that processes queued tasks. |

## Exceptions

| Exception | Meaning |
| --- | --- |
| `WorkerNotFoundException` | The requested worker identifier does not exist. |
| `WorkerClassNotFoundException` | The configured worker class cannot be autoloaded. |
| `WorkerInvalidInterfaceException` | The class does not implement the required worker contract. |

## Worker Contract

Workers must declare `identifier()`, `scope()`, `title()`, `description()`, and
`settings()`. The `handles` map describes available task actions. Discovery can
skip namespaces through `excluded_namespaces`.

## Console Commands

```console
php artisan stask:worker
php artisan stask:publish
php artisan stask:publish --no-prune
```

The full publish signature is
`stask:publish {--no-prune : Do not delete existing files before publish}`.

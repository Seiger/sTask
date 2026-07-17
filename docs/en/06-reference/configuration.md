# Configuration

## `config/sTaskCheck.php`

Merges into `cms.settings`:

| Key | Default | Meaning |
| --- | --- | --- |
| `check_sTask` | `true` | package presence/check flag |
| `sTaskVer` | `2.9999999.9999999.9999999-dev` | development branch version marker |

This is not worker runtime tuning.

## Table presets

| File | Config key | Surface |
| --- | --- | --- |
| `config/tasks/table.php` | `stask.tasks.table` | Tasks |
| `config/workers/table.php` | `stask.workers.table` | Workers |
| `config/logs/table.php` | `stask.logs.table` | Logs |

Presets define provider, wire methods, pagination, views, filters, columns, modal, and actions. For project override, use the Evolution custom config mechanism or publish/extension point if it is supported by your version; Do not edit vendor files.

## `config/excluded_namespaces.php`

List of namespace prefixes that `WorkerDiscovery` does not consider. By default, framework/vendor spaces like `Illuminate\`, `Symfony\`, `EvolutionCMS\Legacy\`, `PHPUnit\` etc. are excluded.

If your worker happens to be under the excluded prefix, move it to package/project namespace. Don't shorten the list without analysis: discovery can start instantiating thousands of third-party classes.

## `config/artisan_security.php`

Used `ArtisanWorker`:

| Key | Default |
| --- | --- |
| `dangerous_commands` | `migrate:fresh`, `migrate:reset`, `db:wipe` |
| `confirmation_required` | `migrate`, `migrate:refresh`, `migrate:rollback`, `db:seed`, `cache:clear-full` |
| `whitelist` | empty: all except blocked |
| `blacklist` | empty |
| `enabled` | `true` |
| `log_executions` | `true` |
| `required_permission` | `run_artisan` |

Whitelisted patterns support `*` for contract comments. In production, leave security enabled and form an explicit whitelist for operational needs.

## Worker settings

Stored in `s_workers.settings` JSON:

```json
{
  "schedule": {
    "enabled": true,
    "type": "periodic",
    "datetime": "",
    "frequency": "hourly",
    "time": "*:10",
    "start_time": "",
    "end_time": ""
  },
  "http": {
    "timeout": 15
  }
}
```

`BaseWorker` API:

```php
$worker->settings();
$worker->getConfig('http.timeout', 10);
$worker->setConfig('http.timeout', 20);
$worker->updateConfig(['endpoint' => 'https://example.test']);
$worker->getSchedule();
$worker->shouldRunNow();
```

`TaskWorker` independently calculates the next run; `shouldRunNow()` is a worker side helper and is not the main scheduler decision in the CLI.

## Service container

Singletons:

```php
app(Seiger\sTask\sTask::class);
app(Seiger\sTask\Services\WorkerService::class);
app(Seiger\sTask\Services\MetricsService::class);
app(Seiger\sTask\Services\SupervisorService::class);
```

Facade accessor: `sTask`.

## Storage

| Path | Data |
| --- | --- |
| `core/storage/stask/{id}.log` | append-only progress |
| `core/storage/stask/uploads` | upload/result files controller/worker |
| Laravel cache | worker instances, metrics, supervisor locks |

Provider creates only root `storage/stask`. Subdirectories are created by the corresponding code path.

## Package metadata for dDocs

dDocs reads:

- Composer name `seiger/stask`;
- localized `lang/{locale}/global.php` keys `module_title`, `module_description`, `module_icon`;
- `docs/{locale}`.

Expected metadata:

```php
'module_title' => 'sTask',
'module_icon' => 'tabler-progress-check',
```

`docs/docs.json` is a portable inventory manifest. The current dDocs runtime may not use manifest directly; discoverability provides a Composer package scan and a physical localized docs tree.

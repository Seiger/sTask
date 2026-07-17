# Конфігурація

## `config/sTaskCheck.php`

Зливається у `cms.settings`:

| Key | Default | Значення |
| --- | --- | --- |
| `check_sTask` | `true` | package presence/check flag |
| `sTaskVer` | `2.9999999.9999999.9999999-dev` | development branch version marker |

Це не worker runtime tuning.

## Table presets

| File | Config key | Surface |
| --- | --- | --- |
| `config/tasks/table.php` | `stask.tasks.table` | Завдання |
| `config/workers/table.php` | `stask.workers.table` | Воркери |
| `config/logs/table.php` | `stask.logs.table` | Логи |

Presets визначають provider, wire methods, pagination, views, filters, columns, modal і actions. Для project override використовуйте Evolution custom config mechanism або publish/extension point, якщо він підтриманий вашою версією; не редагуйте vendor files.

## `config/excluded_namespaces.php`

Список namespace prefixes, які `WorkerDiscovery` не розглядає. За замовчуванням виключені framework/vendor простори на кшталт `Illuminate\`, `Symfony\`, `EvolutionCMS\Legacy\`, `PHPUnit\` тощо.

Якщо ваш worker випадково знаходиться під виключеним prefix, перенесіть його до package/project namespace. Не скорочуйте список без аналізу: discovery може почати інстанціювати тисячі сторонніх classes.

## `config/artisan_security.php`

Використовується `ArtisanWorker`:

| Key | Default |
| --- | --- |
| `dangerous_commands` | `migrate:fresh`, `migrate:reset`, `db:wipe` |
| `confirmation_required` | `migrate`, `migrate:refresh`, `migrate:rollback`, `db:seed`, `cache:clear-full` |
| `whitelist` | empty: усі, крім blocked |
| `blacklist` | empty |
| `enabled` | `true` |
| `log_executions` | `true` |
| `required_permission` | `run_artisan` |

Whitelisted patterns підтримують `*` за contract comments. У production залишайте security enabled і формуйте явний whitelist під operational потреби.

## Worker settings

Stored у `s_workers.settings` JSON:

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

`TaskWorker` самостійно розраховує next run; `shouldRunNow()` є helper-ом worker side і не є основним scheduler decision у CLI.

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

| Path | Дані |
| --- | --- |
| `core/storage/stask/{id}.log` | append-only progress |
| `core/storage/stask/uploads` | upload/result files controller/worker |
| Laravel cache | worker instances, metrics, supervisor locks |

Provider створює тільки root `storage/stask`. Subdirectories створюються відповідним code path.

## Package metadata для dDocs

dDocs читає:

- Composer name `seiger/stask`;
- localized `lang/{locale}/global.php` keys `module_title`, `module_description`, `module_icon`;
- `docs/{locale}`.

Очікувані metadata:

```php
'module_title' => 'sTask',
'module_icon' => 'tabler-progress-check',
```

`docs/docs.json` є portable inventory manifest. Поточний dDocs runtime може не використовувати manifest напряму; discoverability забезпечує Composer package scan і фізичне localized docs tree.

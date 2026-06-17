# Backend Guide

Ця сторінка описує backend integration points для EvoUI manager surface.

## Boot sequence

`Seiger\sTask\sTaskServiceProvider` реєструє migrations, translations, views,
routes, Evolution module, Livewire component, console worker і table presets.

Manager module entry - `module/sTaskModule.php`. Він перевіряє manager mode і
рендерить `sTaskController::index()`.

## Controllers

`sTaskController` рендерить manager shell і standalone task detail shell.
`sTaskActionController` відповідає за task actions, worker actions, uploads,
chunked uploads і downloads.

## Data providers

UI дані лишаються package-owned:

- `DashboardData` для dashboard cards і recent rows;
- `TasksTableData` для task rows і filters;
- `WorkersTableData` для worker rows і actions;
- `LogsTableData` для logs і task detail data.

Не вбудовуй queries напряму в Blade, тримай data shaping у providers.

## Runtime services

`WorkerDiscovery` знаходить worker classes. `WorkerService` створює і контролює
tasks. `MetricsService` дає statistics. `TaskProgress` нормалізує progress для
UI.

## Queue command

`TaskWorker` обробляє queued tasks і має поважати status, attempts, priority,
progress і worker exceptions.

## Verification

Після backend змін запускай:

```console
composer test
```


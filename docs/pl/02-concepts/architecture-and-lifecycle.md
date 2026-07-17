# Backend Guide

Ta strona opisuje backend integration points dla powierzchni managera EvoUI.

## Boot sequence

`Seiger\sTask\sTaskServiceProvider` rejestruje migrations, translations, views,
routes, Evolution module, Livewire component, console worker i table presets.

Manager module entry to `module/sTaskModule.php`. Sprawdza manager mode i
renderuje `sTaskController::index()`.

## Controllers

`sTaskController` renderuje manager shell i standalone task detail shell.
`sTaskActionController` obsluguje task actions, worker actions, uploads, chunked
uploads i downloads.

## Data providers

- `DashboardData` dla dashboard cards i recent rows;
- `TasksTableData` dla task rows i filters;
- `WorkersTableData` dla worker rows i actions;
- `LogsTableData` dla logs i task detail data.

Nie przenos queries do Blade; data shaping zostaje w providers.

## Runtime services

`WorkerDiscovery` znajduje worker classes. `WorkerService` tworzy i kontroluje
tasks. `MetricsService` dostarcza statistics. `TaskProgress` normalizuje
progress dla UI.

## Queue command

`TaskWorker` przetwarza queued tasks i respektuje status, attempts, priority,
progress oraz worker exceptions.

## Verification

```console
composer test
```


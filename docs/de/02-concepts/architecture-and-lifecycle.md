# Backend Guide

Diese Seite beschreibt Backend-Integrationspunkte fuer die EvoUI Manager
Oberflaeche.

## Boot sequence

`Seiger\sTask\sTaskServiceProvider` registriert migrations, translations, views,
routes, Evolution module, Livewire component, console worker und table presets.

Manager module entry ist `module/sTaskModule.php`. Es prueft manager mode und
rendert `sTaskController::index()`.

## Controllers

`sTaskController` rendert manager shell und standalone task detail shell.
`sTaskActionController` verarbeitet task actions, worker actions, uploads,
chunked uploads und downloads.

## Data providers

- `DashboardData` fuer dashboard cards und recent rows;
- `TasksTableData` fuer task rows und filters;
- `WorkersTableData` fuer worker rows und actions;
- `LogsTableData` fuer logs und task detail data.

Keine Queries direkt in Blade verschieben; data shaping bleibt in providers.

## Runtime services

`WorkerDiscovery` findet worker classes. `WorkerService` erstellt und steuert
tasks. `MetricsService` liefert statistics. `TaskProgress` normalisiert progress
fuer UI.

## Queue command

`TaskWorker` verarbeitet queued tasks und respektiert status, attempts, priority,
progress und worker exceptions.

## Verification

```console
composer test
```


# Guide backend

Cette page resume les points d'integration backend qui alimentent la surface
manager EvoUI.

## Boot sequence

`Seiger\sTask\sTaskServiceProvider` enregistre migrations, translations, views,
routes, Evolution module, Livewire component, console worker et table presets.

Le manager module entry est `module/sTaskModule.php`. Il verifie le manager mode
et rend `sTaskController::index()`.

## Controllers

`sTaskController` rend le manager shell et le standalone task detail shell.
`sTaskActionController` gere task actions, worker actions, uploads, chunked
uploads et downloads.

## Data providers

- `DashboardData` pour dashboard cards et recent rows;
- `TasksTableData` pour task rows et filters;
- `WorkersTableData` pour worker rows et actions;
- `LogsTableData` pour logs et task detail data.

Ne deplace pas les queries dans Blade; data shaping reste dans les providers.

## Runtime services

`WorkerDiscovery` trouve les worker classes. `WorkerService` cree et controle les
tasks. `MetricsService` fournit statistics. `TaskProgress` normalise progress
pour l'UI.

## Queue command

`TaskWorker` traite queued tasks et respecte status, attempts, priority, progress
et worker exceptions.

## Verification

```console
composer test
```


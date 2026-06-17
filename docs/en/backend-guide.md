# Backend Guide

This page summarizes the backend integration points that support the EvoUI
manager surface.

## Boot Sequence

`Seiger\sTask\sTaskServiceProvider` registers migrations, translations, views,
routes, the Evolution module, Livewire component, console worker, and table
configuration presets.

The manager module entry is `module/sTaskModule.php`. It guards manager mode and
renders `sTaskController::index()`.

## Controllers

`sTaskController` renders the manager shell and standalone task detail shell.
`sTaskActionController` owns task actions, worker actions, uploads, chunked
uploads, and downloads.

## Data Providers

Backend data for the UI is intentionally package-owned:

- `DashboardData` for dashboard cards and recent rows;
- `TasksTableData` for task rows and filters;
- `WorkersTableData` for worker rows and actions;
- `LogsTableData` for logs and task detail data.

Keep data shaping in these providers instead of embedding queries in Blade.

## Runtime Services

`WorkerDiscovery` finds worker classes. `WorkerService` creates and controls
tasks. `MetricsService` provides statistics. `TaskProgress` normalizes progress
messages and values for UI consumption.

## Queue Command

`TaskWorker` is the console command that processes queued tasks. It must respect
task status, attempts, priorities, progress, and worker exceptions.

## Verification

Use the package smoke suite after backend changes:

```console
composer test
```


# sTask Documentation

sTask is an Evolution CMS manager package for background tasks. It discovers
workers, creates queued tasks, tracks execution progress, stores logs and
results, and gives administrators a compact EvoUI + Livewire control panel.

## Guides

- [User Guide](user-guide.md)
- [Developer Guide](developer-guide.md)
- [Reference](reference.md)
- [Configuration](configuration.md)
- [Troubleshooting](troubleshooting.md)
- [Custom Worker Migration](custom-worker-migration.md)
- [Frontend Guide](frontend-guide.md)
- [Backend Guide](backend-guide.md)

## Manager Surfaces

- Dashboard cards for pending, running, completed, failed, total tasks, and active workers.
- Recent tasks with an eye action and double-click details modal.
- Tasks table/list with worker, action, status, priority, attempts, and created-date filters.
- Workers table/list with edit, run, activate/deactivate, status, class availability, and last-task signals.
- Logs panel with the same task detail modal used by tasks.
- Statistics placeholder for the performance/cache implementation task.

## Runtime Pieces

- `Seiger\sTask\sTaskServiceProvider` registers the manager module, routes, migrations, translations, views, EvoUI table presets, and Livewire panel.
- `Seiger\sTask\Livewire\ModulePanel` owns the EvoUI module shell.
- `Seiger\sTask\Tables\TasksTableData`, `WorkersTableData`, and `LogsTableData` provide table data.
- `Seiger\sTask\Console\TaskWorker` processes queued jobs every minute.
- `Seiger\sTask\Workers\BaseWorker` is the base class for custom workers.

## dDocs Note

Use this folder as the file-first documentation source. The legacy Docusaurus
pages are kept for historical public docs, not as the canonical manager migration
contract.

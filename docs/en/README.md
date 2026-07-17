# sTask 2.x

sTask is an Evolution CMS package for managing background tasks. It stores the queue in the database, finds workers through Composer, runs them with the `stask:worker` command, shows progress and log in the manager, and separately oversees long-term supervisor processes.

The documentation describes the actual branch `2.x`. It does not assume the presence of an external queue, SSE or WebSocket: live progress in the manager is read by periodic HTTP requests from `storage/stask/{taskId}.log` file logs.

## Who should read this

- **Administrator** — installation, permissions, cron, manager tabs, diagnostics, and production operations.
- **Integrator** — schedules, worker registration, manager routes, migrations, and updates.
- **PHP developer** — `TaskInterface`, `BaseWorker`, façade `sTask`, progress API and supervisor contract.

## Documentation Map

1. Getting Started
   - [Requirements, Installation, and Updates](01-getting-started/installation.md)
   - [Quick start](01-getting-started/quick-start.md)
2. Concepts
   - [Architecture and life cycle](02-concepts/architecture-and-lifecycle.md)
   - [Schedules](02-concepts/schedules.md)
   - [Process Supervisor](02-concepts/supervisor.md)
3. Evolution CMS Manager
   - [Panel, Tasks, Workers, Logs, and Statistics](03-manager/interface.md)
4. Development
   - [Facade and PHP API](04-development/public-api.md)
   - [Own worker](04-development/custom-worker.md)
   - [Routes, progress files, and downloads](04-development/routes-and-progress.md)
5. Operation
   - [Production recommendations](05-operations/production.md)
   - [Diagnostics](05-operations/troubleshooting.md)
   - [Transition from 1.x to 2.x](05-operations/upgrade-1-to-2.md)
6. Reference
   - [Configuration](06-reference/configuration.md)
   - [Database tables](06-reference/database.md)
   - [CLI, statuses, and routes](06-reference/cli-statuses-routes.md)
   - [FAQ](06-reference/faq.md)

## Liability Limits

sTask executes the worker sequentially in the process `stask:worker`. The package does not provide an exactly-once, distributed broker guarantee, automatic termination of the OS process with an emergency stop button, or storage of all progress messages in the database. Such requirements are implemented by the application worker and the project infrastructure.

## First check

Once installed, perform:

```bash
cd core
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan stask:worker
```

Expected result: migrations were created `s_workers`, `s_tasks` and `s_supervisor_states`, package assets were published, and the `stask:worker` command finished with a message about the number of created and processed tasks. Next, open the **sTask** module in the manager.

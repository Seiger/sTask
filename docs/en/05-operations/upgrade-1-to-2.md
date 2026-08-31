# Migrating from sTask 1.x to 2.x

This is an evidence-based checklist, not an automatic upgrader. The repository doesn't have a full machine-readable migration contract for all third-party 1.x workers, so check each custom class.

## What changed the 2.x surface

- EvoUI/Livewire module with five tabs.
- Table/list views, server filters and readonly detail modals.
- Adaptive HTTP polling live progress.
- Duplicate suppression for active identifier/action/normalized meta.
- Worker registry refresh and class/title filters.
- Schedule types manual/once/periodic/regular/supervisor.
- Separate `SupervisorWorkerInterface`, `SupervisorStatus`, `s_supervisor_states`.
- Emergency stop as DB-level failed transition.
- Compact UI: Priority/Attempts have been removed from the current columns/filters.

## Before Updating

1. Make a backup database, `core/composer.lock`, custom workers and `storage/stask` audit as needed.
2. Fix active tasks; let them finish.
3. Inventory workers:

   ```sql
   SELECT id, identifier, class, active, settings FROM s_workers ORDER BY id;
   ```

4. Find custom classes that implement the old contract.
5. Check PHP 8.4 and evo-ui 1.2+.

## Adaptation worker

Recommended form:

```php
final class ExampleWorker extends BaseWorker
{
    public function identifier(): string { return 'example'; }
    public function scope(): string { return 'custom'; }
    public function icon(): string { return '<i data-lucide="settings"></i>'; }
    public function title(): string { return 'Example'; }
    public function description(): string { return 'Example worker'; }

    public function taskMake(sTaskModel $task, array $options = []): void
    {
        // business logic
        $task->update(['progress' => 100, 'result' => ['ok' => true]]);
        $this->markFinished($task, null, 'Done');
    }
}
```

Check out:

- action naming `task{StudlyAction}`;
- signatures with `sTaskModel` and array options;
- metadata methods;
- no `$modx`; use `evo()`/services;
- finalization and exception path;
- progress messages one-line;
- secrets do not get into the UI.

## Schedule migration

Move the old custom schedule keys to:

```json
{
  "schedule": {
    "enabled": true,
    "type": "periodic",
    "frequency": "hourly",
    "time": "*:10",
    "datetime": "",
    "start_time": "",
    "end_time": ""
  }
}
```

2.x scheduler expects `taskMake()` for conventional schedules. Manual/once/periodic/regular are not cron expressions.

## Supervisor migration

Don't simulate a minute-by-minute health check like a regular queued task. For daemon class, add `SupervisorWorkerInterface`, stable key, read-only inspection, detached start/restart, and grace. sTask will only create task rows for lifecycle events.

## Database

Run package migrations and check:

- the main tables were not destructive recreation;
- `s_supervisor_states` created;
- permission `stask` active;
- existing worker identifiers have not changed by accident;
- settings JSON is valid.

Current base migrations have `Schema::create`, so reinstall-safe behavior depends on the current upstream reference. Always upgrade to a proven 2.x commit and run migration smoke on a copy of the production schema.

## Assets and cache

```bash
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

Old JS/CSS in the browser cache can break tab switching or live progress.

## Acceptance test

- sTask is opened with permission role;
- each of the five tabs works;
- registry sees custom workers;
- manual `taskMake` ends;
- future schedule creates a queued task;
- Live Progress is updated by polling;
- double click opens the modal;
- emergency stop indicates test active record failed;
- Supervisor state transitions to starting → healthy without event flood;
- dDocs shows a localized sTask tree.

## Rollback

Roll back code/lock and DB consistently. Do not delete `s_supervisor_states` or new fields while the 2.x process can run. If 1.x doesn't understand the new settings, save the backup and prepare an explicit transform.

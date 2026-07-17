# Architecture and life cycle

## Components

| Component | Responsibility |
| --- | --- |
| `sWorker` | worker logging record, class, active/hidden, position, JSON settings |
| `TaskInterface` | minimum metadata/UI contract worker |
| `BaseWorker` | config, schedules, task creation, action dispatch, progress, and finalization |
| `sTaskModel` | persisted task, status, meta/result, timestamps, and scopes |
| `TaskWorker` | Creating scheduled tasks and sequentially executing a ready-made queue |
| `TaskProgress` | Append-only Live Progress in `storage/stask/{id}.log` |
| `WorkerDiscovery` | search concrete `TaskInterface` classes in Composer classmap |
| `WorkerService` | Resolution, Validation, and Cache Worker Instances |
| `SupervisorService` | Serialized inspection/start/restart of long-term processes |
| EvoUI + Livewire | manager tables, filters, modal windows and HTTP polling progress |

## Terms

- **Worker** is a PHP class that knows how to perform one or more actions.
- **Task** — persisted attempt to perform an action of a specific worker identifier.
- **Action** is a string like `make` or `sync_stock`; `BaseWorker` turns it into `taskMake()` or `taskSyncStock()`.
- **Schedule** is the JSON in the `s_workers.settings.schedule` that `stask:worker` supports the next queued task.
- **Progress** — volatile snapshot/history in file `.log`; The `s_tasks.progress` field is not automatically synchronized by each `pushProgress()`.
- **Metadata (`meta`)** — normalized input parameters of task, cast model `array`.
- **Result** — the final payload or path recorded by the worker; DB type `LONGTEXT`, cast model `array`.
- **Message** is a short persisted state/error in `s_tasks.message`; Live history is stored separately.

## Creation

There are two main ways:

1. `sTask::create($identifier, $action, $data, $priority, $userId)`.
2. `$worker->createTask($action, $options)` in `BaseWorker`.

Both normalize meta by recursively sorting associative keys and look for active duplicate by `identifier + action + normalized meta`. Active status `10`, `30`, `50`.

This only protects against identical active records. This is not a global distributed lock and is not a substitute for business operation.

## Execution

`stask:worker` follows these steps:

1. reads all active workers;
2. for enabled schedules, creates missing follow-up tasks or executes supervisor pass;
3. Selects all queued tasks where `start_at IS NULL OR start_at <= now()`;
4. For each task, finds Active Worker Record and Class;
5. checks `TaskInterface`;
6. puts status `running`, `start_at = now()`, increases `attempts`;
7. causes `invokeAction()`;
8. If the worker has not finalized the task, it sets `finished` automatically;
9. With an exception, it sets `failed` and records error progress;
10. When there are no active tasks, deletes progress files older than 24 hours.

The current command loads all ready queued rows without batch limit and processes sequentially. Plan the volume so that one cron pass does not freeze indefinitely.

## Status

| Code | Constant | Text | Meaning |
| ---: | --- | --- | --- |
| 10 | `TASK_STATUS_QUEUED` | `pending` | Waiting Time/Worker Pass |
| 30 | `TASK_STATUS_PREPARING` | `preparing` | intermediate state for application code |
| 50 | `TASK_STATUS_RUNNING` | `running` | Action Is Running or Considered Active |
| 80 | `TASK_STATUS_FINISHED` | `completed` | Successful Final State |
| 100 | `TASK_STATUS_FAILED` | `failed` | Error or Emergency Stop |

There is no separate persisted cancelled status. `isFinished()` returns `true` only for `80` and `100`.

## Timestamps and duration

- `created_at` — when a record was created.
- `start_at` is the planned time before execution, and after `markAsRunning()` is the actual start.
- `finished_at` — finalization.
- `updated_at` is the last change to record.
- accessor `duration` — seconds from `start_at` to `finished_at`; for a running task, from `start_at` to the current time.

Due to the double `start_at` value, the upcoming queued task shows the scheduled start, and the completed task shows the actual start set at startup.

## Retry

`markAsRunning()` increases `attempts`. `canRetry()` true for a failed task, as long as `attempts < max_attempts`. However, the standard `TaskWorker` does not return a failed task automatically in queued and does not have a separate retry command. Retry policy must be implemented by an integrator or worker. Don't promise users automatic recurrences just after `max_attempts = 3`.

## Progress and live UI

`pushProgress()` adds a single pipe-separated string to the `.log`. JavaScript watcher:

- starts at intervals of 1.2 seconds;
- increases the delay to 25 seconds if the snapshot does not change;
- checks once every 5 seconds in the hidden/invisible tab;
- does not make parallel requests;
- stops at `finished`, `failed`, `completed` or after five network failures;
- after final status, asks Livewire to refresh the surface.

This is HTTP polling, not push transport.

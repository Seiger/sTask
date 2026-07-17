# CLI, statuses and routes

## CLI

### `stask:worker`

```bash
php artisan stask:worker
```

No options. Creates scheduled tasks, supervises daemon workers, processes all ready queued tasks sequentially, executes cleanup-if-idle and returns exit code 0 even if individual tasks fail (exceptions are caught per task).

Scheduled provider: every minute.

### `stask:publish`

```bash
php artisan stask:publish
```

Copies package assets to public `assets/site`; uses `Filesystem`. After updating the package, run again.

### Related Evolution commands

```bash
php artisan migrate
php artisan package:discover
php artisan route:list --path=stask
php artisan schedule:list
php artisan cache:clear-full
```

Their exact availability depends on the Evolution CMS version.

## Task status

| Code | PHP constant | API text | Active | Final |
| ---: | --- | --- | --- | --- |
| 10 | `TASK_STATUS_QUEUED` | pending | yes | no |
| 30 | `TASK_STATUS_PREPARING` | preparing | yes | no |
| 50 | `TASK_STATUS_RUNNING` | running | yes | no |
| 80 | `TASK_STATUS_FINISHED` | completed | no | yes |
| 100 | `TASK_STATUS_FAILED` | failed | no | yes |

Unknown code → `unknown`.

## Supervisor state

| Value | Meaning | requiresLaunch |
| --- | --- | --- |
| `healthy` | process available | no |
| `starting` | Startup in Progress | no |
| `degraded` | health has deteriorated | yes |
| `failed` | inspection/launch failure | yes |
| `stopped` | process stopped/not found | yes |

## Schedule values

Types: `manual`, `once`, `periodic`, `regular`, `supervisor`.

Frequencies:

- periodic: `minutely`, `every_5min`, `every_15min`, `every_30min`, `hourly`, `daily`, `weekly`, `monthly`;
- regular: `every_5min`, `every_15min`, `every_30min`, `hourly`.

## Manager routes

All have a prefix `/stask`, route name prefix `sTask.` and middleware `mgr`. Full table with methods and payload context: [Routes, progress files, and downloads](../04-development/routes-and-progress.md).

Key:

```text
GET  /stask
POST /stask/worker/{identifier}/run/{action}
GET  /stask/task/{id}/progress
GET  /stask/task/{id}
GET  /stask/performance/summary
POST /stask/cache/clear
```

## Public PHP classes

| Class | Role |
| --- | --- |
| `Seiger\sTask\sTask` | facade service |
| `Seiger\sTask\Facades\sTask` | Laravel facade |
| `Seiger\sTask\Workers\BaseWorker` | worker base |
| `Seiger\sTask\Contracts\TaskInterface` | worker contract |
| `Seiger\sTask\Contracts\SupervisorWorkerInterface` | daemon capability |
| `Seiger\sTask\Support\SupervisorStatus` | immutable health snapshot |
| `Seiger\sTask\Enums\SupervisorState` | daemon states |
| `Seiger\sTask\Models\sTaskModel` | task Eloquent model |
| `Seiger\sTask\Models\sWorker` | worker Eloquent model |
| `Seiger\sTask\Models\sSupervisorState` | live state model |
| `Seiger\sTask\Services\TaskProgress` | file progress |
| `Seiger\sTask\Services\WorkerDiscovery` | registry discovery |
| `Seiger\sTask\Services\WorkerService` | resolution/cache |
| `Seiger\sTask\Services\SupervisorService` | lifecycle supervision |
| `Seiger\sTask\Services\MetricsService` | stats/cache metrics |

## Exceptions

- `WorkerNotFoundException`;
- `WorkerClassNotFoundException`;
- `WorkerInvalidInterfaceException`.

Each resolution exception has a context payload for logging. Manager/API layer can convert them into JSON messages; Do not show the Stack Trace End User.

## Formatting helpers in UI

- `niceCount(int|float)` — compact count;
- `niceSize(bytes)` — readable size;
- `niceEta(seconds)` — duration/ETA/uptime.

This is Evolution/evo-ui runtime helpers, not sTask facade.

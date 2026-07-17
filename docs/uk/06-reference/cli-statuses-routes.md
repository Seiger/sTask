# CLI, статуси й маршрути

## CLI

### `stask:worker`

```bash
php artisan stask:worker
```

Без options. Створює scheduled tasks, supervise-ить daemon workers, обробляє всі ready queued tasks послідовно, виконує cleanup-if-idle і повертає exit code 0 навіть якщо окремі tasks failed (exceptions ловляться per task).

Scheduled provider: every minute.

### `stask:publish`

```bash
php artisan stask:publish
```

Копіює package assets у public `assets/site`; використовує `Filesystem`. Після оновлення package запускайте повторно.

### Суміжні Evolution commands

```bash
php artisan migrate
php artisan package:discover
php artisan route:list --path=stask
php artisan schedule:list
php artisan cache:clear-full
```

Їх точна доступність залежить від Evolution CMS version.

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

| Value | Значення | requiresLaunch |
| --- | --- | --- |
| `healthy` | process доступний | no |
| `starting` | startup у progress | no |
| `degraded` | health погіршився | yes |
| `failed` | inspection/launch failure | yes |
| `stopped` | process stopped/not found | yes |

## Schedule values

Types: `manual`, `once`, `periodic`, `regular`, `supervisor`.

Frequencies:

- periodic: `minutely`, `every_5min`, `every_15min`, `every_30min`, `hourly`, `daily`, `weekly`, `monthly`;
- regular: `every_5min`, `every_15min`, `every_30min`, `hourly`.

## Manager routes

Усі мають prefix `/stask`, route name prefix `sTask.` і middleware `mgr`. Повна таблиця з methods та payload context: [Маршрути, progress-файли і завантаження](../04-development/routes-and-progress.md).

Ключові:

```text
GET  /stask
POST /stask/worker/{identifier}/run/{action}
GET  /stask/task/{id}/progress
GET  /stask/task/{id}
GET  /stask/performance/summary
POST /stask/cache/clear
```

## Public PHP classes

| Class | Роль |
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

Кожна resolution exception має context payload для logging. Manager/API layer може перетворювати їх у JSON message; не показуйте stack trace end user.

## Formatting helpers у UI

- `niceCount(int|float)` — compact count;
- `niceSize(bytes)` — readable size;
- `niceEta(seconds)` — duration/ETA/uptime.

Це Evolution/evo-ui runtime helpers, не methods sTask facade.

# Маршрути, progress-файли і завантаження

## Статус API

Усі routes нижче знаходяться в manager middleware group `mgr`. Це внутрішній manager HTTP surface, не публічний REST API для зовнішніх клієнтів. Використовуйте session authentication і CSRF для POST.

## Routes

| Method | Path | Route name | Призначення |
| --- | --- | --- | --- |
| GET | `/stask` | `sTask.index` | module shell |
| GET | `/stask/stats` | `sTask.stats` | counts |
| POST | `/stask/task` | `sTask.task.create` | facade task creation |
| POST | `/stask/task/store` | `sTask.task.store` | alias create |
| GET | `/stask/task/{id}` | `sTask.task.show` | full task details |
| POST | `/stask/worker/{identifier}/run/{action}` | `sTask.worker.task.run` | create + launch worker |
| GET | `/stask/task/{id}/progress` | `sTask.task.progress` | progress snapshot/history |
| GET | `/stask/task/{id}/download` | `sTask.task.download` | result download |
| POST | `/stask/task/{id}/upload` | `sTask.task.upload` | task-bound upload |
| POST | `/stask/worker/{identifier}/upload` | `sTask.worker.upload` | pre-task worker upload |
| POST | `/stask/clean` | `sTask.clean` | delete old finished tasks |
| GET | `/stask/server-limits` | `sTask.serverLimits` | PHP upload limits |
| GET | `/stask/workers` | `sTask.workers` | discover and redirect |
| POST | `/stask/worker/clean-orphaned` | `sTask.worker.clean` | remove missing classes |
| POST | `/stask/worker/activate` | `sTask.worker.activate` | activate by identifier |
| POST | `/stask/worker/deactivate` | `sTask.worker.deactivate` | deactivate by identifier |
| GET | `/stask/performance/summary` | `sTask.performance.summary` | metrics summary |
| GET | `/stask/performance/workers` | `sTask.performance.workers` | worker stats |
| GET | `/stask/performance/alerts` | `sTask.performance.alerts` | alerts |
| GET | `/stask/cache/stats` | `sTask.cache.stats` | worker cache stats |
| POST | `/stask/cache/clear` | `sTask.cache.clear` | clear worker cache |

## Запуск action

```http
POST /stask/worker/search_index/run/make
Content-Type: application/json
X-CSRF-TOKEN: ...

{"batch_size":100,"force":false}
```

Controller бере nested `options` або весь body, вилучає `_token` і `options`, resolve-ить active worker та викликає `createTask()`.

Успішна відповідь:

```json
{"success":true,"id":123,"message":"Task created successfully"}
```

HTTP code може залишитися 200 навіть коли `success=false`; client повинен перевіряти JSON flag.

Після response controller використовує `fastcgi_finish_request()` або synchronous fallback, далі намагається запустити `stask:worker`. Це не гарантує окремий queue process на всіх SAPIs.

## Progress endpoint

```http
GET /stask/task/123/progress?include_log=0
Accept: application/json
```

Success:

```json
{
  "success": true,
  "code": 200,
  "id": 123,
  "status": "running",
  "progress": 42,
  "processed": 420,
  "total": 1000,
  "eta": "37s",
  "message": "Обробляю пакет",
  "log_lines": []
}
```

Без `include_log=0` endpoint додає останні 50 messages. Invalid ID повертає 400; відсутній progress file — 404.

## Формат файла

Path:

```text
core/storage/stask/{taskId}.log
```

Кожен append-only рядок:

```text
status|progress|processed|total|eta|message
```

`readProgress()` читає останній валідний рядок; `readLog()` витягує message з останніх N rows. Write failure навмисно не валить business task, тому відсутність live progress не доводить, що task не виконується.

## Cleanup

Коли queued/preparing/running tasks відсутні, `stask:worker` видаляє `*.json` старші 24 годин і temp JSON старші 10 годин. Поточний `TaskProgress` фактично використовує `*.log`, тому ці log files автоматично цим циклом не видаляються. Налаштуйте окрему retention policy для `storage/stask/*.log` після узгодження з audit requirements.

## Upload/download

Controller має normal і chunked upload paths, server limit endpoint та worker-specific allowed extensions. Файли зберігаються під `storage/stask/uploads`.

Правила інтегратора:

- не покладайтеся лише на extension;
- звіряйте MIME і фактичний формат у worker;
- обмежуйте size та chunk count;
- генеруйте server-side filenames;
- не дозволяйте path traversal;
- видаляйте temporary/result files за retention policy;
- не повертайте download path, доки файл не існує і не належить task.

Точний upload payload залежить від widget/worker contract; не вважайте endpoint універсальним файловим API.

## Permissions

`sTaskController::index()` і `show()` явно перевіряють permission `stask`; частина action methods покладається лише на `mgr`. Інфраструктурно обмежте module routes manager session-ом, а в custom controllers повторюйте permission check для destructive operations.

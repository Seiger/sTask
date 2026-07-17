# Routes, progress files, and downloads

## API Status

All routes below are in manager middleware group `mgr`. This is an internal manager HTTP surface, not a public REST API for external clients. Use session authentication and CSRF for POST.

## Routes

| Method | Path | Route name | Purpose |
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

## Launch action

```http
POST /stask/worker/search_index/run/make
Content-Type: application/json
X-CSRF-TOKEN: ...

{"batch_size":100,"force":false}
```

The controller takes nested `options` or the entire body, removes `_token` and `options`, resolves the active worker, and calls `createTask()`.

Successful answer:

```json
{"success":true,"id":123,"message":"Task created successfully"}
```

HTTP code can remain 200 even when `success=false`; client should check the JSON flag.

After the response controller uses `fastcgi_finish_request()` or synchronous fallback, then tries to run `stask:worker`. This does not guarantee a separate queue process on all SAPIs.

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
  "message": "Processing batch",
  "log_lines": []
}
```

Without `include_log=0` endpoint adds the last 50 messages. Invalid ID returns 400; Missing progress file — 404.

## File Format

Path:

```text
core/storage/stask/{taskId}.log
```

Each append-only line:

```text
status|progress|processed|total|eta|message
```

`readProgress()` reads the last valid line; `readLog()` extracts message from the last N rows. Write failure intentionally does not crash a business task, so the lack of live progress does not prove that the task is not completed.

## Cleanup

When queued/preparing/running tasks are missing, `stask:worker` deletes `*.json` older than 24 hours and temp JSON older than 10 hours. The current `TaskProgress` actually uses `*.log`, so these log files are not automatically deleted by this loop. Set up a separate retention policy for `storage/stask/*.log` after reconciling with audit requirements.

## Upload/download

Controller has normal and chunked upload paths, server limit endpoint, and worker-specific allowed extensions. Files are stored under `storage/stask/uploads`.

Integrator rules:

- Don't rely only on extension;
- check the MIME and the actual format in the worker;
- limit size and chunk count;
- generate server-side filenames;
- do not allow path traversal;
- delete temporary/result files by retention policy;
- Do not return Download Path until the file exists and belongs to Task.

The exact upload payload depends on the widget/worker contract; don't think of endpoint as a universal file API.

## Permissions

`sTaskController::index()` and `show()` explicitly check permission `stask`; The Action Methods part relies only on `mgr`. Infrastructurally limit the module routes manager session to the session, and in custom controllers, repeat permission check for destructive operations.

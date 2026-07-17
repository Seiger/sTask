# Manager Interface

The module has five Livewire tabs: **Panel**, **Tasks**, **Workers**, **Logs**, **Statistics**. Switching occurs without a complete reboot of the manager frame. The initial tab can be passed by the query parameter `get`; The unknown value is replaced by `dashboard`.

## Access

The module shell and task details can be opened by a manager user with permission `stask`. All HTTP routes are in the middleware group `mgr`. Some action endpoints only rely on `mgr` middleware and don't call `hasPermission('stask')` repeatedly, so don't post `/stask/*` outside of manager authentication.

## EvoUI tables common rules

Tables **Tasks**, **Workers**, **Logs** support:

- search;
- per page: 15, 30, 50, 100, 200 (default 30);
- table/list view;
- sorting only by columns marked `sortable` in preset;
- multi-select and date-range filters;
- row actions;
- double click in all string views: in the Panel, in Tasks and Logs, it opens task details, and in Workers — modal editing.

Search and filters are applied on the server. List view changes the view, not the dataset.

## Panel

![sTask Dashboard with Key Metrics](../../assets/screenshots/stask-dashboard.png)

### Cards

The panel shows counts queued, running, finished, failed, workers and active workers. This is followed by recent tasks and, if available, a separate table of recent errors.

### Recent tasks

Columns: ID, Worker, Action, Status, Progress, Start of Execution, Actions. For active task, the string gets the progress URL, and `stask-module.js` periodically reads the snapshot without log history (`include_log=0`).

Double-clicking or the `eye` icon opens a modal with the main fields, task log, meta, and result. Content readonly.

### Live progress

Progress bar/values and message are updated via HTTP polling. Secure inline Markdown supported: backticks, bold, strikethrough, emphasis. HTML first escapes. On final status, Livewire refreshes the panel once.

## Tasks

![sTask Table](../../assets/screenshots/stask-tasks.png)

### Search

Searches by numeric ID, `identifier`, `action`, `message`.

### Filters

- **Worker** is a searchable list with human `worker->title` rather than raw identifiers.
- **Action** — distinct actions from the database.
- **Status** — queued, preparing, running, completed, failed.
- **User** — manager users who have already run tasks, plus `system` for `started_by IS NULL OR <= 0`.
- **Creation range** — boundaries inclusive from start-of-day to end-of-day.

Priority and Attempts are not relevant columns or filters of this tab. They remain runtime/schema fields for compatibility, but are not documented as UI control.

### Columns

| Column | Meaning |
| --- | --- |
| ID | `#id`; newest first for default |
| Worker | localized/human title or identifier fallback |
| Action | action code |
| Status | Numeric Status Badge |
| Progress | `0–100%`; Active Row Can Be Updated Live |
| Started by | username or `system` |
| Messages | persisted message with safe Markdown rendering |
| Start of execution | `start_at`; For Future Queued Task, this is the scheduled time |
| Completed | `finished_at` |

### Actions

- `eye` — modal details.
- `player-eject` — emergency stop for queued/preparing/running.

Emergency stop puts the status failed, `finished_at = now()` and the message "Task is crash stopped". It **does not terminate the PHP/OS process**. If the process continues to work, it can still change the data or progress file.

Double-clicking on the line opens the details modal.

## Workers

![Worker registry with schedules and supervisor uptime](../../assets/screenshots/stask-workers.png)

### Search & Filters

Search: identifier, scope, class. Filters:

- active/inactive;
- class available/missing;
- visible/hidden.

### Columns

- Identifier.
- Worker — title with class instance.
- Description — excerpt up to 96 characters.
- Schedule — chip; For Healthy Supervisor Badge shows Uptime after `niceEta()`.
- Number of tasks — `niceCount()` (compact localized number).
- Last action.
- Last run — the timestamp of the last task record.

Hidden is the manager visibility flag; worker record is not deleted. Inactive worker cannot be started and scheduler skips it.

### Toolbar and row actions

- `database-cog` — discover + rescan + clean orphaned + clear worker cache.
- `player-play` — starts selected/row worker only if active, class exists and is `taskMake()`.
- `edit` — modal settings.
- `power` — active toggle.
- `eye/eye-off` — visibility toggle.

The refresh registry can delete records whose class no longer exists. Before hitting production, check that Composer autoload is complete and deploy is not in an intermediate state.

### Modal Worker

Readonly: title, identifier, scope, class, description. Editable: active, hidden, position, schedule, additional JSON settings payload.

Additional JSON should not contain a `schedule` key: when saving, it is extracted and replaced with form values. Invalid JSON is not stored; provider leaves the previous custom settings.

For supervisor-capable class modal also shows:

- key;
- state badge;
- PID;
- heartbeat;
- runtime (`niceEta`);
- the latest diagnosis;
- last transition.

Supervisor option is hidden if class does not implement `SupervisorWorkerInterface`.

## Logs

![Task Progress Log](../../assets/screenshots/stask-logs.png)

This is not a separate log table: the tab reads `s_tasks` and shows the task history in more detail.

### Search & Filters

Search: ID, identifier, action, message. Filters: worker title, action, status, user including `system`, created date range.

### Columns

ID-link, worker title, identifier, action, status, progress, started by, created, start, finished, updated, **Work time**.

**Runtime** uses task duration: for final task `finished_at - start_at`, for active task `now - start_at`. Formatting is done by `niceEta()`. The name translation key is shared with supervisor uptime, but here it is the duration of the task.

ID opens a separate details page. Double-clicking opens a readonly modal with message, meta, result, and worker class.

## Statistics

![sTask statistics for the last 24 hours](../../assets/screenshots/stask-statistics.png)

Shows performance cards for the last 24 hours, alerts, and worker cache stats.

Real on the current implementation:

- task counts;
- success/error rate with status records;
- worker grouping;
- cache hits, misses, evictions, hit rate, cache size;
- Clearing Worker Cache.

Limitations: `MetricsService` so far returns `0` for average duration, average memory, and total execution time when aggregated from task records; common errors is also an empty placeholder. Do not use these values as production SLI without external telemetry.

`niceSize()` is used for human-readable memory values where there is a real byte value; `niceCount()` — for counts; `niceEta()` for seconds/duration.

## Data Security

Meta, result, message, and progress log are visible to manager users with access to the module. Do not share passwords, API tokens, session strings, or personal data unless you need to show it to the operator. Upload/download endpoints have worker-specific validation, but the worker still needs to check the file's type, size, and content.

# PRD: sTask migration to evo-ui

Date: 2026-05-13
Owner: sTask / WebUI migration lane
Target package: `/Users/dmi3yy/PhpstormProjects/Extras/sTask`
Reference packages: `sArticles`, `sLang`, `sSeo`, `sSettings`, `dGramm`,
`dIssues`, `evo-ui`
Reference issue storage: `/Users/dmi3yy/PhpstormProjects/dIssuesStorage`
Readiness status: PRD hardening tasks are ready to test; code implementation
starts from the dIssues implementation backlog with task-level artifacts
in dIssues under project `stask`.

## 1. Summary

sTask must be migrated from its standalone manager UI to the shared `evo-ui`
manager interface used by the already migrated Extras packages.

The goal is not only to restyle the current pages. The migration must move sTask
onto the same architectural contract as `sArticles`, `sLang`, `sSeo` and
`sSettings`:

- `evo-ui` owns the manager shell, assets, visual primitives, tables, forms,
  modals, tabs, dirty-state helpers, field rendering and reusable runtime
  behavior.
- `sTask` owns task/worker domain logic, routes, permissions, translations,
  worker discovery, worker execution, queue/progress semantics, security rules,
  metrics, provider data and task persistence.

The current sTask UI is operational and compact, but it is isolated from WebUI:
it publishes `stask.min.css` and `stask.js`, loads CDN Alpine/Lucide/Alertify and
Marked, owns a custom side navigation shell, uses inline CSS/JS in worker pages,
and renders worker widgets through package-local Blade scripts. That is exactly
the class of drift that the previous WebUI migrations exposed and then began to
consolidate.

The migration should produce a first evo-ui version of sTask that feels native
to the current Evolution manager WebUI while preserving all existing task
management behavior.

## 2. Evidence and Source Review

### sTask current state

Key files reviewed:

- `composer.json`: package is `seiger/stask`, type `evolutioncms-tool`, requires
  Evolution only and has no `evolution-cms/evo-ui` dependency or test script.
- `src/sTaskServiceProvider.php`: registers views, routes, migrations,
  translations, console commands and publishes `stask.min.css`, `stask.js`,
  `stask.svg`, `seigerit.svg`, tooltip JS.
- `src/Http/routes.php`: exposes dashboard, stats, task CRUD/show/progress,
  worker run/upload/settings/activate/deactivate, performance summary, alerts,
  cache stats and cache clear.
- `src/Controllers/sTaskController.php`: renders `dashboard`, `workers`,
  `workerSettings`, task detail, JSON stats/performance/cache endpoints.
- `src/Controllers/sTaskActionController.php`: starts tasks, returns progress
  snapshots, downloads result files, uploads worker input files.
- `src/Models/sTaskModel.php`: task lifecycle statuses are queued, preparing,
  running, finished and failed.
- `src/Models/sWorker.php`: worker metadata, activation, scope, settings,
  class resolution and widget rendering.
- `src/Workers/BaseWorker.php`: worker settings, schedules, task creation,
  action dispatch, progress helpers.
- `src/Workers/ArtisanWorker.php` and `ComposerUpdateWorker.php`: core worker
  widgets and real-time output/progress use cases.
- `views/index.blade.php`: custom manager shell, custom sidebar, package CSS/JS,
  CDN assets and manager globals.
- `views/dashboard.blade.php`: stats cards and recent task table.
- `views/workers.blade.php`: worker card UI, local styles, widget log/progress
  classes and action buttons.
- `views/workerSettings.blade.php`: worker settings page with large inline CSS.
- `views/widgets/*.blade.php`: per-worker controls, inline styles, inline JS,
  progress bars, logs, upload zones, command runner behavior.
- `views/task.blade.php`: task detail, payload/result/log display.

Current functional surfaces:

- Dashboard: task counters, recent tasks, status badges, progress bars, detail
  links.
- Workers list: discovery, worker grouping/scope, activation/deactivation,
  settings entry, widget rendering.
- Worker widget runtime: run action, upload file, progress polling, log output,
  button disabling/enabling, ETA display.
- Artisan worker UI: command input, arguments input, command list rendering,
  clickable commands, security messaging.
- Composer update worker UI: command execution with progress/log stream.
- Worker settings: schedule configuration, custom worker settings, worker stats,
  run/activate/deactivate controls.
- Task detail: metadata, result payload, current status, raw log.
- Performance/cache endpoints: system summary, worker stats, alerts, cache
  statistics and cache clear.

### Previous WebUI migration lessons

The most important prior analysis is:

- `dIssuesStorage/issues/reports/webui-layer-separation-review-2026-05-10.md`
- `dIssuesStorage/issues/reports/webui-consolidation-backlog-manifest-2026-05-10.json`
- `evo-ui/docs/module-integration.md`
- `evo-ui/docs/components.md`
- `evo-ui/docs/forms.md`
- `evo-ui/docs/module-table-contract.md`
- `evo-ui/docs/consumer-drift-guards.md`
- `evo-ui/docs/four-module-release-gate.md`

Observed migration lessons:

- `sArticles` is the cleanest table/form consumer: configs and providers stay in
  the module; shell, table, forms and common UI come from `evo-ui`.
- `sLang` proves that embedded resource tabs are a special boundary. Full
  module screens can use `evo-ui`; legacy resource edit tabs may keep a narrow
  bridge only when documented and allowlisted.
- `sSeo` shows that local compact-form CSS becomes drift quickly. Generic form
  density, label alignment, heading style and editor field layout should move to
  `evo-ui`.
- `sSettings` shows that compact operational settings/builders are useful but
  dangerous to copy. Builder/reorder/DnD, settings rows, dirty state and save
  toasts must become shared primitives instead of inline Blade runtime.
- `dIssues` shows that large operational workspaces need provider contracts and
  shared workspace primitives, while workflow/comment/business state stays in
  the consumer.
- `evo-ui` now has drift guards and a four-module release gate. New consumers
  should have tests that prevent local CSS/JS and CDN/legacy-manager assets from
  reappearing.

### Migration lessons matrix

This matrix is implementation guidance, not background reading. Each row names
what sTask should reuse and what it must avoid copying.

| Reference | Reuse | Do not copy | sTask-specific consequence |
| --- | --- | --- | --- |
| `sArticles` | Declarative module shell, table/form preset ownership, clean provider split. | Duplicated module-tab dirty-navigation controller if `evo-ui` shared tabs are available. | Use `sArticles` as the main table/form consumer donor, but keep task execution logic in sTask. |
| `sLang` | Explicit boundary for embedded Evolution resource tabs. | Resource-tab legacy globals in full manager module screens. | sTask is a full manager module, not an embedded resource tab; it should use evo-ui shell, not resource bridges. |
| `sSeo` | Form/table config registration, custom form field registration, manager/resource boundary discipline. | Local compact form CSS and editor boot code for generic layout. | Worker settings must use evo-ui settings rows/forms, not another package-local compact CSS layer. |
| `sSettings` | Compact settings-row and builder lessons, dirty-state/save-toast issues, DnD extraction pressure. | Inline builder/DnD runtime or local Save button variants. | Worker settings can borrow the contract idea, but any reusable row/action/runtime behavior belongs in evo-ui. |
| `dIssues` | Provider-owned workspace data and evo-ui-owned workspace rendering. | Legacy admin CSS/JS and old board surfaces after evo-ui workspace exists. | Task/workers/performance surfaces should be provider-backed; old sTask shell assets must be quarantined or removed. |
| `dGramm` | Manager module registration, labels, hidden canonical module alias pattern when useful, sTask worker dependency. | Treating an installed dependency as visible UI without `registerModule()`. | dGramm can require sTask, but sTask itself must register the manager module and install/publish assets correctly. |
| `evo-ui` | `x-evo::layout`, `evo::partials.assets`, module tabs, module-table, form/settings-row, drift guards. | Publishing evo-ui assets from consumers or redefining shared atoms locally. | sTask must depend on evo-ui and consume it; shared task runner primitives should land in evo-ui. |

### Integration bugs already observed

These are not theoretical risks. They happened while wiring sTask into the local
demo and must be encoded into the PRD/test gates:

- sTask was installed through dGramm dependency but did not appear in manager
  modules until `registerModule()` and `module/sTaskModule.php` existed.
- `abort()` inside the namespaced sTask controller resolved as
  `Seiger\sTask\Controllers\abort()` in this Evolution runtime. Controller
  access checks should use explicit exceptions or verified global helpers.
- Manager permissions are session-loaded at login. After adding the `stask`
  permission, the correct operational step is manager logout/login, not a
  controller fallback that queries permissions directly.
- Legacy sTask UI rendered unstyled because `stask.min.css`, `stask.js` and
  `seigerit.tooltip.js` had not been published to the demo site. The browser
  saw HTML/500 responses and rejected CSS because of MIME mismatch.
- `sWorker` naming is sharp: the real model class is
  `Seiger\sTask\Models\sWorker` in source file `src/Models/sWorker.php`, and
  action/controller imports must be checked by syntax/static tests.

Policy: do not solve these with hidden runtime fallbacks. Fix the installation,
service-provider, permission-session, asset-publish and model-import contracts
explicitly, then test them.

## 3. Problem Statement

sTask is currently a standalone manager application inside Evolution CMS. This
creates these problems:

- UI drift: sTask has its own shell, sidebar, cards, buttons, badges, progress
  bars, modals, logs and form styling instead of shared WebUI primitives.
- Asset drift: sTask loads CDN Alpine, Lucide, Alertify and Marked; it also
  publishes package CSS/JS assets for generic manager behavior.
- Inline runtime drift: worker pages and widgets contain inline scripts and
  styles for generic actions, logs, progress and command handling.
- Missing declarative surfaces: dashboard/table/form screens are not expressed
  as `evo-ui` configs/providers, unlike the newer packages.
- Missing tests: there is no `composer test` compatibility suite to assert
  `evo-ui` dependency, shell usage, table/form/provider contracts or drift
  rules.
- Missing shared primitive: `evo-ui` does not yet have a first-class task
  workspace/log/progress primitive. sTask migration should either add one or
  define a temporary sTask-local adapter with explicit follow-up.

## 4. Goals

1. Migrate sTask full manager screens to `evo-ui` layout/assets.
2. Keep all current sTask domain behavior intact.
3. Replace current dashboard/recent tasks UI with `evo-ui` cards, dashboard and
   module-table patterns.
4. Replace worker listing and settings surfaces with Livewire/provider-backed
   `evo-ui` screens.
5. Standardize worker run controls, progress bars, logs, uploads and task output
   using shared or clearly proposed `evo-ui` primitives.
6. Add sTask compatibility tests and include sTask in consumer drift scanning.
7. Update docs in localized filesystem-readable form, following current Extras
   documentation discipline.

## 5. Non-goals

- Do not rewrite the task execution engine.
- Do not change task status codes or database schema unless a UI feature truly
  requires it.
- Do not move worker discovery, scheduling, security rules, Artisan command
  validation, Composer execution or metrics semantics into `evo-ui`.
- Do not force embedded third-party worker widgets to be fully declarative in
  the first pass. They need a compatibility adapter and migration guide.
- Do not copy `sSettings` builder CSS/JS or `sSeo` compact-form CSS into sTask.

## 6. Target Architecture

### Package dependency

sTask should require `evolution-cms/evo-ui` with the same release family as
current migrated packages:

```json
{
  "require": {
    "evolution-cms/evo-ui": "^1.0.1"
  },
  "scripts": {
    "test": "php tests/run.php"
  }
}
```

The exact constraint should be aligned with the release state of `evo-ui` at
implementation time.

### Service provider

sTask should continue to load migrations, translations, routes and console
commands. It should additionally:

- register a visible Evolution manager module in manager mode through
  `registerModule()`;
- provide a `module/sTaskModule.php` wrapper that renders the evo-ui entrypoint;
- expose localized `module_title` and `module_icon` labels in every active
  language file;
- merge table configs under `stask.tasks.table` and `stask.workers.table`;
- merge form configs under `evo-ui.forms.stask.worker-settings`;
- register Livewire components such as:
  - `stask.module-panel`;
  - `stask.dashboard-panel` if dashboard aggregation needs module-owned state;
  - `stask.worker-widget` or `stask.task-runner` if the first release needs a
    wrapper around imperative worker controls.

Avoid publishing generic manager CSS/JS once screens use `evo-ui`. Keep icons
and genuinely package-specific assets only if still needed.

### Install and manager boot contract

The first implementation task must make local installation repeatable before
any UI refactor starts.

Required local install checks:

```bash
cd /Users/dmi3yy/PhpstormProjects/Extras/sArticles/demo/core
composer show seiger/stask
php artisan package:discover -n
php artisan migrate:status | rg 'stask|dgramm'
php artisan route:list | rg 'stask|sTask'
```

Expected:

- `Seiger\sTask\sTaskServiceProvider` is discovered.
- `Dmi3yy\dGramm\dGrammServiceProvider` can depend on sTask without hiding the
  sTask manager module.
- sTask migrations are applied.
- `/stask` routes exist for dashboard, workers, task progress, uploads,
  downloads, stats, cache and performance.
- `evo_role_permissions` contains `permission = stask` for the admin role.
- after permission changes, the manager user logs out and logs in again so
  `$_SESSION['mgrPermissions']` refreshes.

Legacy-shell asset checks while `views/index.blade.php` still loads old assets:

```bash
php artisan stask:publish
curl -I 'http://127.0.0.1:8788/assets/site/stask.min.css?dev-main'
curl -I 'http://127.0.0.1:8788/assets/site/stask.js'
curl -I 'http://127.0.0.1:8788/assets/site/seigerit.tooltip.js'
```

Expected MIME:

- `stask.min.css`: `text/css`
- `stask.js`: `application/javascript`
- `seigerit.tooltip.js`: `application/javascript`

New evo-ui-shell asset checks:

```bash
cd /Users/dmi3yy/PhpstormProjects/Extras/sArticles/demo/core
php artisan vendor:publish --tag=evo-ui --force
curl -I 'http://127.0.0.1:8788/assets/modules/evo-ui/evo-ui.css'
curl -I 'http://127.0.0.1:8788/assets/modules/evo-ui/evo-ui.js'
```

Do not continue a UI wave while assets return HTML, 404 or 500 responses.

### Manager shell

Replace `views/index.blade.php` as the active shell with an `evo-ui` layout
screen:

```blade
<x-evo::layout :title="$pageTitle">
    <livewire:stask.module-panel :active-tab="$activeTab" />
</x-evo::layout>
```

If a transitional shell is required, it must use:

```blade
@include('evo::partials.assets')
<main class="evo-ui {{ $themeClasses }}" data-evo-ui-root>
```

The final version must not load:

- `stask.min.css` for common manager UI;
- `stask.js` for common manager UI;
- CDN Alpine/Lucide/Alertify/Marked;
- old manager `main.js` as a UI dependency;
- module-local button/card/table/form systems.

### Module tabs

Recommended first sTask tabs:

- `dashboard`: overview, health, alerts, recent tasks.
- `tasks`: all tasks table with filters and detail modal/page.
- `workers`: worker inventory, activation, quick actions.
- `performance`: system metrics, worker metrics, cache stats.
- `settings` or worker detail route: only if global sTask settings are added.

The top-level tab shell should use the shared `evo-ui` module-tab dirty guard
when settings forms are present. If the shared primitive is not mature enough,
open a paired `evo-ui` task and avoid duplicating the full Alpine modal pattern
long-term.

### Tables and providers

Create provider-backed `evo-ui.module-table` presets:

- `stask.tasks`: task list with status, worker, action, priority, progress,
  created/start/finished timestamps, started-by user and row actions.
- `stask.workers`: worker list with title, identifier, scope, class, active,
  hidden, position, tasks count, last run and row actions.
- `stask.performance.workers`: optional metrics table if dashboard cards are not
  enough.

Provider responsibilities stay in sTask:

- querying `s_tasks` and `s_workers`;
- resolving worker metadata safely;
- formatting task statuses and priority;
- exposing row action methods;
- persisting activation/deactivation, settings, cache clearing and cleanup;
- exposing detail modal defaults/data where supported.

`evo-ui` responsibilities:

- table/list view, filters, sorting, pagination, row action styling, badges,
  progress visual cells if promoted, empty states and modals.

Concrete provider contracts:

`src/Tables/TasksTableData.php` should expose a table id of `stask.tasks` and
read from `Seiger\sTask\Models\sTaskModel` with `worker` and `user` relations.

Task row shape:

| Key | Source | Notes |
| --- | --- | --- |
| `id` | `s_tasks.id` | integer, displayed as `#id` |
| `identifier` | `s_tasks.identifier` | searchable worker identifier |
| `worker_title` | `task.worker.title` when available | fallback only to formatted identifier for display, not for lookup |
| `action` | `s_tasks.action` | searchable |
| `status` | `s_tasks.status` | raw numeric status |
| `status_label` | status map | localized label |
| `status_tone` | status map | `neutral`, `info`, `primary`, `success`, `danger` |
| `priority` | `s_tasks.priority` | `low`, `normal`, `high` |
| `priority_tone` | priority map | `muted`, `neutral`, `warning` |
| `progress` | `s_tasks.progress` | integer clamped to `0..100` |
| `started_by` | `task.user.username` | `system` when empty |
| `created_at` | `s_tasks.created_at` | manager-locale formatted plus raw ISO |
| `start_at` | `s_tasks.start_at` | nullable |
| `finished_at` | `s_tasks.finished_at` | nullable |
| `actions` | route map | detail, download when finished |

Default task filters:

- status: queued, preparing, running, finished, failed;
- priority: low, normal, high;
- worker identifier;
- created date range;
- incomplete only.

Default task sorting:

- newest first by `created_at desc`;
- secondary `id desc`;
- allow `status`, `priority`, `identifier`, `start_at`, `finished_at`.

`src/Tables/WorkersTableData.php` should expose a table id of `stask.workers`
and read from `Seiger\sTask\Models\sWorker`.

Worker row shape:

| Key | Source | Notes |
| --- | --- | --- |
| `id` | `s_workers.id` | integer |
| `identifier` | `s_workers.identifier` | stable lookup key |
| `title` | `sWorker::title` accessor | display only |
| `description` | `sWorker::description` accessor | display only |
| `scope` | `s_workers.scope` or accessor | filterable |
| `class` | `s_workers.class` | shown in details, searchable |
| `class_exists` | accessor | danger tone when false |
| `active` | `s_workers.active` | boolean toggle action |
| `hidden` | `s_workers.hidden` | hidden workers excluded by default |
| `position` | `s_workers.position` | default ordering |
| `settings_summary` | `s_workers.settings` | short, safe text summary |
| `tasks_count` | `tasks()` relation count | optional eager count |
| `last_task_status` | latest related task | optional status badge |
| `last_run_at` | latest related task timestamps | nullable |
| `actions` | route map | settings, run when supported, activate/deactivate |

Default worker filters:

- active/inactive;
- scope;
- class exists / missing;
- hidden/visible;
- supports `taskMake`;
- worker identifier search.

Route compatibility map:

| Capability | Current route | New UI behavior |
| --- | --- | --- |
| dashboard | `GET /stask` / `sTask.index` | module panel default tab |
| stats | `GET /stask/stats` / `sTask.stats` | dashboard cards provider |
| task create/store | `POST /stask/task`, `POST /stask/task/store` | keep for compatibility until task-runner contract replaces direct forms |
| task detail | `GET /stask/task/{id}` / `sTask.task.show` | detail page first; modal/drawer only after evo-ui primitive exists |
| run worker action | `POST /stask/worker/{identifier}/run/{action}` | task-runner submit endpoint |
| progress | `GET /stask/task/{id}/progress` | task-runner polling endpoint |
| download | `GET /stask/task/{id}/download` | result action visible only when finished |
| task upload | `POST /stask/task/{id}/upload` | task-scoped upload |
| worker upload | `POST /stask/worker/{identifier}/upload` | pre-task worker upload |
| server limits | `GET /stask/server-limits` | upload primitive metadata |
| workers | `GET /stask/workers` / `sTask.workers` | workers tab provider |
| worker settings | `GET/POST /stask/worker/{identifier}/settings` | settings rows/forms |
| worker activation | `POST /stask/worker/activate`, `POST /stask/worker/deactivate` | row actions |
| cleanup | `POST /stask/clean`, `POST /stask/worker/clean-orphaned` | guarded toolbar actions |
| performance/cache | `/stask/performance/*`, `/stask/cache/*` | performance tab/cards |

Controller ownership:

- Existing `sTaskController` and `sTaskActionController` remain the compatibility
  HTTP surface for Wave 0 and Wave 1.
- New provider classes may query models directly for read-only table/card data.
- Mutating actions must call existing controller/service methods or shared sTask
  services instead of duplicating task creation, worker activation, cache clear,
  or upload behavior.
- New routes are allowed only for the evo-ui shell/panel; existing route names
  must not be broken without an explicit migration task.

### Forms

Worker settings should be represented as either:

- a model-backed `evo-ui.form` for common `s_workers.settings`, or
- a module-owned Livewire panel using `x-evo::settings-row` for dynamic worker
  schemas until a more generic dynamic settings form contract exists.

The worker settings UI must support:

- active/inactive state;
- schedule type: manual, once, periodic, regular;
- date/time, frequency, days, interval, start/end time;
- custom worker settings returned by `TaskInterface::settings()`;
- save/cancel behavior;
- validation errors and save toast;
- dirty-state protection when leaving the tab.

### Task runner/log/progress primitive

sTask needs a task-runner UI pattern that is more specific than current `evo-ui`
forms/tables:

- run button with disabled/running state;
- progress bar with percent and ETA;
- append-only log stream;
- status tone mapping;
- optional file upload/drop zone;
- optional result download;
- poll progress endpoint with adaptive interval;
- render markdown-like log messages safely or replace with structured log
  tokens;
- command palette/list behavior for Artisan worker.

This should become a new `evo-ui` primitive if the pattern is reusable by future
packages:

- proposed component: `x-evo::task-runner`;
- proposed runtime namespace: `window.EvoUI.taskRunner`;
- proposed CSS atoms: `.evo-ui-task-runner`, `.evo-ui-progress`,
  `.evo-ui-log-stream`, `.evo-ui-command-list`, `.evo-ui-upload-zone`;
- proposed Livewire wrapper: `evo-ui.task-runner` only if server state needs to
  be shared.

If implementation time is too tight, create a narrow sTask adapter for
task-runner behavior, document it as temporary, and add it to the `evo-ui`
consumer drift allowlist with a follow-up task. Do not hide it as generic
module-local CSS/JS.

Minimum task-runner contract before implementation:

- Inputs:
  - worker identifier;
  - action name;
  - run endpoint;
  - progress endpoint template;
  - optional upload endpoint;
  - optional download endpoint;
  - initial task id/status when a task is already running;
  - labels/translations supplied by sTask.
- Events:
  - `task-runner:start`;
  - `task-runner:progress`;
  - `task-runner:finished`;
  - `task-runner:failed`;
  - `task-runner:upload-start`;
  - `task-runner:upload-progress`;
  - `task-runner:upload-failed`;
  - `task-runner:reset`.
- Progress response shape:
  - `id`;
  - `status`;
  - `progress`;
  - `message`;
  - `log` or append-only log delta;
  - `eta` when available;
  - `download_url` when available.
- Stop polling when:
  - status is finished or failed;
  - progress endpoint returns 404 for a deleted task;
  - request fails repeatedly past a documented retry limit;
  - the root element is disconnected.
- Log rendering:
  - default to escaped text with line breaks;
  - markdown-like formatting must be sanitized or replaced by structured log
    tokens;
  - do not load remote Marked/CDN runtime.
- Button state:
  - run/upload buttons use real disabled state while pending/running;
  - buttons re-enable on failure, cancellation or completed terminal state;
  - error state must be visible without requiring console inspection.
- Upload:
  - support normal and chunked uploads if current endpoints need it;
  - show file size errors from `/stask/server-limits`;
  - preserve task upload and worker upload endpoints.
- Artisan command list:
  - empty command still lists available commands;
  - command rows remain clickable;
  - security rejection messages remain visible and auditable.

The first task-runner implementation may live in sTask only if it is explicitly
named as a temporary adapter. Otherwise, reusable runtime and CSS belong in
`evo-ui`.

Task-runner API contract:

`x-evo::task-runner` or the temporary `sTask` adapter must accept this server
configuration from the module:

| Key | Required | Meaning |
| --- | --- | --- |
| `identifier` | yes | worker identifier, e.g. `artisan` or `composer_update` |
| `action` | yes | worker action, usually `make` |
| `runUrl` | yes | `route('sTask.worker.task.run', ...)` |
| `progressUrlTemplate` | yes | `route('sTask.task.progress', ['id' => '__ID__'])` |
| `uploadUrl` | no | worker upload endpoint before task exists |
| `taskUploadUrlTemplate` | no | task-scoped upload endpoint after task exists |
| `downloadUrlTemplate` | no | task result download endpoint |
| `serverLimitsUrl` | no | `/stask/server-limits` |
| `initialTaskId` | no | existing task id for resumed watches |
| `initialStatus` | no | existing task status |
| `labels` | yes | localized labels from sTask |
| `csrfToken` | yes | manager CSRF token |

Run request:

```http
POST /stask/worker/{identifier}/run/{action}
X-Requested-With: XMLHttpRequest
Content-Type: multipart/form-data or application/x-www-form-urlencoded
```

Request payload:

```json
{
  "options": {
    "command": "cache:clear",
    "arguments": "--no-interaction",
    "uploaded_file": "/absolute/server/path/when-needed"
  }
}
```

Successful run response:

```json
{
  "success": true,
  "id": 123,
  "message": "Task created successfully"
}
```

Failed run response:

```json
{
  "success": false,
  "message": "Worker not found or inactive"
}
```

The UI must not assume HTTP `200` means success. It must inspect `success`.

Progress response:

```json
{
  "success": true,
  "code": 200,
  "id": 123,
  "status": "running",
  "progress": 42,
  "processed": 21,
  "total": 50,
  "eta": "12s",
  "message": "Processing item 21",
  "log_lines": ["Preparing", "Processing item 21"]
}
```

Progress error response:

```json
{
  "success": false,
  "code": 404,
  "error": "Progress file not found",
  "id": 123,
  "status": "not_found",
  "message": "Progress tracking not available"
}
```

Polling rules:

- start at `750ms` for the first five polls;
- use `1000ms` while status is `preparing` or `running`;
- use `2500ms` while status is `queued` or `not_found`;
- stop on `finished`, `failed`, `error`, explicit cancellation, or disconnected
  root element;
- stop after five consecutive network/server failures and show the last error;
- keep `404 progress not found` as non-terminal for the first ten polls because
  the worker may not have created its progress file yet;
- terminal states must re-enable run/upload buttons and leave the final log
  visible.

Progress/log rendering:

- render `message` and `log_lines` as escaped text by default;
- convert line breaks to `<br>` only after escaping;
- allow structured classes only when the server sends a trusted token, not raw
  HTML;
- keep the last 50 lines visible, matching the current controller;
- do not load Marked, highlight.js, or any CDN parser for logs.

Upload contract:

Direct upload request uses field `file`.

Chunked upload request uses:

```json
{
  "file": "binary chunk",
  "chunk_index": 0,
  "total_chunks": 3,
  "session_id": "upload_...",
  "original_filename": "data.csv"
}
```

Upload success response:

```json
{
  "success": true,
  "code": 200,
  "message": "File uploaded successfully",
  "result": "worker_...",
  "original_filename": "data.csv",
  "file_size": 1024,
  "file_path": "/server/storage/stask/uploads/worker_..."
}
```

Upload UI rules:

- read limits from `GET /stask/server-limits` when available;
- use direct upload under `singleUploadLimit`;
- use chunked upload above `singleUploadLimit` and below `maxFileSize`;
- show allowed extension and file-size errors inline;
- do not retry uploads automatically after validation errors;
- pass returned file path/name into the next run request only when the worker
  contract requires it.

Download contract:

- show download action only after a finished task has a downloadable result;
- unfinished tasks must show disabled or absent download action;
- `400`, `404` and `500` JSON responses must render inline, not only in console.

Artisan command-list behavior:

- empty command means list available commands;
- clickable command rows must set the command input without auto-running it;
- arguments stay separate from command name;
- security rejection messages from `config/artisan_security.php` remain visible
  in the log;
- destructive commands must not be prefilled by the UI.

Third-party worker widget adapter boundary:

sTask currently allows workers to render custom widget/settings HTML. The evo-ui
migration must keep third-party workers usable, but the compatibility boundary
has to be narrow and visible.

Allowed temporary adapter:

- wraps one worker-provided `renderWidget()` result inside a constrained evo-ui
  panel;
- isolates legacy widget markup from module navigation, tables and dashboard;
- does not load global legacy CSS/JS for the whole module;
- may load worker-specific assets only when the worker declares them explicitly;
- exposes the shared task-runner config to the widget when possible;
- marks the row/card as `legacy widget` in developer-facing metadata or debug
  context;
- requires a follow-up issue for each built-in widget still using the adapter.

Not allowed:

- generic fallback shell for the whole sTask module;
- silent loading of `/assets/site/stask.min.css` or `/assets/site/stask.js` in
  the new evo-ui shell;
- remote CDN runtime introduced by a worker widget;
- raw script injection from arbitrary worker settings;
- replacing evo-ui table/form primitives with iframe-like legacy pages;
- treating adapter success as migration completion for built-in widgets.

Built-in widget migration policy:

| Widget | First evo-ui target | Adapter allowed? | Removal gate |
| --- | --- | --- | --- |
| default worker widget | shared task-runner primitive | only during Wave 4 | `stask-evo-ui-007` done |
| Composer update widget | task-runner plus log/result display | only during Wave 4 | `stask-evo-ui-008` done |
| Artisan worker widget | task-runner plus command-list behavior | only during Wave 5 | `stask-evo-ui-009` done |
| third-party widgets | constrained adapter | yes, documented per worker | migration guide and explicit issue |

Adapter QA:

- adapter can render a legacy widget without breaking evo-ui layout;
- adapter cannot affect dashboard/tasks/workers tables outside its own panel;
- browser console has no missing global `sTask` errors unless that worker is
  explicitly marked as legacy and has a follow-up task;
- built-in widgets have no adapter usage after their migration waves.

## 7. Functional Requirements

### Dashboard

- Show counters for pending, running, completed, failed, total, total workers
  and active workers.
- Show recent tasks with status badges, worker/action, progress, created time
  and details action.
- Show performance alerts when available.
- Show cache summary when available.
- Provide refresh action using `x-evo::button`.
- Use `x-evo::dashboard`, `x-evo::dashboard-card`, `x-evo::badge` and table/list
  primitives where possible.

### Tasks table

- List tasks with filtering by status, worker identifier, action, priority and
  date range.
- Search by task id, worker identifier, action and message.
- Sort by id, status, priority, progress, created_at, start_at and finished_at.
- Support table and list views if the data remains readable.
- Row actions:
  - view details;
  - download result when available;
  - retry failed task if `canRetry()`;
  - optionally clean/delete only behind explicit confirmation.
- Detail surface must show:
  - status and progress;
  - worker metadata;
  - timestamps and duration;
  - started-by user;
  - meta payload;
  - result payload or file download;
  - log stream or stored message.

### Workers table

- Auto-discover workers on workers tab load or explicit action.
- List worker title, icon, description, identifier, scope, class, active state,
  hidden state and task counts.
- Row actions:
  - run default action when available;
  - open settings;
  - activate/deactivate;
  - clear worker cache;
  - inspect class/problem state when class is missing.
- Show inactive/missing-class states without breaking the page.
- Keep worker widgets renderable, but begin moving built-in widgets to shared
  runner controls.

### Worker settings

- Replace current inline-styled worker settings page with an `evo-ui` settings
  form/panel.
- Support schedule configuration from `BaseWorker::getSchedule()`.
- Support custom settings returned from worker classes.
- Keep save action visible and consistent with WebUI.
- Dispatch `evo-ui:form.saved` after successful save.
- Do not mark modal-only controls dirty until modal save if modal editing is
  used.

### Built-in worker widgets

Composer update:

- Preserve run action, progress, log stream and error handling.
- Keep command options supported by `ComposerUpdateWorker::taskMake()`.
- Show result state and allow download when worker returns a file.

Artisan:

- Preserve command input and arguments input.
- Preserve empty command behavior for command listing.
- Preserve clickable command list.
- Preserve security validation messaging and audit behavior.
- Replace console styling with an `evo-ui` log/command-list style or add that
  style to the proposed task-runner primitive.

Default worker:

- Preserve endpoint badge display.
- Preserve schedule badges.
- Preserve run action, progress polling and button state.
- Replace local badge/button/progress classes with shared equivalents.

### Performance and cache

- Expose system performance summary in dashboard/performance tab.
- Expose worker performance stats in table/card form.
- Expose alerts as `evo-ui-alert` or dashboard warning cards.
- Expose cache stats and cache clear controls through shared buttons and
  confirmation.

### File upload/download

- Preserve `/stask/worker/{identifier}/upload`, `/stask/task/{id}/upload` and
  `/stask/task/{id}/download`.
- Use shared upload/drop-zone visuals if added to `evo-ui`; otherwise create a
  temporary sTask adapter with a named follow-up.
- Never allow generic upload styling to become broad module-local drift.

## 8. Required evo-ui Additions

Minimum additions or confirmations needed before a clean migration:

1. Task runner/progress/log primitive.
   - Needed by sTask worker widgets.
   - Donor: current sTask widget runtime.
   - Must support adaptive polling, log append, progress percent/ETA, disabled
     action buttons, error state, result download and optional upload zone.

2. Progress cell or progress bar primitive.
   - Needed by task table and dashboard.
   - Could be a typed `module-table` cell such as `type => progress`.

3. Command/log stream visual primitive.
   - Needed by Artisan and Composer workers.
   - Should avoid CDN Marked unless log rendering is sanitized and self-hosted.

4. Dynamic worker settings rows.
   - Existing `x-evo::settings-row` may be enough for manual rendering.
   - A later generic dynamic form provider would reduce custom Blade.

5. Drift scanner inclusion for sTask.
   - Add sTask to `evo-ui/tests/consumer-drift.php` scan list and allowlist only
     exact temporary adapters.

Nice-to-have additions:

- `evo-ui` table status/progress/date formatting helpers for operational task
  tables.
- Shared file-upload field/drop-zone component.
- Shared code/log viewer with copy action and safe wrapping.

## 9. Migration Plan

### Wave -1: PRD hardening and backlog visibility

Tasks:

- Create and keep the `stask` dIssues project isolated from `dgramm`,
  `sseo`, `slang`, `ssettings` and `dissues`.
- Add PRD-hardening issues to backlog before implementation.
- Add evo-ui companion backlog issues only for shared primitives, not sTask
  domain behavior.
- Update this PRD with:
  - install and manager boot contract;
  - lessons matrix from migrated packages;
  - no-hidden-fallback policy;
  - task-runner API contract;
  - baseline smoke/test matrix;
  - final readiness score and open decision list.

Backlog issues created:

| Issue | Project | Purpose |
| --- | --- | --- |
| `stask-prd-001` | `stask` | Install, module registration, permission and asset boot gates. |
| `stask-prd-002` | `stask` | Lessons matrix from prior WebUI migrations. |
| `stask-prd-003` | `stask` | Baseline compatibility and smoke test matrix. |
| `stask-prd-004` | `stask` | Provider/table/form/route contracts. |
| `stask-prd-005` | `stask` | Task-runner/progress/log/upload API contract. |
| `stask-prd-006` | `stask` | Third-party worker widget adapter and migration policy. |
| `stask-prd-007` | `stask` | Final PRD readiness gate and implementation decomposition. |
| `evo-ui-stask-001` | `evo-ui` | Shared task-runner/progress/log primitive. |
| `evo-ui-stask-002` | `evo-ui` | Module-table progress and operational status cells. |
| `evo-ui-stask-003` | `evo-ui` | Upload/drop-zone and log/code viewer visuals. |
| `evo-ui-stask-004` | `evo-ui` | Include sTask in consumer drift scan with exact allowlists. |
| `evo-ui-stask-005` | `evo-ui` | Document worker widget adapter boundary. |

Exit criteria:

- dIssues backlog contains all PRD-hardening and evo-ui companion tasks.
- This PRD reaches at least `8.5/10` readiness in review.
- No implementation starts until the first implementation issue has PRD/SPEC/QA
  artifacts in dIssues storage.

Launch order:

1. `stask-prd-001` and `stask-prd-002` first. These lock the install,
   manager-boot, asset, permission and prior-migration lessons so nobody starts
   by rediscovering simple setup failures.
2. `stask-prd-003` next. This freezes the baseline smoke matrix before any new
   UI code changes behavior.
3. `stask-prd-004` and `stask-prd-005` together. These are the last blockers
   before coding because table/form/route payloads and task-runner contracts
   decide the shape of the implementation.
4. `evo-ui-stask-001`, `evo-ui-stask-002` and `evo-ui-stask-003` may run in
   parallel after `stask-prd-005` has a draft contract.
5. `stask-prd-006` and `evo-ui-stask-005` run before migrating third-party
   worker widgets. They must not introduce a generic hidden fallback shell.
6. `stask-prd-007` is the final gate. It converts the PRD decisions into
   implementation issues and blocks code work if readiness is below `8.5/10`.

### Wave 0: Baseline and safety

Tasks:

- Add `tests/run.php` to sTask.
- Add `composer test`.
- Add `evolution-cms/evo-ui` dependency.
- Add tests asserting current routes, controllers, service provider, models and
  built-in workers are still present.
- Add drift baseline test that identifies current standalone UI assets as known
  pre-migration debt.
- Add tests for the known simple hazards:
  - service provider registers manager module in manager mode;
  - `module/sTaskModule.php` exists;
  - language files expose `module_title` and `module_icon`;
  - controller permission check uses `hasPermission('stask', 'mgr')`;
  - no unqualified `abort()` calls remain in namespaced sTask controllers unless
    a global helper is explicitly verified in the runtime;
  - no stale `Seiger\sTask\Models\Worker` import is used where `sWorker` is the
    actual model class expected by code.

Exit criteria:

- `composer test` exists and runs without a full browser.
- Current behavior is documented before UI changes start.
- Demo install smoke passes after logout/login when permissions were newly
  added.

### Wave 1: evo-ui shell and module panel

Tasks:

- Add new `views/index.blade.php` or `views/module/index.blade.php` using
  `x-evo::layout` or transitional `evo::partials.assets`.
- Add `src/Livewire/ModulePanel.php`.
- Define tabs for dashboard, tasks, workers and performance.
- Remove active CDN UI asset loading from the evo-ui screen.
- Keep old shell reachable only if explicitly needed as fallback.

Exit criteria:

- sTask opens inside `evo-ui` shell.
- No CDN Alpine/Lucide/Alertify/Marked on the new manager screen.
- Existing routes still resolve.

### Wave 2: Dashboard and tasks table

Tasks:

- Create `config/tasks/table.php`.
- Create `src/Tables/TasksTableData.php`.
- Convert recent tasks table to `evo-ui.module-table`.
- Add dashboard cards through `x-evo::dashboard`.
- Add task detail modal/page using shared badges, progress and code/log blocks.

Exit criteria:

- Task list supports search/filter/sort/pagination.
- Detail action shows all current task information.
- Dashboard counters match `sTask::getStats()`.

### Wave 3: Workers table and worker settings

Tasks:

- Create `config/workers/table.php`.
- Create `src/Tables/WorkersTableData.php`.
- Convert workers list to `evo-ui.module-table` or a provider-backed Livewire
  surface if widget embedding requires custom layout.
- Replace worker settings inline CSS page with `evo-ui` settings rows/forms.
- Preserve activate/deactivate, clean orphaned workers, worker cache clear and
  auto-discovery.

Exit criteria:

- Worker inventory uses shared table/list/cards and row actions.
- Worker settings save and dispatch shared saved event.
- Missing worker classes show a controlled warning state.

### Wave 4: Task runner primitive or adapter

Tasks:

- Implement or consume `evo-ui` task runner/progress/log primitive.
- Port built-in widgets:
  - default worker;
  - Composer update;
  - Artisan.
- Replace widget-local buttons, badges, progress bars, logs and upload zones.
- Remove `views/scripts/global.blade.php` and `views/scripts/task.blade.php`
  usage from active evo-ui screens.

Exit criteria:

- Running a worker starts the task and immediately shows progress.
- Polling stops on finished/failed.
- Buttons re-enable correctly on error.
- Artisan command list remains clickable.
- Composer/Artisan logs are readable and accessible.

### Wave 5: Performance/cache and cleanup

Tasks:

- Build performance tab from existing JSON endpoints/provider methods.
- Add cache stats and clear cache controls with shared confirmation.
- Remove obsolete published generic UI assets if no fallback uses them.
- Add sTask to evo-ui drift scanner.
- Tighten tests to fail on new inline generic manager CSS/JS.

Exit criteria:

- sTask active UI has no generic local CSS/JS drift.
- Only documented temporary adapters remain allowlisted.
- Tests pass in sTask and evo-ui drift report recognizes sTask.

### Wave 6: Documentation and release gate

Tasks:

- Add localized docs at minimum:
  - `docs/uk/README.md`, `user-guide.md`, `developer-guide.md`;
  - `docs/en/README.md`, `user-guide.md`, `developer-guide.md`;
  - `docs/pl/...`, `docs/de/...`, `docs/fr/...`.
- Update current Docusaurus docs or clearly mark them as legacy if dDocs should
  prefer localized filesystem docs.
- Document the worker widget adapter contract for third-party worker authors.
- Document how to migrate a custom `renderWidget()` to the new task-runner
  primitive.

Exit criteria:

- dDocs can read current package docs from filesystem without DB state.
- Worker authors have a migration path.

## 10. Acceptance Criteria

- sTask manager UI is rendered through `evo-ui` shell/assets.
- sTask no longer loads remote CDN UI libraries in active manager screens.
- sTask no longer publishes or loads package-local CSS/JS for generic manager
  components in the active evo-ui UI.
- Dashboard, tasks, workers, worker settings, task detail, performance and cache
  controls are available.
- Built-in default, Composer and Artisan workers can still run from manager UI.
- Progress polling, log display, upload and download behavior still work.
- Activation/deactivation, worker discovery, orphan cleanup and cache clear still
  work.
- `composer test` passes in sTask.
- `evo-ui` drift scan includes sTask or has a tracked task to include it.
- Documentation is updated in localized filesystem-readable format.

## 11. Test Plan

Static/package tests in `sTask/tests/run.php`:

- composer declares `evolution-cms/evo-ui`;
- composer has `test` script;
- service provider registers views, translations, migrations, routes, commands
  and Livewire components;
- service provider registers the manager module only in manager mode;
- `module/sTaskModule.php` exists and renders the evo-ui entrypoint;
- all active language files include `module_title` and `module_icon`;
- module shell uses `evo-ui` assets/layout;
- no active manager screen loads CDN UI assets;
- no active manager screen loads old `stask.min.css`/`stask.js` for generic UI;
- task and worker table configs exist and point to sTask providers;
- built-in workers still expose identifiers `artisan` and `composer_update`;
- routes for run/progress/upload/download/stats/workers/cache remain present.
- namespaced controllers do not call unqualified `abort()` unless the helper is
  verified in this Evolution runtime;
- manager permission checks use `hasPermission('stask', 'mgr')`;
- model imports resolve to real classes, especially the worker model surface.

Targeted PHP syntax:

- `php -l` on changed PHP files.

Package checks:

- `composer test` from sTask.
- `composer drift` from evo-ui after sTask is added to scan list.

Manual/browser smoke:

- Publish assets for the active shell:
  - legacy shell: `php artisan stask:publish`;
  - evo-ui shell: `php artisan vendor:publish --tag=evo-ui --force`.
- Verify CSS/JS MIME through browser Network or `curl -I`.
- If `stask` permission was just added, logout/login before opening the module.
- Open sTask dashboard in Evolution manager.
- Switch each tab.
- Run Composer update worker in a safe demo mode if available.
- Run Artisan with empty command to list commands.
- Run a safe Artisan command such as `cache:clear` only in a controlled local
  demo.
- Watch progress/log until completion.
- Open task detail.
- Toggle worker active state and save worker settings.
- Verify dark/light manager theme behavior.
- Verify narrow/mobile iframe width does not overlap text or controls.
- Verify browser console has no `Unexpected token '<'`, missing `sTask`, MIME,
  CDN, Livewire asset, or `Window.open` errors from the sTask screen.

Baseline compatibility and smoke matrix:

| Gate | Command or action | Expected result | Blocks |
| --- | --- | --- | --- |
| Package installed | `composer show seiger/stask` in demo core | Package resolves from the local path repository | all waves |
| Package discovery | `php artisan package:discover -n` | Provider discovery completes without PHP errors | all waves |
| sTask migrations | `php artisan migrate:status \| rg 'sTask|stask|s_workers|s_tasks'` | task tables and permission migration are visible/applied | Wave 0 |
| Manager permission | DB or manager UI check for permission key `stask` | admin role has `stask`; after changes, logout/login before testing | Wave 0 |
| Manager module | open manager after login | sTask module is visible in Modules, not only installed as dependency | Wave 0 |
| Module wrapper | static check for `module/sTaskModule.php` | wrapper renders the sTask controller entrypoint | Wave 0 |
| Routes | `php artisan route:list \| rg 'sTask|stask'` | dashboard, stats, task, worker, upload, download, performance and cache routes exist | Wave 0 |
| PHP syntax | `php -l` on touched PHP files | no syntax errors | every wave |
| Known namespace hazards | `rg -n 'abort\\(|Models\\\\Worker' src module` | no unqualified `abort()` in namespaced controllers; no stale worker model import | every wave |
| Legacy assets while old shell remains | `php artisan stask:publish` plus `curl -I` for `/assets/site/stask.min.css`, `/assets/site/stask.js`, `/assets/site/seigerit.tooltip.js` | HTTP `200`; CSS served as `text/css`; JS served as JavaScript | Wave 0 and legacy-shell checks |
| evo-ui assets | `php artisan vendor:publish --tag=evo-ui --force` plus `curl -I` for evo-ui CSS/JS | HTTP `200` with correct MIME | Wave 1+ |
| Dashboard smoke | open sTask module in manager | dashboard renders with counters and recent tasks; no raw browser default styling | Wave 1 |
| Worker list smoke | open Workers tab | built-in workers render and actions are visible | Wave 1 |
| Safe command list | open Artisan worker with empty command/list action | command list renders without running destructive commands | task-runner migration |
| Progress polling | run a safe local task, then poll `/stask/task/{id}/progress` | terminal status reaches finished/failed and polling stops | task-runner migration |
| Upload limits | `GET /stask/server-limits` | JSON includes server upload limits | upload migration |
| Upload endpoint | safe small test file against worker/task upload endpoint | accepted file stores under `storage/stask/uploads` and returns JSON | upload migration |
| Download endpoint | finished task with a result file | download only works for finished tasks and rejects unfinished tasks | result migration |
| Browser console | Safari/Chrome console during module smoke | no `Unexpected token '<'`, missing `sTask`, MIME, CDN, Livewire, or `Window.open` errors | every browser gate |

Status and priority mapping baseline:

| Legacy value | Meaning | evo-ui tone |
| --- | --- | --- |
| `10` | queued | neutral |
| `30` | preparing | info |
| `50` | running | primary/progress |
| `80` | finished | success |
| `100` | failed | danger |
| `low` | low priority | muted |
| `normal` | normal priority | neutral |
| `high` | high priority | warning |

## 12. Risks and Mitigations

Risk: worker widgets are intentionally extensible and third-party packages may
return arbitrary HTML from `renderWidget()`.

Mitigation: keep a compatibility wrapper for custom widgets in the first
release. Migrate built-in widgets first and document the new task-runner
contract.

Risk: progress polling/log behavior is reusable but not currently an `evo-ui`
primitive.

Mitigation: create the primitive in `evo-ui` before cleanup, or keep a narrow
allowlisted adapter with an explicit follow-up issue.

Risk: removing `stask.js` too early can break button state, upload and polling.

Mitigation: port runtime behavior feature-by-feature, with tests and browser
smoke after each worker widget migration.

Risk: old Docusaurus docs and new localized docs may both be indexed.

Mitigation: document source preference and mark legacy docs clearly or move old
docs under an ignored path after dDocs policy is confirmed.

Risk: manager permissions differ from module packages because sTask is a tool,
not a normal module.

Mitigation: keep `stask` permission checks in sTask controllers/providers and
only delegate visual rendering to `evo-ui`. Use `hasPermission('stask', 'mgr')`
and require manager logout/login after adding new permissions instead of hidden
database fallbacks.

Risk: installed dependency is assumed to create a visible manager module.

Mitigation: module visibility is a separate contract. sTask must register its
own manager module and wrapper file even when installed as a dGramm dependency.

Risk: assets are missing or served as HTML/500 during the transition.

Mitigation: asset publish and MIME checks are mandatory in the smoke plan.
Legacy shell uses `stask:publish`; evo-ui shell uses `vendor:publish --tag=evo-ui
--force`.

Risk: simple PHP namespace/model errors block the manager iframe.

Mitigation: baseline tests must catch unqualified `abort()` calls in namespaced
controllers and invalid worker model imports before browser testing.

## 13. Implementation Task Breakdown

### sTask tasks

1. `stask-evo-ui-001`: Add evo-ui dependency and baseline tests.
2. `stask-evo-ui-002`: Add evo-ui shell and Livewire module panel.
3. `stask-evo-ui-003`: Build dashboard cards and tasks table provider.
4. `stask-evo-ui-004`: Build task detail surface and progress/status cells.
5. `stask-evo-ui-005`: Build workers table provider and actions.
6. `stask-evo-ui-006`: Migrate worker settings to evo-ui settings rows/forms.
7. `stask-evo-ui-007`: Migrate default worker widget to task-runner primitive.
8. `stask-evo-ui-008`: Migrate Composer update worker widget.
9. `stask-evo-ui-009`: Migrate Artisan worker widget and command list.
10. `stask-evo-ui-010`: Build performance/cache tab.
11. `stask-evo-ui-011`: Remove or quarantine legacy active UI assets.
12. `stask-evo-ui-012`: Add localized docs and custom worker migration guide.

### evo-ui companion tasks

1. `evo-ui-stask-001`: Add task runner/progress/log primitive.
2. `evo-ui-stask-002`: Add module-table progress cell support.
3. `evo-ui-stask-003`: Add upload/drop-zone visual primitive if needed.
4. `evo-ui-stask-004`: Include sTask in consumer drift scan and release-gate
   documentation.
5. `evo-ui-stask-005`: Document worker widget adapter boundary.

## 14. Open Questions

- Should sTask stay `type: evolutioncms-tool`, or should package metadata align
  with the module-style packages where possible?
- Should task details be a full page, table modal, or split drawer once `evo-ui`
  has a drawer/detail primitive?
- Should old Docusaurus docs be migrated into localized docs now, or tracked as
  a separate documentation cleanup task?

Resolved decisions:

- Old standalone sTask shell may stay temporarily reachable only as a legacy
  compatibility surface during migration. The new evo-ui shell must not load its
  global CSS/JS.
- Third-party `renderWidget()` compatibility may stay through the constrained
  adapter boundary, but built-in widgets must leave the adapter by their
  migration tasks.

## 15. Implementation Readiness Gate

Ready to start code implementation when all are true:

- `stask-prd-001` through `stask-prd-006` are `Ready to test`.
- PRD contains install/boot gates, lessons matrix, baseline smoke matrix,
  provider contracts, task-runner API contract and adapter boundary.
- dIssues contains implementation backlog tasks `stask-evo-ui-001` through
  `stask-evo-ui-012`.
- dIssues contains evo-ui companion tasks `evo-ui-stask-001` through
  `evo-ui-stask-005`.
- First code task starts from `stask-evo-ui-001`; no UI implementation starts
  directly from this PRD without task artifacts.
- Any temporary adapter must have an exact issue id and removal gate.
- Permission changes are tested by logout/login, not by hidden runtime fallback.
- Asset publish/MIME checks pass for whichever shell is under test.

## 16. Recommended First Build Slice

Current readiness after final PRD gate review: `9.3/10`.

Remaining blockers before code implementation:

- `evo-ui-stask-001`: decide whether the task-runner contract lands as a shared
  evo-ui primitive immediately or as an explicitly temporary sTask adapter.
- Before each code task, create/attach task-level PRD/SPEC/QA artifacts in
  dIssues storage.

The first implementation should be small enough to verify quickly:

1. Add `evo-ui` dependency and `tests/run.php`.
2. Add evo-ui shell and module panel with `dashboard`, `tasks`, `workers`.
3. Convert dashboard counters and recent tasks table.
4. Add drift assertions that prevent reintroducing CDN assets in the new shell.

This gives sTask a visible WebUI foundation without touching worker execution
internals. After that, migrate worker widgets and settings deliberately, because
that is the highest-risk part of the package.

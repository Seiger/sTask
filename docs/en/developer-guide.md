# Developer Guide

## Installation Checks

Run inside the Evolution CMS `core` directory:

```console
php artisan package:installrequire seiger/stask "*"
php artisan vendor:publish --tag=stask
php artisan vendor:publish --tag=evo-ui --force
php artisan migrate
```

After permission migrations, log out and log back in so the manager permission
cache is refreshed.

## Architecture

sTask is a package-owned runtime with an EvoUI manager surface.

- sTask owns workers, task lifecycle, progress, uploads, downloads, permissions,
  and command execution.
- EvoUI owns shared manager layout, tabs, tables, filters, badges, modals,
  list/table switching, and local assets.
- Legacy widget code stays package-owned until it is migrated to a task-runner
  primitive.

Important files:

```text
module/sTaskModule.php
src/sTaskServiceProvider.php
src/Livewire/ModulePanel.php
src/Tables/TasksTableData.php
src/Tables/WorkersTableData.php
src/Tables/LogsTableData.php
src/Console/TaskWorker.php
src/Workers/BaseWorker.php
src/Workers/ArtisanWorker.php
src/Workers/ComposerUpdateWorker.php
config/tasks/table.php
config/workers/table.php
config/logs/table.php
config/artisan_security.php
```

## Manager Module And Assets

The active EvoUI manager shell must load `evo::partials.assets` and must not load
legacy `stask.min.css`, `stask.js`, CDN bundles, or the old manager main script.
If the manager renders unstyled HTML, check published local assets before adding
fallbacks.

## Worker Contract

Workers implement `TaskInterface` or extend `BaseWorker`.

Required identity methods:

```php
public function identifier(): string;
public function scope(): string;
public function title(): string;
public function description(): string;
public function settings(): array;
```

Task actions are methods named `task<Action>`. For example action `make` maps to:

```php
public function taskMake(\Seiger\sTask\Models\sTaskModel $task, array $options = []): void
{
    $this->pushProgress($task, [
        'progress' => 25,
        'message' => 'Preparing data',
    ]);

    $this->markFinished($task, null, 'Done');
}
```

## Task Data Contract

Tasks store:

- `identifier` and `action`;
- numeric `status`;
- `message`;
- `started_by`;
- `meta` and `result`;
- `start_at`, `finished_at`, `created_at`, `updated_at`;
- `attempts`, `max_attempts`, `priority`, and `progress`.

Use `meta` for structured input and `result` for structured output. The details
modal pretty-prints both fields when they contain JSON-like data.

## Progress And Logs

Use `pushProgress()` for long-running actions. The worker command and manager
details surface read progress, messages, meta, and result without requiring
module-specific JavaScript.

## Uploads And Downloads

The action controller supports task uploads, worker uploads, chunked uploads, and
downloads for completed tasks. Uploaded files are stored under the sTask storage
upload area and referenced through task metadata.

## Artisan Worker Security

`config/artisan_security.php` defines dangerous, confirmation-required, allowed,
and forbidden commands. Do not bypass this layer from widgets or custom manager
buttons.

## Widget Migration Policy

Existing `renderWidget()` output is a compatibility surface. New widgets should
move toward a shared task-runner pattern:

- declare inputs in config or provider data;
- submit options to a task action;
- render progress through the shared task detail/log modal;
- avoid inline scripts and local CSS for generic buttons, tables, modals, or
  status badges.

## Verification

```console
composer test
php -l src/Tables/TasksTableData.php
php -l src/Tables/WorkersTableData.php
php -l src/Tables/LogsTableData.php
```

Manual smoke:

- open the manager module;
- switch Dashboard, Tasks, Workers, Logs, and Statistics tabs;
- switch table/list views;
- open task details by eye action and double-click;
- confirm assets load as CSS/JS, not HTML error pages.

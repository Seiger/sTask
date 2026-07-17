# Facade and PHP API

The canonical service class is `Seiger\sTask\sTask`; facade — `Seiger\sTask\Facades\sTask`. Composer alias is `sTask` also logged, but explicit import is better readable and more convenient for static analysis.

## Creating a task

```php
public function create(
    string $identifier,
    string $action,
    array $data = [],
    string $priority = 'normal',
    ?int $userId = null,
): sTaskModel
```

```php
use Seiger\sTask\Facades\sTask;

$task = sTask::create(
    'catalog_sync',
    'sync_stock',
    ['shop_id' => 7, 'dry_run' => false],
    'normal',
    evo()->getLoginUserID() ?: null,
);
```

The method normalizes associative meta by recursive sorting and returns active duplicate if the identifier/action/meta already matches. New record: queued, progress 0, attempts 0, max_attempts 3.

Priority exists in PHP/schema for compatibility and queue ordering in `getPendingTasks()`, but is not shown in the current manager table columns/filters.

## Performing a single task

```php
public function execute(sTaskModel $task): bool
```

The method writes start metrics, sets running, resolves the worker through `WorkerService`, calls action, and finalizes the task if the worker has not done so. An exception commits a task to failed and returns `false`.

Call `execute()` only in a controlled CLI/queue context. Manager flow and scheduler use the `stask:worker`.

## Queue

```php
public function getPendingTasks(int $limit = 10): Collection
public function processPendingTasks(?int $batchSize = null): int
```

`getPendingTasks()` reads status `10`, sorts priority high → normal → low, then `created_at`. Unlike CLI `TaskWorker`, this method does not filter future `start_at`; Do not use it for Scheduler Semantics without an additional condition.

`processPendingTasks()` sequentially calls `execute()` and returns the number of successful tasks.

## Statistics and metrics

```php
public function getStats(): array
public function getPerformanceMetrics(int $hours = 24): array
public function getWorkerStats(?string $identifier = null, int $hours = 24): array
public function getPerformanceAlerts(): array
```

`getStats()` returns counts pending/running/completed/failed/total and workers. Performance API partially placeholder: duration/memory aggregation from task records has not yet been implemented.

## Worker registry

```php
public function discoverWorkers(): array
public function registerWorker(string $className): ?sWorker
public function cleanOrphanedWorkers(): int
public function getWorkers(bool $activeOnly = false): Collection
public function getWorker(string $identifier): ?sWorker
public function activateWorker(string $identifier): bool
public function deactivateWorker(string $identifier): bool
```

Discovery works on the Composer classmap. After adding a class:

```bash
composer dump-autoload
php artisan package:discover
```

or click refresh registry in the UI. Remember: new records are created inactive.

## Worker cache

```php
public function getCacheStats(): array
public function clearWorkerCache(?string $identifier = null): void
```

`WorkerService` has in-memory cache and Laravel cache entries with prefix `stask_worker_`. Clear a specific identifier after changing settings/class or the entire cache after registry refresh/deploy.

## Clearing history

```php
public function cleanOldTasks(int $days = 30): int
```

Deletes only finished tasks (`status = 80`) with `finished_at` highest cutoff. Failed, queued, running, supervisor state, and progress files are not cleared by this method.

## sTaskModel

Useful scopes and methods:

```php
sTaskModel::queued();
sTaskModel::preparing();
sTaskModel::running();
sTaskModel::finished();
sTaskModel::failed();
sTaskModel::incomplete();
sTaskModel::byIdentifier('catalog_sync');
sTaskModel::byAction('make');

$task->markAsRunning();
$task->markAsFinished('Done');
$task->markAsFailed('Reason');
$task->updateProgress(50, 'Half complete');
$task->canRetry();
$task->isFinished();
$task->isRunning();
$task->isPending();
```

`markAsRunning()` overwrites `start_at = now()` and increases attempts. `markAsFinished()` put progress 100. `markAsFailed()` don't put progress 100.

## Meta and Result

The model casts `meta` and `result` as arrays, and `start_at`/`finished_at` as datetimes. Pass JSON-compatible values. Do not put Eloquent models, resources, closures or secrets.

For downloadable result, `BaseWorker::markFinished()` can get a string path, but model cast `result => array` and HTTP download logic have their own expectations. Check concrete worker contract and endpoint test; Do not consider any arbitrary path to be automatically accessible.

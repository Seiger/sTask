# Own worker

The easiest way is to follow `BaseWorker`. The class then gets task creation, schedule settings, action dispatch, progress, and finalization.

## Full Example

```php
<?php

namespace EvolutionCMS\Custom\Workers;

use EvolutionCMS\Models\SiteContent;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Workers\BaseWorker;
use Throwable;

final class DocumentAuditWorker extends BaseWorker
{
    public function identifier(): string
    {
        return 'document_audit';
    }

    public function scope(): string
    {
        return 'custom';
    }

    public function icon(): string
    {
        return '<i data-lucide="database-search"></i>';
    }

    public function title(): string
    {
        return 'Document audit';
    }

    public function description(): string
    {
        return 'Counts published and deleted documents in batches.';
    }

    public function taskMake(sTaskModel $task, array $options = []): void
    {
        $batchSize = max(1, min(500, (int)($options['batch_size'] ?? 100)));
        $total = SiteContent::query()->count();
        $processed = 0;
        $published = 0;
        $deleted = 0;
        $startedAt = microtime(true);

        try {
            SiteContent::query()
                ->select(['id', 'published', 'deleted'])
                ->orderBy('id')
                ->chunkById($batchSize, function ($documents) use (
                    $task,
                    $total,
                    $startedAt,
                    &$processed,
                    &$published,
                    &$deleted,
                ): void {
                    foreach ($documents as $document) {
                        $processed++;
                        $published += (int)$document->published;
                        $deleted += (int)$document->deleted;
                    }

                    $progress = $total > 0 ? (int)floor($processed * 100 / $total) : 100;
                    $etaSeconds = $processed > 0
                        ? (int)round((microtime(true) - $startedAt) / $processed * ($total - $processed))
                        : 0;

                    $this->pushProgress($task, [
                        'status' => 'running',
                        'progress' => $progress,
                        'processed' => $processed,
                        'total' => $total,
                        'eta' => niceEta((float)$etaSeconds),
                        'message' => "Processed **{$processed}** of **{$total}**",
                    ]);
                });

            $task->update(['progress' => 100]);
            $this->markFinished($task, null, "Audit completed: {$processed} documents");
            $task->update(['result' => [
                'processed' => $processed,
                'published' => $published,
                'deleted' => $deleted,
            ]]);
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($task, 'Audit failed: ' . $exception->getMessage());
        }
    }
}
```

The example has no application dependencies: it reads the standard Evolution CMS model and does not modify documents. The current `markFinished()` always writes its nullable string argument to `result`, while the model casts the field as array. Therefore, the structured result example writes separately **after** `markFinished()`; This is the real order that prevents the helper from erasing the array value. The basic signature of the action is strictly real: `taskMake(sTaskModel $task, array $options = []): void`.

## Required methods

`TaskInterface` requires:

- `identifier(): string`;
- `scope(): string`;
- `icon(): string`;
- `title(): string`;
- `description(): string`;
- `renderWidget(): string`;
- `settings(): array`.

`BaseWorker` is already implementing `renderWidget()` and `settings()`. Concrete class adds metadata and action methods.

## Naming actions

`invokeAction()` casts the action to lowercase, replaces `-`/`_` with words, and adds a prefix `task`:

| Action | Method |
| --- | --- |
| `make` | `taskMake` |
| `sync_stock` | `taskSyncStock` |
| `import-csv` | `taskImportCsv` |

If the method is missing, a `BadMethodCallException` is thrown and the CLI worker marks task failed.

## Registration

1. Add class to PSR-4 namespace package/project.
2. Update the Composer classmap.
3. Launch discovery.
4. Activate the worker.

```bash
cd core
composer dump-autoload
php artisan package:discover
```

Or programmatically:

```php
use Seiger\sTask\Facades\sTask;

sTask::registerWorker(DocumentAuditWorker::class);
sTask::activateWorker('document_audit');
```

The identifier must be stable and unique. Rescan can change the identifier persisted record, but existing `s_tasks.identifier` does not automatically migrate.

## Creating a task from a worker

```php
$task = $worker->createTask('make', [
    'batch_size' => 100,
    'manual' => true,
]);
```

If options are not passed, `createTask()` takes request `options` and direct `filename`. For CLI/scheduled code, always pass options explicitly.

## Progress

```php
$this->pushProgress($task, [
    'status' => 'running',
    'progress' => 42,
    'processed' => 420,
    'total' => 1000,
    'eta' => niceEta(37),
    'message' => 'Processing **batch 5**',
]);
```

This writes file log, but does not update the `s_tasks.progress` field. If persisted progress is required for final tables/recovery, update the model on checkpoints, not on every item.

Message must be one-line; `TaskProgress` replaces line breaks with `<br>` and pipe `|` with `¦`.

## Completions and errors

```php
$this->markFinished($task, null, 'Done');
$task->update(['result' => ['count' => 420]]);
```

```php
$this->markFailed($task, 'External API returned 503');
```

If action throws an exception, `TaskWorker` will also set failed and write filename/line/error to the progress log. Catch exceptions only when you can add context or cleanup; otherwise, let the runner centrally commit failure.

## Retry strategy

The package counts attempts, but does not requeue failed rows automatically. For retry:

- make the business operation idempotent;
- Identify retryable error types;
- create a new task or deliberately return a failed record in queued;
- apply exponential backoff after `start_at`;
- Do not repeat validation/authentication errors.

## Worker settings

```php
$timeout = (int)$this->getConfig('http.timeout', 10);
$this->setConfig('http.timeout', 20);
$this->updateConfig(['endpoint' => 'https://example.test']);
```

`updateSettings()` makes shallow `array_merge`; nested structures can be replaced in their entirety when updated.

## Custom widget

Override `renderWidget()` only when the default EvoUI task runner is insufficient. Return rendered Blade view, escape user data and use manager routes with CSRF. Do not embed secrets in descriptor/HTML.

## Supervisor extension

If the worker owns a detached daemon, add `SupervisorWorkerInterface` but do not remove the regular `TaskInterface`. Full contract: [Process Supervisor](../02-concepts/supervisor.md).

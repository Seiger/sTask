# Власний воркер

Найпростіший шлях — наслідувати `BaseWorker`. Тоді class отримує task creation, schedule settings, action dispatch, progress і finalization.

## Повний приклад

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
        return 'Аудит документів';
    }

    public function description(): string
    {
        return 'Підраховує опубліковані й видалені документи пакетами.';
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
                        'message' => "Перевірено **{$processed}** із **{$total}**",
                    ]);
                });

            $task->update(['progress' => 100]);
            $this->markFinished($task, null, "Аудит завершено: {$processed} документів");
            $task->update(['result' => [
                'processed' => $processed,
                'published' => $published,
                'deleted' => $deleted,
            ]]);
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($task, 'Не вдалося виконати аудит: ' . $exception->getMessage());
        }
    }
}
```

Приклад не має прикладних залежностей: він читає стандартну модель Evolution CMS і не змінює документи. Поточний `markFinished()` завжди записує свій nullable string argument у `result`, тоді як модель cast-ить поле як array. Тому структурований result приклад записує окремо **після** `markFinished()`; це реальний порядок, який не дає helper-у затерти array значення. Основна сигнатура action строго реальна: `taskMake(sTaskModel $task, array $options = []): void`.

## Обов’язкові methods

`TaskInterface` вимагає:

- `identifier(): string`;
- `scope(): string`;
- `icon(): string`;
- `title(): string`;
- `description(): string`;
- `renderWidget(): string`;
- `settings(): array`.

`BaseWorker` уже реалізує `renderWidget()` і `settings()`. Concrete class додає metadata та action methods.

## Іменування actions

`invokeAction()` приводить action до lowercase, замінює `-`/`_` на words і додає prefix `task`:

| Action | Method |
| --- | --- |
| `make` | `taskMake` |
| `sync_stock` | `taskSyncStock` |
| `import-csv` | `taskImportCsv` |

Якщо method відсутній, кидається `BadMethodCallException` і CLI worker маркує task failed.

## Реєстрація

1. Додайте class у PSR-4 namespace package/project.
2. Оновіть Composer classmap.
3. Запустіть discovery.
4. Активуйте worker.

```bash
cd core
composer dump-autoload
php artisan package:discover
```

Або програмно:

```php
use Seiger\sTask\Facades\sTask;

sTask::registerWorker(DocumentAuditWorker::class);
sTask::activateWorker('document_audit');
```

Identifier повинен бути стабільним і унікальним. Rescan може змінити identifier persisted record, але existing `s_tasks.identifier` автоматично не мігрує.

## Створення task з воркера

```php
$task = $worker->createTask('make', [
    'batch_size' => 100,
    'manual' => true,
]);
```

Якщо options не передані, `createTask()` бере request `options` і direct `filename`. Для CLI/scheduled code завжди передавайте options явно.

## Progress

```php
$this->pushProgress($task, [
    'status' => 'running',
    'progress' => 42,
    'processed' => 420,
    'total' => 1000,
    'eta' => niceEta(37),
    'message' => 'Обробляю **пакет 5**',
]);
```

Це пише file log, але не оновлює поле `s_tasks.progress`. Якщо persisted progress потрібний для final tables/recovery, оновлюйте модель на контрольних checkpoints, не на кожному item.

Message повинен бути однорядковим; `TaskProgress` замінює line breaks на `<br>` і pipe `|` на `¦`.

## Завершення й помилки

```php
$this->markFinished($task, null, 'Готово');
$task->update(['result' => ['count' => 420]]);
```

```php
$this->markFailed($task, 'Зовнішній API повернув 503');
```

Якщо action кидає exception, `TaskWorker` також поставить failed і запише filename/line/error у progress log. Перехоплюйте exception лише коли можете додати context або cleanup; інакше дайте runner-у централізовано зафіксувати failure.

## Retry strategy

Пакет рахує attempts, але не requeue-ить failed rows автоматично. Для retry:

- робіть business operation ідемпотентною;
- визначте retryable error types;
- створюйте новий task або свідомо повертайте failed record у queued;
- застосуйте exponential backoff через `start_at`;
- не повторюйте validation/authentication errors.

## Worker settings

```php
$timeout = (int)$this->getConfig('http.timeout', 10);
$this->setConfig('http.timeout', 20);
$this->updateConfig(['endpoint' => 'https://example.test']);
```

`updateSettings()` робить shallow `array_merge`; nested structures при update можуть бути замінені цілком.

## Custom widget

Override `renderWidget()` лише коли default EvoUI task runner недостатній. Поверніть rendered Blade view, escape-те user data і використовуйте manager routes з CSRF. Не вбудовуйте secrets у descriptor/HTML.

## Supervisor extension

Якщо worker володіє detached daemon, додайте `SupervisorWorkerInterface`, але не прибирайте звичайний `TaskInterface`. Повний contract: [Supervisor-процеси](../02-concepts/supervisor.md).

# Собственный работник

Самый простой способ — следовать `BaseWorker`. Затем класс получает создание задач, настройки расписания, распределение действий, прогресс и финализацию.

## Полный пример

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
        return 'Аудит документов';
    }

    public function description(): string
    {
        return 'Подсчитывает опубликованные и удалённые документы пакетами.';
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
                        'message' => "Проверено **{$processed}** из **{$total}**",
                    ]);
                });

            $task->update(['progress' => 100]);
            $this->markFinished($task, null, "Аудит завершён: {$processed} документов");
            $task->update(['result' => [
                'processed' => $processed,
                'published' => $published,
                'deleted' => $deleted,
            ]]);
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($task, 'Не удалось выполнить аудит: ' . $exception->getMessage());
        }
    }
}
```

В примере нет зависимостей от приложений: он читает стандартную модель Evolution CMS и не изменяет документы. Текущий `markFinished()` всегда записывает аргумент своей нулевой строки в `result`, в то время как модель откастывает поле как массивы. Таким образом, структурированный пример результата записывается отдельно **после** `markFinished()`; Это реальный порядок, который мешает помощнику стереть значение массива. Основная подпись действия строго вещественная: `taskMake(sTaskModel $task, array $options = []): void`.

## Обязательные методы

`TaskInterface` требует:

- `identifier(): string`;
- `scope(): string`;
- `icon(): string`;
- `title(): string`;
- `description(): string`;
- `renderWidget(): string`;
- `settings(): array`.

`BaseWorker` уже внедряет `renderWidget()` и `settings()`. Класс Concrete добавляет метаданные и методы действий.

## Действия по именованию

`invokeAction()` накладывает действие в строчную букву, заменяет `-`/`_` словами и добавляет префикс `task`:

| Экшн | Метод |
| --- | --- |
| `make` | `taskMake` |
| `sync_stock` | `taskSyncStock` |
| `import-csv` | `taskImportCsv` |

Если метод отсутствует, `BadMethodCallException` бросается, и CLI-работник отмечает задачу невыполненной.

## Регистрация

1. Добавить класс в пакет/проект пространства имён PSR-4.
2. Обновить классовую карту Composer.
3. Открытие запуска.
4. Активировать работника.

```bash
cd core
composer dump-autoload
php artisan package:discover
```

Или программно:

```php
use Seiger\sTask\Facades\sTask;

sTask::registerWorker(DocumentAuditWorker::class);
sTask::activateWorker('document_audit');
```

Идентификатор должен быть стабильным и уникальным. Повторное сканирование может изменить запись сохранения идентификатора, но существующая `s_tasks.identifier` не мигрирует автоматически.

## Создание задачи от работника

```php
$task = $worker->createTask('make', [
    'batch_size' => 100,
    'manual' => true,
]);
```

Если варианты не приняты, `createTask()` принимает запросы `options` и прямые `filename`. Для CLI/запланированного кода всегда чётко указывайте опции передачи.

## Прогресс

```php
$this->pushProgress($task, [
    'status' => 'running',
    'progress' => 42,
    'processed' => 420,
    'total' => 1000,
    'eta' => niceEta(37),
    'message' => 'Обрабатываю **пакет 5**',
]);
```

Он записывает журнал файлов, но не обновляет поле `s_tasks.progress`. Если для финальных таблиц/восстановления требуется сохранение прогресса, обновляйте модель на контрольных точках, а не по каждому элементу.

Сообщение должно быть однострочным; `TaskProgress` заменяет тормозы на `<br>` и трубные `|` на `¦`.

## Завершения и ошибки

```php
$this->markFinished($task, null, 'Готово');
$task->update(['result' => ['count' => 420]]);
```

```php
$this->markFailed($task, 'Внешний API вернул 503');
```

Если действие выдает исключение, `TaskWorker` также установит неудачу и запишет имя файла/строку/ошибку в журнал прогресса. Ловите исключения только тогда, когда можно добавить контекст или очистить; В противном случае пусть бегущий централизованно совершит провал.

## Стратегия повторения

Пакет считает попытки, но не возвращает неудачные строки автоматически. Для повторной попытки:

- сделать бизнес-операцию идемпотентной;
- Определить повторяемые типы ошибок;
- создать новую задачу или намеренно вернуть неудачную запись в очередь;
- применить экспоненциальное отступление после `start_at`;
- Не повторяйте ошибки валидации/аутентификации.

## Рабочие настройки

```php
$timeout = (int)$this->getConfig('http.timeout', 10);
$this->setConfig('http.timeout', 20);
$this->updateConfig(['endpoint' => 'https://example.test']);
```

`updateSettings()` делает неглубокий `array_merge`; вложенные структуры могут быть полностью заменены при обновлении.

## Кастомный виджет

Оверрайд `renderWidget()` только если стандартный EvoUI задачный раннер недостаточен. Вернуть отрисованный Blade-вид, сбежать пользовательские данные и использовать маршруты менеджера с помощью CSRF. Не встраивайте секреты в descriptor/HTML.

## Продление должности супервайзера

Если у работника есть отделённый демон, добавьте `SupervisorWorkerInterface`, но не убирайте обычный `TaskInterface`. Полный контракт: [Руководитель процессов](../02-concepts/supervisor.md).

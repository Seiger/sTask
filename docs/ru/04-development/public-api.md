# PHP API и разработка воркеров

## Создание задания

Фасад `Seiger\sTask\Facades\sTask` предоставляет фактическую сигнатуру:

```php
public function create(
    string $identifier,
    string $action,
    array $data = [],
    ?int $startAt = null,
    int $priority = 0,
    ?int $userId = null,
): sTaskModel
```

Пример:

```php
use Seiger\sTask\Facades\sTask;

$task = sTask::create(
    identifier: 'catalog_sync',
    action: 'make',
    data: ['batch_size' => 100],
    userId: evo()->getLoginUserID(),
);
```

Метод нормализует `meta` и возвращает активный duplicate для одинаковых identifier, action и metadata.

## Собственный воркер

Наследуйте `Seiger\sTask\Workers\BaseWorker` и реализуйте identity methods. Action `make` соответствует `taskMake()`:

```php
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Workers\BaseWorker;

final class CatalogWorker extends BaseWorker
{
    public function identifier(): string { return 'catalog_sync'; }
    public function scope(): string { return 'custom'; }
    public function icon(): string { return '<i data-lucide="refresh-cw"></i>'; }
    public function title(): string { return 'Синхронизация каталога'; }
    public function description(): string { return 'Обновляет каталог пакетами.'; }

    public function taskMake(sTaskModel $task, array $options = []): void
    {
        $this->pushProgress($task, [
            'status' => 'running',
            'progress' => 50,
            'processed' => 50,
            'total' => 100,
            'message' => 'Обработана половина записей',
        ]);

        $task->update(['result' => ['processed' => 100]]);
        $this->markFinished($task, null, 'Готово');
    }
}
```

Не записывайте progress после каждого элемента: используйте контрольные точки. Exceptions можно не перехватывать, если не требуется прикладной cleanup — runner централизованно зафиксирует failed state.

## Полезные методы

```php
sTask::execute($task);
sTask::getStats();
sTask::getWorkers();
sTask::getWorker('catalog_sync');
sTask::cleanOldTasks(30);
```

`cleanOldTasks()` удаляет только успешно завершённые задания старше заданного срока.

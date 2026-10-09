# Фасад и PHP API

Канонический класс службы — `Seiger\sTask\sTask`; Фасад — `Seiger\sTask\Facades\sTask`. Псевдоним composer тоже `sTask` логируется, но явный импорт лучше читается и удобнее для статического анализа.

## Heartbeat runner-а

`heartbeat(int $ttl = 3610): bool` проверяет запуск `stask:worker` за последние `$ttl` секунд включительно. Отсутствующий, недоступный, просроченный marker или timestamp из будущего даёт `false`. Отрицательный `$ttl` также даёт `false`.

`heartbeatStatus(int $ttl = 3610): array` возвращает `available` (bool), `state` (`available`, `expired`, `missing`, `error`), `last_seen_at` (Unix timestamp или null) и `age_seconds` (int или null). Оба метода только читают состояние, не создавая и не обновляя файл.

```php
use Seiger\sTask\Facades\sTask;

$available = sTask::heartbeat();
$recent = sTask::heartbeat(180);
$status = sTask::heartbeatStatus();
```

В начале каждого запуска runner вызывает `touch(storage_path('logs/sTask.heartbeat'))` до расписания и очереди, даже без заданий. Каталог `core/storage/logs` должен существовать: CLI необходима запись, вебпроцессу — доступ к атрибутам файла. Ошибка записи логируется, обработка продолжается. Публикация пакета не создаёт marker. Используйте общий storage между релизами.

Heartbeat подтверждает недавний запуск, а не успешное выполнение или существование процесса сейчас. Типовой порог допускает задержку обнаружения остановки до 3610 секунд. Внешний lock, препятствующий запуску команды, не обновляет marker.

## Создание задачи

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

Метод нормализует ассоциативную мету с помощью рекурсивной сортировки и возвращает активный дубликат, если идентификатор/действие/мета уже совпадают. Новый рекорд: в очереди, прогресс 0, попытки 0, max_attempts 3.

Приоритет существует в PHP/схеме для совместимости и порядка очереди в `getPendingTasks()`, но не отображается в текущих столбцах/фильтрах таблицы менеджеров.

## Выполняя одно задание

```php
public function execute(sTaskModel $task): bool
```

Метод записывает стартовые метрики, устанавливает запуск, разрешает рабочий через `WorkerService`, вызывает действие и завершает задачу, если работник этого не сделал. Исключение фиксирует задачу в неудачную и возвращает `false`.

Вызов `execute()` только в контролируемом контексте CLI/очереди. Менеджер flow и планировщик используют `stask:worker`.

## Очередь

```php
public function getPendingTasks(int $limit = 10): Collection
public function processPendingTasks(?int $batchSize = null): int
```

`getPendingTasks()` читает статус `10`, сортирует приоритеты высокого → нормального → низкого, затем `created_at`. В отличие от CLI `TaskWorker`, этот метод не фильтрует будущие `start_at`; Не используйте его для семантики планировщика без дополнительного условия.

`processPendingTasks()` последовательно вызывает `execute()` и возвращает количество успешных задач.

## Статистика и метрики

```php
public function getStats(): array
public function getPerformanceMetrics(int $hours = 24): array
public function getWorkerStats(?string $identifier = null, int $hours = 24): array
public function getPerformanceAlerts(): array
```

`getStats()` подсчёты возвратов, ожидающих/запущенных/выполненных/неудачных/общего, и работников. API производительности, частично заполняющий: агрегация длительности/памяти из записей задач пока не реализована.

## Реестр работников

```php
public function discoverWorkers(): array
public function registerWorker(string $className): ?sWorker
public function cleanOrphanedWorkers(): int
public function getWorkers(bool $activeOnly = false): Collection
public function getWorker(string $identifier): ?sWorker
public function activateWorker(string $identifier): bool
public function deactivateWorker(string $identifier): bool
```

Discovery работает на классовой карте Composer. После добавления класса:

```bash
composer dump-autoload
php artisan package:discover
```

или нажмите обновить реестр в интерфейсе. Помните: новые записи создаются неактивными.

## Тайник рабочих

```php
public function getCacheStats(): array
public function clearWorkerCache(?string $identifier = null): void
```

`WorkerService` содержит встроенный кэш и записи кэша Laravel с префиксом `stask_worker_`. Очищайте конкретный идентификатор после смены настроек/класса или всего кэша после обновления/развертывания реестра.

## История очищения

```php
public function cleanOldTasks(int $days = 30): int
```

Удаляет только выполненные задачи (`status = 80`) с `finished_at` самым высоким порогом. Файлы failed, queue, running, supervisor state и progress не очищаются этим методом.

## sTaskModel

Полезные масштабы и методы:

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

`markAsRunning()` перезаписывает `start_at = now()` и увеличивает количество попыток. `markAsFinished()` ставлю прогресс 100. `markAsFailed()` не ставьте прогресс 100.

## Мета и результат

Модель откастывает `meta` и `result` как массивы, а `start_at`/`finished_at` — как время даты. Передайте значения, совместимые с JSON. Не добавляйте Eloquent модели, ресурсы, закрытия или секреты.

Для скачиваемого результата `BaseWorker::markFinished()` могу получить путь к строкам, но у `result => array` моделей и HTTP-логики загрузки есть свои ожидания. Проверьте контракт бетонных рабочих и тест на конечную точку; Не считайте любой произвольный путь автоматически доступным.

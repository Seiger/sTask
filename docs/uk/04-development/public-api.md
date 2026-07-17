# Фасад і PHP API

Канонічний service class — `Seiger\sTask\sTask`; facade — `Seiger\sTask\Facades\sTask`. Composer alias `sTask` також реєструється, але explicit import краще читається і зручніший для static analysis.

## Створення task

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

Метод нормалізує associative meta рекурсивним сортуванням і повертає active duplicate, якщо identifier/action/meta уже збігаються. Новий record: queued, progress 0, attempts 0, max_attempts 3.

Priority існує в PHP/schema для compatibility та queue ordering у `getPendingTasks()`, але не показується в актуальних manager table columns/filters.

## Виконання одного task

```php
public function execute(sTaskModel $task): bool
```

Метод записує start metrics, ставить running, resolve-ить worker через `WorkerService`, викликає action і фіналізує task, якщо worker цього не зробив. Exception переводить task у failed і повертає `false`.

Викликайте `execute()` лише в контрольованому CLI/queue context. Manager flow і scheduler використовують `stask:worker`.

## Черга

```php
public function getPendingTasks(int $limit = 10): Collection
public function processPendingTasks(?int $batchSize = null): int
```

`getPendingTasks()` читає status `10`, сортує priority high → normal → low, потім `created_at`. На відміну від CLI `TaskWorker`, цей метод не фільтрує future `start_at`; не використовуйте його для scheduler semantics без додаткової умови.

`processPendingTasks()` послідовно викликає `execute()` і повертає кількість successful tasks.

## Статистика і metrics

```php
public function getStats(): array
public function getPerformanceMetrics(int $hours = 24): array
public function getWorkerStats(?string $identifier = null, int $hours = 24): array
public function getPerformanceAlerts(): array
```

`getStats()` повертає counts pending/running/completed/failed/total і workers. Performance API частково placeholder: duration/memory aggregation з task records ще не реалізована.

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

Discovery працює по Composer classmap. Після додавання класу:

```bash
composer dump-autoload
php artisan package:discover
```

або натисніть refresh registry в UI. Пам’ятайте: нові records створюються inactive.

## Worker cache

```php
public function getCacheStats(): array
public function clearWorkerCache(?string $identifier = null): void
```

`WorkerService` має in-memory cache і Laravel cache entries з prefix `stask_worker_`. Очищайте конкретний identifier після зміни settings/class або весь cache після registry refresh/deploy.

## Очищення history

```php
public function cleanOldTasks(int $days = 30): int
```

Видаляє тільки finished tasks (`status = 80`) зі `finished_at` старшим cutoff. Failed, queued, running, supervisor state і progress files цим методом не очищаються.

## sTaskModel

Корисні scopes і methods:

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

`markAsRunning()` перезаписує `start_at = now()` та збільшує attempts. `markAsFinished()` ставить progress 100. `markAsFailed()` не ставить progress 100.

## Meta і result

Модель cast-ить `meta` і `result` як arrays, а `start_at`/`finished_at` як datetimes. Передавайте JSON-compatible values. Не кладіть Eloquent models, resources, closures або secrets.

Для downloadable result `BaseWorker::markFinished()` може отримати string path, але model cast `result => array` і HTTP download logic мають власні очікування. Перевіряйте concrete worker contract і endpoint тестом; не вважайте будь-який довільний path автоматично доступним.

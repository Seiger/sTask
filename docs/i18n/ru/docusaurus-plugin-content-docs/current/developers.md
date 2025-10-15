---
title: Руководство разработчика
sidebar_label: Руководство разработчика
sidebar_position: 3
---

# Руководство разработчика

## Создание пользовательских воркеров

Для создания пользовательского воркера, расширьте класс `BaseWorker` который предоставляет всю общую функциональность:

```php
<?php namespace YourNamespace\Workers;

use Seiger\sTask\Workers\BaseWorker;
use Seiger\sTask\Models\sTaskModel;

class ProductWorker extends BaseWorker
{
    /**
     * Уникальный идентификатор для этого воркера
     */
    public function identifier(): string
    {
        return 'product';
    }
    
    /**
     * Scope модуля/пакета (для фильтрации в админе)
     */
    public function scope(): string
    {
        return 'scommerce';
    }
    
    /**
     * Иконка для админ интерфейса
     */
    public function icon(): string
    {
        return '<i class="fa fa-cube"></i>';
    }
    
    /**
     * Краткое понятное название
     */
    public function title(): string
    {
        return 'Управление товарами';
    }
    
    /**
     * Детальное описание
     */
    public function description(): string
    {
        return 'Импорт и экспорт товаров из/в CSV файлы';
    }
    
    /**
     * Рендер виджета для админ интерфейса
     */
    public function renderWidget(): string
    {
        return view('your-package::widgets.product-worker', [
            'worker' => $this
        ])->render();
    }
    
    /**
     * Настройки воркера (опционально)
     */
    public function settings(): array
    {
        return [
            'batch_size' => 100,
            'timeout' => 3600,
            'retry_on_fail' => true,
        ];
    }
    
    /**
     * Действие: Импорт товаров из CSV
     */
    public function taskImport(sTaskModel $task, array $options = []): void
    {
        try {
            // Обновить статус задачи
            $task->update(['status' => 20, 'message' => 'Начало импорта...']);
            
            // Получить файл из опций
            $file = $options['file'] ?? null;
            if (!$file || !file_exists($file)) {
                throw new \Exception('Файл для импорта не найден');
            }
            
            // Прочитать CSV
            $handle = fopen($file, 'r');
            $header = fgetcsv($handle);
            
            // Посчитать общее количество строк
            $total = 0;
            while (fgets($handle)) $total++;
            rewind($handle);
            fgetcsv($handle); // Пропустить заголовок
            
            $processed = 0;
            $startTime = microtime(true);
            
            // Обработать каждую строку
            while (($row = fgetcsv($handle)) !== false) {
                $data = array_combine($header, $row);
                
                // Логика импорта товара
                $this->importProduct($data);
                
                $processed++;
                
                // Обновлять прогресс каждые 10 элементов
                if ($processed % 10 === 0 || $processed === $total) {
                    $progress = (int)(($processed / $total) * 100);
                    
                    // Рассчитать ETA
                    $elapsed = microtime(true) - $startTime;
                    $rate = $processed / $elapsed;
                    $remaining = $total - $processed;
                    $etaSeconds = $remaining > 0 ? $remaining / $rate : 0;
                    
                    $this->pushProgress($task, [
                        'progress' => $progress,
                        'processed' => $processed,
                        'total' => $total,
                        'eta' => niceEta($etaSeconds),
                        'message' => "Импортировано {$processed} из {$total} товаров"
                    ]);
                }
            }
            
            fclose($handle);
            
            // Отметить как завершенное
            $this->markFinished(
                $task, 
                null, 
                "Успешно импортировано {$processed} товаров за " . round(microtime(true) - $startTime, 2) . "с"
            );
            
        } catch (\Exception $e) {
            $this->markFailed($task, $e->getMessage());
        }
    }
    
    /**
     * Действие: Экспорт товаров в CSV
     */
    public function taskExport(sTaskModel $task, array $options = []): void
    {
        try {
            $task->update(['status' => 20, 'message' => 'Начало экспорта...']);
            
            // Подготовить файл экспорта
            $filename = 'products_' . date('Y-m-d_His') . '.csv';
            $filepath = storage_path('stask/uploads/' . $filename);
            
            if (!is_dir(dirname($filepath))) {
                mkdir(dirname($filepath), 0755, true);
            }
            
            $handle = fopen($filepath, 'w');
            
            // Записать заголовок
            fputcsv($handle, ['ID', 'SKU', 'Название', 'Цена', 'Остаток']);
            
            // Получить товары
            $products = \DB::table('products')->get();
            $total = count($products);
            $processed = 0;
            
            foreach ($products as $i => $product) {
                fputcsv($handle, [
                    $product->id,
                    $product->sku,
                    $product->name,
                    $product->price,
                    $product->stock,
                ]);
                
                $processed++;
                
                if ($processed % 100 === 0 || $processed === $total) {
                    $progress = (int)(($processed / $total) * 100);
                    
                    $this->pushProgress($task, [
                        'progress' => $progress,
                        'processed' => $processed,
                        'total' => $total,
                        'message' => "Экспортировано {$processed} из {$total} товаров"
                    ]);
                }
            }
            
            fclose($handle);
            
            $this->markFinished(
                $task,
                $filepath,
                "Экспортировано {$total} товаров в {$filename}"
            );
            
        } catch (\Exception $e) {
            $this->markFailed($task, $e->getMessage());
        }
    }
    
    /**
     * Действие: Синхронизация остатков
     */
    public function taskSyncStock(sTaskModel $task, array $options = []): void
    {
        try {
            $source = $options['source'] ?? 'api';
            
            $task->update(['status' => 20, 'message' => "Синхронизация остатков с {$source}..."]);
            
            // Ваша логика синхронизации здесь
            $items = $this->fetchStockFromSource($source);
            $total = count($items);
            
            foreach ($items as $i => $item) {
                $this->updateProductStock($item['sku'], $item['quantity']);
                
                if (($i + 1) % 50 === 0) {
                    $this->pushProgress($task, [
                        'progress' => (int)((($i + 1) / $total) * 100),
                        'processed' => $i + 1,
                        'total' => $total,
                    ]);
                }
            }
            
            $this->markFinished($task, null, "Синхронизированы остатки для {$total} товаров");
            
        } catch (\Exception $e) {
            $this->markFailed($task, $e->getMessage());
        }
    }
    
    // Вспомогательные методы
    private function importProduct(array $data): void
    {
        // Ваша логика импорта
    }
    
    private function fetchStockFromSource(string $source): array
    {
        // Ваша логика получения из API/источника
        return [];
    }
    
    private function updateProductStock(string $sku, int $quantity): void
    {
        // Логика обновления
    }
}
```

## Автоматическое обнаружение воркеров

Воркеры автоматически обнаруживаются если они:
1. Реализуют интерфейс `TaskInterface`
2. Не являются абстрактными классами
3. Могут быть инстанцированы
4. Находятся в пространстве имен вашего пакета

Процесс обнаружения сканирует все установленные Composer пакеты и автоматически регистрирует воркеры.

## API управления задачами

### Создание задач

```php
use Seiger\sTask\Facades\sTask;

// Базовое создание задачи
$task = sTask::create(
    identifier: 'product',
    action: 'import',
    data: ['file' => '/path/to/products.csv'],
    priority: 'high',
    userId: evo()->getLoginUserID()
);

// Создать с собственным приоритетом
$task = sTask::create(
    identifier: 'product',
    action: 'export',
    data: ['format' => 'csv', 'filters' => ['active' => true]],
    priority: 'normal', // 'low', 'normal', 'high'
    userId: evo()->getLoginUserID()
);

// Программное создание задачи из воркера
$worker = new ProductWorker();
$task = $worker->createTask('import', ['file' => 'products.csv']);
```

### Обработка задач

```php
// Обработать все ожидающие задачи (размер пакета по умолчанию: 10)
$processedCount = sTask::processPendingTasks();

// Обработать с собственным размером пакета
$processedCount = sTask::processPendingTasks(batchSize: 50);

// Получить статистику задач
$stats = sTask::getStats();
/* Возвращает:
[
    'pending' => 5,
    'running' => 2,
    'completed' => 100,
    'failed' => 3,
    'cancelled' => 1,
    'total' => 111,
]
*/

// Получить ожидающие задачи
$pending = sTask::getPendingTasks(limit: 20);

foreach ($pending as $task) {
    echo "Задача #{$task->id}: {$task->identifier} -> {$task->action}\n";
}
```

### Управление воркерами

```php
// Обнаружить новые воркеры
$registered = sTask::discoverWorkers();
echo "Зарегистрировано " . count($registered) . " новых воркеров\n";

// Пересканировать существующие воркеры (обновить их метаданные)
$updated = sTask::rescanWorkers();
echo "Обновлено " . count($updated) . " воркеров\n";

// Очистить orphaned воркеры (классы больше не существуют)
$deleted = sTask::cleanOrphanedWorkers();
echo "Удалено {$deleted} orphaned воркеров\n";

// Получить всех воркеров
$workers = sTask::getWorkers(activeOnly: false);

foreach ($workers as $worker) {
    echo "{$worker->identifier} ({$worker->scope}) - ";
    echo $worker->active ? 'Активный' : 'Неактивный';
    echo "\n";
}

// Получить конкретного воркера
$worker = sTask::getWorker('product');
if ($worker) {
    echo "Название: {$worker->title}\n";
    echo "Описание: {$worker->description}\n";
    echo "Иконка: {$worker->icon}\n";
}

// Активировать/деактивировать воркеров
sTask::activateWorker('product');
sTask::deactivateWorker('old_worker');

// Фильтровать воркеров по scope
$commerceWorkers = \Seiger\sTask\Models\sWorker::byScope('scommerce')->get();
```

### Выполнение задач

```php
// Выполнить конкретную задачу
$task = \Seiger\sTask\Models\sTaskModel::find(1);
$result = sTask::execute($task);

if ($result) {
    echo "Задача завершена успешно\n";
} else {
    echo "Задача неудачна: {$task->message}\n";
}

// Повторить неудачную задачу
if ($task->canRetry()) {
    sTask::retry($task);
}
```

### Операции очистки

```php
// Очистить задачи старше 30 дней
$deletedTasks = sTask::cleanOldTasks(days: 30);
echo "Удалено {$deletedTasks} старых задач\n";

// Очистить логи старше 30 дней
$deletedLogs = sTask::cleanOldLogs(days: 30);
echo "Удалено {$deletedLogs} старых файлов логов\n";

// Собственная очистка
$deleted = \Seiger\sTask\Models\sTaskModel::where('status', 30) // completed
    ->where('finished_at', '<', now()->subDays(7))
    ->delete();
```

## Конвенция именования методов действий

Методы действий должны следовать конвенции `task{Action}`:

| Название действия | Название метода | Пример |
|-------------------|-----------------|--------|
| `import` | `taskImport()` | Импорт товаров |
| `export` | `taskExport()` | Экспорт товаров |
| `sync_stock` | `taskSyncStock()` | Синхронизация остатков |
| `generate_report` | `taskGenerateReport()` | Генерация отчетов |
| `send_emails` | `taskSendEmails()` | Массовая рассылка |

```php
// Примеры преобразования названий действий:
'import' → taskImport()
'export' → taskExport()  
'sync' → taskSync()
'sync_stock' → taskSyncStock()
'send_emails' → taskSendEmails()
'generate_report' → taskGenerateReport()
'cleanup-old-data' → taskCleanupOldData()
```

## Отслеживание прогресса

### Базовые обновления прогресса

```php
public function taskProcess(sTaskModel $task, array $options = []): void
{
    $items = range(1, 1000);
    $total = count($items);
    
    foreach ($items as $i => $item) {
        // Обработать элемент
        sleep(0.01); // Симуляция работы
        
        // Обновить прогресс
        $processed = $i + 1;
        $progress = (int)(($processed / $total) * 100);
        
        $this->pushProgress($task, [
            'progress' => $progress,
            'processed' => $processed,
            'total' => $total,
            'message' => "Обработка элемента {$processed} из {$total}"
        ]);
    }
    
    $this->markFinished($task);
}
```

### Прогресс с расчетом ETA

```php
public function taskLongRunning(sTaskModel $task, array $options = []): void
{
    $total = 10000;
    $startTime = microtime(true);
    
    for ($i = 0; $i < $total; $i++) {
        // Обработать элемент
        $this->processItem($i);
        
        // Обновлять каждые 100 элементов
        if ($i > 0 && $i % 100 === 0) {
            $elapsed = microtime(true) - $startTime;
            $rate = $i / $elapsed; // элементов в секунду
            $remaining = $total - $i;
            $etaSeconds = $remaining / $rate;
            
            $this->pushProgress($task, [
                'progress' => (int)(($i / $total) * 100),
                'processed' => $i,
                'total' => $total,
                'eta' => niceEta($etaSeconds),
                'message' => "Обработка... {$i}/{$total}"
            ]);
        }
    }
    
    $this->markFinished($task, null, "Обработано {$total} элементов");
}
```

### Многоэтапный прогресс

```php
public function taskMultiStage(sTaskModel $task, array $options = []): void
{
    try {
        // Этап 1: Подготовка (0-20%)
        $this->pushProgress($task, [
            'progress' => 5,
            'message' => 'Подготовка данных...'
        ]);
        
        $data = $this->prepareData();
        
        $this->pushProgress($task, [
            'progress' => 20,
            'message' => 'Данные подготовлены'
        ]);
        
        // Этап 2: Обработка (20-80%)
        $total = count($data);
        foreach ($data as $i => $item) {
            $this->processItem($item);
            
            // Прогресс от 20% до 80%
            $stageProgress = ($i + 1) / $total; // 0.0 до 1.0
            $overallProgress = 20 + ($stageProgress * 60); // 20 до 80
            
            if ($i % 10 === 0) {
                $this->pushProgress($task, [
                    'progress' => (int)$overallProgress,
                    'processed' => $i + 1,
                    'total' => $total,
                    'message' => "Обработка: {$i}/{$total}"
                ]);
            }
        }
        
        // Этап 3: Завершение (80-100%)
        $this->pushProgress($task, [
            'progress' => 85,
            'message' => 'Генерация отчета...'
        ]);
        
        $reportPath = $this->generateReport($data);
        
        $this->pushProgress($task, [
            'progress' => 95,
            'message' => 'Сохранение результатов...'
        ]);
        
        $this->saveResults($data);
        
        // Готово
        $this->markFinished($task, $reportPath, 'Все этапы завершены');
        
    } catch (\Exception $e) {
        $this->markFailed($task, $e->getMessage());
    }
}
```

## Логирование

### Автоматическое логирование

sTask автоматически логирует:
- Старт/завершение задачи
- Ошибки задачи
- Обновления прогресса (в файлах прогресса)

Файлы логов хранятся в `storage/stask/{task_id}.log`

### Собственное логирование

```php
use Seiger\sTask\Facades\sTask;

public function taskWithLogging(sTaskModel $task, array $options = []): void
{
    // Info лог
    sTask::log($task, 'info', 'Начало процесса импорта', [
        'file' => $options['file'],
        'user_id' => $task->started_by
    ]);
    
    try {
        foreach ($items as $item) {
            try {
                $this->processItem($item);
            } catch (\Exception $e) {
                // Warning для некритичных ошибок
                sTask::log($task, 'warning', "Пропущен элемент {$item->id}", [
                    'reason' => $e->getMessage(),
                    'item' => $item->toArray()
                ]);
                continue;
            }
        }
        
        // Успех info
        sTask::log($task, 'info', 'Импорт завершен успешно', [
            'total_processed' => count($items)
        ]);
        
        $this->markFinished($task);
        
    } catch (\Exception $e) {
        // Error лог
        sTask::log($task, 'error', 'Импорт неудачен', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        $this->markFailed($task, $e->getMessage());
    }
}
```

### Чтение логов

```php
$task = \Seiger\sTask\Models\sTaskModel::find(1);

// Получить все логи
$logs = $task->getLogs();
foreach ($logs as $log) {
    echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}\n";
    if (!empty($log['context'])) {
        print_r($log['context']);
    }
}

// Получить последние 10 логов
$recentLogs = $task->getLastLogs(10);

// Получить только ошибки
$errorLogs = $task->getErrorLogs();

// Очистить логи задачи
$task->clearLogs();

// Скачать логи
return $task->logger()->downloadLogs($task);
```

## Обработка ошибок

### Базовая обработка ошибок

```php
public function taskSafe(sTaskModel $task, array $options = []): void
{
    try {
        // Ваша логика
        $result = $this->doSomething();
        
        if (!$result) {
            throw new \RuntimeException('Операция неудачна');
        }
        
        $this->markFinished($task);
        
    } catch (\Exception $e) {
        // Логировать детальную ошибку
        sTask::log($task, 'error', $e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        $this->markFailed($task, $e->getMessage());
    }
}
```

### Логика повторных попыток

```php
public function taskWithRetry(sTaskModel $task, array $options = []): void
{
    $maxRetries = 3;
    $currentAttempt = $task->attempts;
    
    try {
        // Попытка операции
        $this->doUnreliableOperation();
        
        $this->markFinished($task);
        
    } catch (\Exception $e) {
        if ($currentAttempt < $maxRetries) {
            // Будет повторная попытка
            sTask::log($task, 'warning', 
                "Попытка {$currentAttempt} неудачна, будет повтор", 
                ['error' => $e->getMessage()]
            );
            
            // sTask автоматически повторит
            throw $e;
        } else {
            // Достигнут максимум попыток
            sTask::log($task, 'error', 
                "Все {$maxRetries} попытки неудачны", 
                ['last_error' => $e->getMessage()]
            );
            
            $this->markFailed($task, "Неудачно после {$maxRetries} попыток: " . $e->getMessage());
        }
    }
}
```

### Валидация перед обработкой

```php
public function taskValidated(sTaskModel $task, array $options = []): void
{
    // Валидировать опции
    $errors = [];
    
    if (empty($options['file'])) {
        $errors[] = 'Путь к файлу обязателен';
    } elseif (!file_exists($options['file'])) {
        $errors[] = 'Файл не существует: ' . $options['file'];
    }
    
    if (empty($options['user_id'])) {
        $errors[] = 'ID пользователя обязателен';
    }
    
    if (!empty($errors)) {
        $this->markFailed($task, 'Валидация неудачна: ' . implode('; ', $errors));
        return;
    }
    
    // Обработать
    try {
        $this->processFile($options['file'], $options['user_id']);
        $this->markFinished($task);
    } catch (\Exception $e) {
        $this->markFailed($task, $e->getMessage());
    }
}
```

## Приоритеты задач

```php
// Высокий приоритет - обрабатывается первым
$urgentTask = sTask::create(
    identifier: 'notification',
    action: 'send_urgent',
    data: ['email' => 'admin@example.com'],
    priority: 'high'
);

// Обычный приоритет - по умолчанию
$normalTask = sTask::create(
    identifier: 'report',
    action: 'generate',
    data: [],
    priority: 'normal'
);

// Низкий приоритет - обрабатывается последним
$backgroundTask = sTask::create(
    identifier: 'cleanup',
    action: 'archive_old_data',
    data: [],
    priority: 'low'
);

// Задачи обрабатываются в порядке приоритета:
// 1. Все задачи с приоритетом 'high'
// 2. Все задачи с приоритетом 'normal'  
// 3. Все задачи с приоритетом 'low'
```

## Статусы задач

```php
use Seiger\sTask\Models\sTaskModel;

// Константы статусов
// 10 - pending (ожидает)
// 20 - running (выполняется)
// 30 - completed (завершено)
// 40 - failed (неудачно)
// 50 - cancelled (отменено)

// Проверить статус задачи
$task = sTaskModel::find(1);

if ($task->isPending()) {
    echo "Задача ожидает обработки\n";
}

if ($task->isRunning()) {
    echo "Задача выполняется сейчас\n";
}

if ($task->isFinished()) {
    echo "Задача завершена (completed, failed или cancelled)\n";
}

// Получить текстовый статус
echo $task->status_text; // 'pending', 'running', 'completed', 'failed', 'cancelled'

// Запросы по статусу
$pendingTasks = sTaskModel::pending()->get();
$runningTasks = sTaskModel::running()->get();
$completedTasks = sTaskModel::completed()->get();
$failedTasks = sTaskModel::failed()->get();

// Получить незавершенные задачи (не finished и не failed)
$incompleteTasks = sTaskModel::incomplete()->get();

// Запросы по идентификатору и действию
$productImports = sTaskModel::byIdentifier('product')
    ->byAction('import')
    ->get();

// Последние неудачные задачи
$recentFailures = sTaskModel::failed()
    ->where('created_at', '>', now()->subDays(7))
    ->orderBy('created_at', 'desc')
    ->get();
```

## Расширенные примеры

### Пакетная обработка с chunking

```php
public function taskBatchProcess(sTaskModel $task, array $options = []): void
{
    $chunkSize = 100;
    $processed = 0;
    
    // Получить общее количество
    $total = \DB::table('products')->count();
    
    \DB::table('products')->orderBy('id')->chunk($chunkSize, function($products) use ($task, &$processed, $total) {
        foreach ($products as $product) {
            $this->processProduct($product);
            $processed++;
        }
        
        // Обновить прогресс после каждого chunk
        $this->pushProgress($task, [
            'progress' => (int)(($processed / $total) * 100),
            'processed' => $processed,
            'total' => $total,
            'message' => "Обработано {$processed}/{$total} товаров"
        ]);
    });
    
    $this->markFinished($task, null, "Обработано {$processed} товаров");
}
```

### Задача загрузки файла

```php
public function taskDownload(sTaskModel $task, array $options = []): void
{
    $url = $options['url'];
    $filename = basename($url);
    $destination = storage_path('downloads/' . $filename);
    
    if (!is_dir(dirname($destination))) {
        mkdir(dirname($destination), 0755, true);
    }
    
    $file = fopen($destination, 'w');
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $file);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    
    // Callback прогресса
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($resource, $downloadSize, $downloaded) use ($task) {
        if ($downloadSize > 0) {
            $progress = (int)(($downloaded / $downloadSize) * 100);
            
            $this->pushProgress($task, [
                'progress' => $progress,
                'processed' => $downloaded,
                'total' => $downloadSize,
                'message' => "Загружено " . $this->formatBytes($downloaded) . " из " . $this->formatBytes($downloadSize)
            ]);
        }
    });
    
    curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    fclose($file);
    
    if ($error) {
        unlink($destination);
        $this->markFailed($task, "Загрузка неудачна: {$error}");
    } else {
        $this->markFinished($task, $destination, "Загружено {$filename}");
    }
}

private function formatBytes(int $bytes): string
{
    $units = ['Б', 'КБ', 'МБ', 'ГБ'];
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
```

### Задача синхронизации с API

```php
public function taskApiSync(sTaskModel $task, array $options = []): void
{
    $apiUrl = $options['api_url'];
    $apiKey = $this->settings()['api_key'];
    
    try {
        // Получить из API
        $this->pushProgress($task, [
            'progress' => 10,
            'message' => 'Получение данных из API...'
        ]);
        
        $response = $this->apiRequest($apiUrl, $apiKey);
        $items = json_decode($response, true);
        
        if (!is_array($items)) {
            throw new \RuntimeException('Невалидный ответ API');
        }
        
        $total = count($items);
        
        // Обработать элементы
        foreach ($items as $i => $item) {
            $this->syncItem($item);
            
            if (($i + 1) % 10 === 0) {
                $progress = 10 + (int)((($i + 1) / $total) * 80); // 10% до 90%
                
                $this->pushProgress($task, [
                    'progress' => $progress,
                    'processed' => $i + 1,
                    'total' => $total,
                    'message' => "Синхронизировано {$i+1}/{$total} элементов"
                ]);
            }
        }
        
        // Финальная очистка
        $this->pushProgress($task, [
            'progress' => 95,
            'message' => 'Очистка...'
        ]);
        
        $this->cleanup();
        
        $this->markFinished($task, null, "Синхронизировано {$total} элементов из API");
        
    } catch (\Exception $e) {
        $this->markFailed($task, $e->getMessage());
    }
}
```

## Настройки воркера

```php
class MyWorker extends BaseWorker
{
    public function settings(): array
    {
        return [
            'api_key' => config('services.myservice.key'),
            'timeout' => 300,
            'batch_size' => 100,
            'retry_attempts' => 3,
        ];
    }
    
    public function taskProcess(sTaskModel $task, array $options = []): void
    {
        $settings = $this->settings();
        $apiKey = $settings['api_key'];
        $timeout = $settings['timeout'];
        
        // Использование настроек
    }
}
```

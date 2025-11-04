---
title: Керівництво розробника
sidebar_label: Керівництво розробника
sidebar_position: 3
---

# Керівництво розробника

## Створення власних воркерів

Для створення власного воркера, розширте клас `BaseWorker` який надає всю спільну функціональність:

```php
<?php namespace YourNamespace\Workers;

use Seiger\sTask\Workers\BaseWorker;
use Seiger\sTask\Models\sTaskModel;

class ProductWorker extends BaseWorker
{
    /**
     * Унікальний ідентифікатор для цього воркера
     */
    public function identifier(): string
    {
        return 'product';
    }
    
    /**
     * Scope модуля/пакету (для фільтрації в адміні)
     */
    public function scope(): string
    {
        return 'scommerce';
    }
    
    /**
     * Іконка для адмін інтерфейсу
     */
    public function icon(): string
    {
        return '<i class="fa fa-cube"></i>';
    }
    
    /**
     * Коротка зрозуміла назва
     */
    public function title(): string
    {
        return 'Управління товарами';
    }
    
    /**
     * Детальний опис
     */
    public function description(): string
    {
        return 'Імпорт та експорт товарів з/до CSV файлів';
    }
    
    /**
     * Рендер віджета для адмін інтерфейсу
     */
    public function renderWidget(): string
    {
        return view('your-package::widgets.product-worker', [
            'worker' => $this
        ])->render();
    }
    
    /**
     * Налаштування воркера (опціонально)
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
     * Дія: Імпорт товарів з CSV
     */
    public function taskImport(sTaskModel $task, array $options = []): void
    {
        try {
            // Оновити статус задачі
            $task->update(['status' => 20, 'message' => 'Початок імпорту...']);
            
            // Отримати файл з опцій
            $file = $options['file'] ?? null;
            if (!$file || !file_exists($file)) {
                throw new \Exception('Файл для імпорту не знайдено');
            }
            
            // Прочитати CSV
            $handle = fopen($file, 'r');
            $header = fgetcsv($handle);
            
            // Порахувати загальну кількість рядків
            $total = 0;
            while (fgets($handle)) $total++;
            rewind($handle);
            fgetcsv($handle); // Пропустити заголовок
            
            $processed = 0;
            $startTime = microtime(true);
            
            // Обробити кожен рядок
            while (($row = fgetcsv($handle)) !== false) {
                $data = array_combine($header, $row);
                
                // Логіка імпорту товару
                $this->importProduct($data);
                
                $processed++;
                
                // Оновлювати прогрес кожні 10 елементів
                if ($processed % 10 === 0 || $processed === $total) {
                    $progress = (int)(($processed / $total) * 100);
                    
                    // Розрахувати ETA
                    $elapsed = microtime(true) - $startTime;
                    $rate = $processed / $elapsed;
                    $remaining = $total - $processed;
                    $etaSeconds = $remaining > 0 ? $remaining / $rate : 0;
                    
                    $this->pushProgress($task, [
                        'progress' => $progress,
                        'processed' => $processed,
                        'total' => $total,
                        'eta' => niceEta($etaSeconds),
                        'message' => "Імпортовано {$processed} з {$total} товарів"
                    ]);
                }
            }
            
            fclose($handle);
            
            // Позначити як завершене
            $this->markFinished(
                $task, 
                null, 
                "Успішно імпортовано {$processed} товарів за " . round(microtime(true) - $startTime, 2) . "с"
            );
            
        } catch (\Exception $e) {
            $this->markFailed($task, $e->getMessage());
        }
    }
    
    /**
     * Дія: Експорт товарів до CSV
     */
    public function taskExport(sTaskModel $task, array $options = []): void
    {
        try {
            $task->update(['status' => 20, 'message' => 'Початок експорту...']);
            
            // Підготувати файл експорту
            $filename = 'products_' . date('Y-m-d_His') . '.csv';
            $filepath = storage_path('stask/uploads/' . $filename);
            
            if (!is_dir(dirname($filepath))) {
                mkdir(dirname($filepath), 0755, true);
            }
            
            $handle = fopen($filepath, 'w');
            
            // Записати заголовок
            fputcsv($handle, ['ID', 'SKU', 'Назва', 'Ціна', 'Залишок']);
            
            // Отримати товари
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
                        'message' => "Експортовано {$processed} з {$total} товарів"
                    ]);
                }
            }
            
            fclose($handle);
            
            $this->markFinished(
                $task,
                $filepath,
                "Експортовано {$total} товарів до {$filename}"
            );
            
        } catch (\Exception $e) {
            $this->markFailed($task, $e->getMessage());
        }
    }
    
    /**
     * Дія: Синхронізація залишків
     */
    public function taskSyncStock(sTaskModel $task, array $options = []): void
    {
        try {
            $source = $options['source'] ?? 'api';
            
            $task->update(['status' => 20, 'message' => "Синхронізація залишків з {$source}..."]);
            
            // Ваша логіка синхронізації тут
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
            
            $this->markFinished($task, null, "Синхронізовано залишки для {$total} товарів");
            
        } catch (\Exception $e) {
            $this->markFailed($task, $e->getMessage());
        }
    }
    
    // Допоміжні методи
    private function importProduct(array $data): void
    {
        // Ваша логіка імпорту
    }
    
    private function fetchStockFromSource(string $source): array
    {
        // Ваша логіка отримання з API/джерела
        return [];
    }
    
    private function updateProductStock(string $sku, int $quantity): void
    {
        // Логіка оновлення
    }
}
```

## Автоматичне виявлення воркерів

Воркери автоматично виявляються якщо вони:
1. Реалізують інтерфейс `TaskInterface`
2. Не є абстрактними класами
3. Можуть бути інстанційовані
4. Знаходяться в просторі імен вашого пакету

Процес виявлення сканує всі встановлені Composer пакети та автоматично реєструє воркерів.

## Конфігурація воркерів

### Кастомні налаштування

Воркери можуть надавати власну конфігурацію через метод `renderSettings()`:

```php
public function renderSettings(): string
{
    $apiKey = $this->getConfig('api_key', '');
    $endpoint = $this->getConfig('endpoint', '');
    
    return <<<HTML
        <h4><i data-lucide="key" class="w-4 h-4"></i> Конфігурація API</h4>
        <div class="form-group">
            <label>API Endpoint</label>
            <input type="url" 
                   class="form-control" 
                   name="endpoint" 
                   value="{$endpoint}"
                   placeholder="https://api.example.com">
        </div>
        <div class="form-group">
            <label>API ключ</label>
            <input type="text" 
                   class="form-control" 
                   name="api_key" 
                   value="{$apiKey}"
                   placeholder="ваш-api-ключ">
        </div>
        <hr>
    HTML;
}
```

### Читання конфігурації

Використовуйте методи `BaseWorker` для доступу до налаштувань:

```php
// Отримати одне значення
$endpoint = $this->getConfig('endpoint', 'https://default.com');

// Отримати вкладене значення (dot notation)
$timeout = $this->getConfig('api.timeout', 30);

// Отримати всі налаштування
$settings = $this->settings();
```

### Збереження конфігурації

Конфігурація автоматично зберігається через адмін інтерфейс. Також можна програмно оновити:

```php
// Встановити одне значення
$this->setConfig('endpoint', 'https://api.example.com');

// Оновити кілька значень
$this->updateConfig([
    'endpoint' => 'https://api.example.com',
    'api_key' => 'secret-key',
    'timeout' => 60,
]);
```

**Зберігання:** Налаштування зберігаються в `s_workers.settings` (JSON колонка).

### Конфігурація розкладу

Воркери з методом `taskMake()` автоматично отримують конфігурацію розкладу в адмін інтерфейсі.

**Типи розкладу:**

1. **На вимогу** - Тільки ручне виконання
2. **Один раз** - Виконати один раз у вказаний datetime
3. **Періодично** - Виконувати у вказаний час з періодичністю (щогодини/щодня/щотижня)
4. **Регулярно** - Виконувати у часовому періоді з інтервалом (кожні 15/30/60 хвилин)

**Перевірка чи треба запускати:**

```php
public function taskMake(sTaskModel $task, array $opt = []): void
{
    // Перевірка розкладу (пропускаємо для ручних запусків)
    $isManual = $opt['manual'] ?? true;
    if (!$isManual && !$this->shouldRunNow()) {
        $task->update([
            'status' => sTaskModel::TASK_STATUS_FINISHED,
            'message' => 'Пропущено: поза межами розкладу',
        ]);
        return;
    }
    
    // Продовження виконання задачі...
}
```

**Доступ до розкладу:**

```php
$schedule = $this->getSchedule();
// Повертає:
// [
//     'type' => 'regular',
//     'enabled' => true,
//     'start_time' => '05:00',
//     'end_time' => '23:00',
//     'interval' => 'hourly',
// ]
```

## API управління задачами

### Створення задач

```php
use Seiger\sTask\Facades\sTask;

// Базове створення задачі
$task = sTask::create(
    identifier: 'product',
    action: 'import',
    data: ['file' => '/path/to/products.csv'],
    priority: 'high',
    userId: evo()->getLoginUserID()
);

// Створити з власним пріоритетом
$task = sTask::create(
    identifier: 'product',
    action: 'export',
    data: ['format' => 'csv', 'filters' => ['active' => true]],
    priority: 'normal', // 'low', 'normal', 'high'
    userId: evo()->getLoginUserID()
);

// Програмне створення задачі з воркера
$worker = new ProductWorker();
$task = $worker->createTask('import', ['file' => 'products.csv']);
```

### Обробка задач

```php
// Обробити всі задачі що очікують (розмір пакету за замовчуванням: 10)
$processedCount = sTask::processPendingTasks();

// Обробити з власним розміром пакету
$processedCount = sTask::processPendingTasks(batchSize: 50);

// Отримати статистику задач
$stats = sTask::getStats();
/* Повертає:
[
    'pending' => 5,
    'running' => 2,
    'completed' => 100,
    'failed' => 3,
    'cancelled' => 1,
    'total' => 111,
]
*/

// Отримати задачі що очікують
$pending = sTask::getPendingTasks(limit: 20);

foreach ($pending as $task) {
    echo "Задача #{$task->id}: {$task->identifier} -> {$task->action}\n";
}
```

### Управління воркерами

```php
// Виявити нові воркери
$registered = sTask::discoverWorkers();
echo "Зареєстровано " . count($registered) . " нових воркерів\n";

// Пересканувати існуючі воркери (оновити їх метадані)
$updated = sTask::rescanWorkers();
echo "Оновлено " . count($updated) . " воркерів\n";

// Очистити orphaned воркери (класи більше не існують)
$deleted = sTask::cleanOrphanedWorkers();
echo "Видалено {$deleted} orphaned воркерів\n";

// Отримати всіх воркерів
$workers = sTask::getWorkers(activeOnly: false);

foreach ($workers as $worker) {
    echo "{$worker->identifier} ({$worker->scope}) - ";
    echo $worker->active ? 'Активний' : 'Неактивний';
    echo "\n";
}

// Отримати конкретного воркера
$worker = sTask::getWorker('product');
if ($worker) {
    echo "Назва: {$worker->title}\n";
    echo "Опис: {$worker->description}\n";
    echo "Іконка: {$worker->icon}\n";
}

// Активувати/деактивувати воркерів
sTask::activateWorker('product');
sTask::deactivateWorker('old_worker');

// Фільтрувати воркерів за scope
$commerceWorkers = \Seiger\sTask\Models\sWorker::byScope('scommerce')->get();
```

### Виконання задач

```php
// Виконати конкретну задачу
$task = \Seiger\sTask\Models\sTaskModel::find(1);
$result = sTask::execute($task);

if ($result) {
    echo "Задача завершена успішно\n";
} else {
    echo "Задача невдала: {$task->message}\n";
}

// Повторити невдалу задачу
if ($task->canRetry()) {
    sTask::retry($task);
}
```

### Операції очищення

```php
// Очистити задачі старші 30 днів
$deletedTasks = sTask::cleanOldTasks(days: 30);
echo "Видалено {$deletedTasks} старих задач\n";

// Очистити логи старші 30 днів
$deletedLogs = sTask::cleanOldLogs(days: 30);
echo "Видалено {$deletedLogs} старих файлів логів\n";

// Власне очищення
$deleted = \Seiger\sTask\Models\sTaskModel::where('status', 30) // completed
    ->where('finished_at', '<', now()->subDays(7))
    ->delete();
```

## Конвенція іменування методів дій

Методи дій повинні слідувати конвенції `task{Action}`:

| Назва дії | Назва методу | Приклад |
|-----------|--------------|---------|
| `import` | `taskImport()` | Імпорт товарів |
| `export` | `taskExport()` | Експорт товарів |
| `sync_stock` | `taskSyncStock()` | Синхронізація залишків |
| `generate_report` | `taskGenerateReport()` | Генерація звітів |
| `send_emails` | `taskSendEmails()` | Масова розсилка |

```php
// Приклади перетворення назв дій:
'import' → taskImport()
'export' → taskExport()  
'sync' → taskSync()
'sync_stock' → taskSyncStock()
'send_emails' → taskSendEmails()
'generate_report' → taskGenerateReport()
'cleanup-old-data' → taskCleanupOldData()
```

## Відстеження прогресу

### Базові оновлення прогресу

```php
public function taskProcess(sTaskModel $task, array $options = []): void
{
    $items = range(1, 1000);
    $total = count($items);
    
    foreach ($items as $i => $item) {
        // Обробити елемент
        sleep(0.01); // Симуляція роботи
        
        // Оновити прогрес
        $processed = $i + 1;
        $progress = (int)(($processed / $total) * 100);
        
        $this->pushProgress($task, [
            'progress' => $progress,
            'processed' => $processed,
            'total' => $total,
            'message' => "Обробка елемента {$processed} з {$total}"
        ]);
    }
    
    $this->markFinished($task);
}
```

### Прогрес з розрахунком ETA

```php
public function taskLongRunning(sTaskModel $task, array $options = []): void
{
    $total = 10000;
    $startTime = microtime(true);
    
    for ($i = 0; $i < $total; $i++) {
        // Обробити елемент
        $this->processItem($i);
        
        // Оновлювати кожні 100 елементів
        if ($i > 0 && $i % 100 === 0) {
            $elapsed = microtime(true) - $startTime;
            $rate = $i / $elapsed; // елементів на секунду
            $remaining = $total - $i;
            $etaSeconds = $remaining / $rate;
            
            $this->pushProgress($task, [
                'progress' => (int)(($i / $total) * 100),
                'processed' => $i,
                'total' => $total,
                'eta' => niceEta($etaSeconds),
                'message' => "Обробка... {$i}/{$total}"
            ]);
        }
    }
    
    $this->markFinished($task, null, "Оброблено {$total} елементів");
}
```

### Багатоетапний прогрес

```php
public function taskMultiStage(sTaskModel $task, array $options = []): void
{
    try {
        // Етап 1: Підготовка (0-20%)
        $this->pushProgress($task, [
            'progress' => 5,
            'message' => 'Підготовка даних...'
        ]);
        
        $data = $this->prepareData();
        
        $this->pushProgress($task, [
            'progress' => 20,
            'message' => 'Дані підготовлено'
        ]);
        
        // Етап 2: Обробка (20-80%)
        $total = count($data);
        foreach ($data as $i => $item) {
            $this->processItem($item);
            
            // Прогрес від 20% до 80%
            $stageProgress = ($i + 1) / $total; // 0.0 до 1.0
            $overallProgress = 20 + ($stageProgress * 60); // 20 до 80
            
            if ($i % 10 === 0) {
                $this->pushProgress($task, [
                    'progress' => (int)$overallProgress,
                    'processed' => $i + 1,
                    'total' => $total,
                    'message' => "Обробка: {$i}/{$total}"
                ]);
            }
        }
        
        // Етап 3: Завершення (80-100%)
        $this->pushProgress($task, [
            'progress' => 85,
            'message' => 'Генерація звіту...'
        ]);
        
        $reportPath = $this->generateReport($data);
        
        $this->pushProgress($task, [
            'progress' => 95,
            'message' => 'Збереження результатів...'
        ]);
        
        $this->saveResults($data);
        
        // Готово
        $this->markFinished($task, $reportPath, 'Всі етапи завершено');
        
    } catch (\Exception $e) {
        $this->markFailed($task, $e->getMessage());
    }
}
```

## Логування

### Файлова система відстеження прогресу

sTask використовує файлову систему відстеження прогресу зі структурованими логами:

**Зберігання:**
- Розташування: `storage/stask/{task_id}.log`
- Формат: Значення розділені вертикальною рискою
- Структура: `status|progress|processed|total|eta|message`

**Приклад файлу лога:**
```
preparing|0|0|0|—|Підготовка задачі...
running|20|50|250|3m 15s|Обробка елементів...
running|45|112|250|2m 10s|Обробка елементів...
running|75|187|250|45s|Обробка елементів...
completed|100|250|250|0s|**Задачу виконано успішно (5.2s)**
```

**Переваги:**
- **Тільки додавання** - Немає конфліктів блокування файлів
- **Повна історія** - Повне трасування виконання
- **Швидке читання** - Прочитати останній рядок для поточного статусу
- **Реальний час** - Миттєві оновлення в UI

### Методи оновлення прогресу

**pushProgress()** - Головний метод для оновлення прогресу:

```php
$this->pushProgress($task, [
    'progress' => 45,           // 0-100
    'processed' => 112,         // Оброблено елементів
    'total' => 250,             // Всього елементів
    'eta' => '2m 10s',         // Очікуваний час
    'message' => 'Обробка...'   // Поточна операція
]);
```

Кожен виклик додає новий рядок у файл лога з усією інформацією.

### Конвертація статусів у текст

sTask надає централізований метод для конвертації кодів статусів у текстові представлення:

**Статичний метод:**
```php
use Seiger\sTask\Models\sTaskModel;

// Конвертувати код статусу в текст
$statusText = sTaskModel::statusText(sTaskModel::TASK_STATUS_RUNNING);
// Повертає: 'running'

// Використання в pushProgress
$this->pushProgress($task, [
    'status' => sTaskModel::statusText(sTaskModel::TASK_STATUS_FINISHED),
    'progress' => 100,
    'message' => 'Задачу виконано'
]);
```

**Використання екземпляра задачі:**
```php
// Отримати текстовий статус з екземпляра задачі
$task = sTaskModel::find($id);
$statusText = $task->status_text;  // Повертає 'running', 'completed', тощо

// Доступні текстові статуси:
// - 'pending'    (TASK_STATUS_QUEUED = 10)
// - 'preparing'  (TASK_STATUS_PREPARING = 30)
// - 'running'    (TASK_STATUS_RUNNING = 50)
// - 'completed'  (TASK_STATUS_FINISHED = 80)
// - 'failed'     (TASK_STATUS_FAILED = 100)
```

**Переваги:**
- **Типобезпека** - Використання констант замість жорстко закодованих рядків
- **Узгодженість** - Всі текстові статуси походять з одного місця
- **Легко розширювати** - Додавати нові статуси в одному місці
- **Без помилок** - Неможливо зробити помилку в написанні назв статусів

### Автоматичне логування

sTask автоматично логує:
- Старт/завершення задачі
- Помилки задачі
- Оновлення прогресу (у файлах прогресу)

Файли логів зберігаються в `storage/stask/{task_id}.log`

### Власне логування

```php
use Seiger\sTask\Facades\sTask;

public function taskWithLogging(sTaskModel $task, array $options = []): void
{
    // Info лог
    sTask::log($task, 'info', 'Початок процесу імпорту', [
        'file' => $options['file'],
        'user_id' => $task->started_by
    ]);
    
    try {
        foreach ($items as $item) {
            try {
                $this->processItem($item);
            } catch (\Exception $e) {
                // Warning для некритичних помилок
                sTask::log($task, 'warning', "Пропущено елемент {$item->id}", [
                    'reason' => $e->getMessage(),
                    'item' => $item->toArray()
                ]);
                continue;
            }
        }
        
        // Успіх info
        sTask::log($task, 'info', 'Імпорт завершено успішно', [
            'total_processed' => count($items)
        ]);
        
        $this->markFinished($task);
        
    } catch (\Exception $e) {
        // Error лог
        sTask::log($task, 'error', 'Імпорт невдалий', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        $this->markFailed($task, $e->getMessage());
    }
}
```

### Читання логів

```php
$task = \Seiger\sTask\Models\sTaskModel::find(1);

// Отримати всі логи
$logs = $task->getLogs();
foreach ($logs as $log) {
    echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}\n";
    if (!empty($log['context'])) {
        print_r($log['context']);
    }
}

// Отримати останні 10 логів
$recentLogs = $task->getLastLogs(10);

// Отримати тільки помилки
$errorLogs = $task->getErrorLogs();

// Очистити логи задачі
$task->clearLogs();

// Завантажити логи
return $task->logger()->downloadLogs($task);
```

## Обробка помилок

### Базова обробка помилок

```php
public function taskSafe(sTaskModel $task, array $options = []): void
{
    try {
        // Ваша логіка
        $result = $this->doSomething();
        
        if (!$result) {
            throw new \RuntimeException('Операція невдала');
        }
        
        $this->markFinished($task);
        
    } catch (\Exception $e) {
        // Логувати детальну помилку
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

### Логіка повторних спроб

```php
public function taskWithRetry(sTaskModel $task, array $options = []): void
{
    $maxRetries = 3;
    $currentAttempt = $task->attempts;
    
    try {
        // Спроба операції
        $this->doUnreliableOperation();
        
        $this->markFinished($task);
        
    } catch (\Exception $e) {
        if ($currentAttempt < $maxRetries) {
            // Буде повторна спроба
            sTask::log($task, 'warning', 
                "Спроба {$currentAttempt} невдала, буде повтор", 
                ['error' => $e->getMessage()]
            );
            
            // sTask автоматично повторить
            throw $e;
        } else {
            // Досягнуто максимум спроб
            sTask::log($task, 'error', 
                "Всі {$maxRetries} спроби невдалі", 
                ['last_error' => $e->getMessage()]
            );
            
            $this->markFailed($task, "Невдало після {$maxRetries} спроб: " . $e->getMessage());
        }
    }
}
```

### Валідація перед обробкою

```php
public function taskValidated(sTaskModel $task, array $options = []): void
{
    // Валідувати опції
    $errors = [];
    
    if (empty($options['file'])) {
        $errors[] = 'Шлях до файлу обов\'язковий';
    } elseif (!file_exists($options['file'])) {
        $errors[] = 'Файл не існує: ' . $options['file'];
    }
    
    if (empty($options['user_id'])) {
        $errors[] = 'ID користувача обов\'язковий';
    }
    
    if (!empty($errors)) {
        $this->markFailed($task, 'Валідація невдала: ' . implode('; ', $errors));
        return;
    }
    
    // Обробити
    try {
        $this->processFile($options['file'], $options['user_id']);
        $this->markFinished($task);
    } catch (\Exception $e) {
        $this->markFailed($task, $e->getMessage());
    }
}
```

## Пріоритети задач

```php
// Високий пріоритет - обробляється першим
$urgentTask = sTask::create(
    identifier: 'notification',
    action: 'send_urgent',
    data: ['email' => 'admin@example.com'],
    priority: 'high'
);

// Звичайний пріоритет - за замовчуванням
$normalTask = sTask::create(
    identifier: 'report',
    action: 'generate',
    data: [],
    priority: 'normal'
);

// Низький пріоритет - обробляється останнім
$backgroundTask = sTask::create(
    identifier: 'cleanup',
    action: 'archive_old_data',
    data: [],
    priority: 'low'
);

// Задачі обробляються в порядку пріоритету:
// 1. Всі задачі з пріоритетом 'high'
// 2. Всі задачі з пріоритетом 'normal'  
// 3. Всі задачі з пріоритетом 'low'
```

## Статуси задач

```php
use Seiger\sTask\Models\sTaskModel;

// Константи статусів
// 10 - pending (очікує)
// 20 - running (виконується)
// 30 - completed (завершено)
// 40 - failed (невдало)
// 50 - cancelled (скасовано)

// Перевірити статус задачі
$task = sTaskModel::find(1);

if ($task->isPending()) {
    echo "Задача очікує обробки\n";
}

if ($task->isRunning()) {
    echo "Задача виконується зараз\n";
}

if ($task->isFinished()) {
    echo "Задача завершена (completed, failed або cancelled)\n";
}

// Отримати текстовий статус
echo $task->status_text; // 'pending', 'running', 'completed', 'failed', 'cancelled'

// Запити за статусом
$pendingTasks = sTaskModel::pending()->get();
$runningTasks = sTaskModel::running()->get();
$completedTasks = sTaskModel::completed()->get();
$failedTasks = sTaskModel::failed()->get();

// Отримати незавершені завдання (не finished і не failed)
$incompleteTasks = sTaskModel::incomplete()->get();

// Запити за ідентифікатором та дією
$productImports = sTaskModel::byIdentifier('product')
    ->byAction('import')
    ->get();

// Останні невдалі задачі
$recentFailures = sTaskModel::failed()
    ->where('created_at', '>', now()->subDays(7))
    ->orderBy('created_at', 'desc')
    ->get();
```

## Розширені приклади

### Пакетна обробка з chunking

```php
public function taskBatchProcess(sTaskModel $task, array $options = []): void
{
    $chunkSize = 100;
    $processed = 0;
    
    // Отримати загальну кількість
    $total = \DB::table('products')->count();
    
    \DB::table('products')->orderBy('id')->chunk($chunkSize, function($products) use ($task, &$processed, $total) {
        foreach ($products as $product) {
            $this->processProduct($product);
            $processed++;
        }
        
        // Оновити прогрес після кожного chunk
        $this->pushProgress($task, [
            'progress' => (int)(($processed / $total) * 100),
            'processed' => $processed,
            'total' => $total,
            'message' => "Оброблено {$processed}/{$total} товарів"
        ]);
    });
    
    $this->markFinished($task, null, "Оброблено {$processed} товарів");
}
```

### Задача завантаження файлу

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
    
    // Callback прогресу
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($resource, $downloadSize, $downloaded) use ($task) {
        if ($downloadSize > 0) {
            $progress = (int)(($downloaded / $downloadSize) * 100);
            
            $this->pushProgress($task, [
                'progress' => $progress,
                'processed' => $downloaded,
                'total' => $downloadSize,
                'message' => "Завантажено " . $this->formatBytes($downloaded) . " з " . $this->formatBytes($downloadSize)
            ]);
        }
    });
    
    curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    fclose($file);
    
    if ($error) {
        unlink($destination);
        $this->markFailed($task, "Завантаження невдале: {$error}");
    } else {
        $this->markFinished($task, $destination, "Завантажено {$filename}");
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

### Задача синхронізації з API

```php
public function taskApiSync(sTaskModel $task, array $options = []): void
{
    $apiUrl = $options['api_url'];
    $apiKey = $this->settings()['api_key'];
    
    try {
        // Отримати з API
        $this->pushProgress($task, [
            'progress' => 10,
            'message' => 'Отримання даних з API...'
        ]);
        
        $response = $this->apiRequest($apiUrl, $apiKey);
        $items = json_decode($response, true);
        
        if (!is_array($items)) {
            throw new \RuntimeException('Невалідна відповідь API');
        }
        
        $total = count($items);
        
        // Обробити елементи
        foreach ($items as $i => $item) {
            $this->syncItem($item);
            
            if (($i + 1) % 10 === 0) {
                $progress = 10 + (int)((($i + 1) / $total) * 80); // 10% до 90%
                
                $this->pushProgress($task, [
                    'progress' => $progress,
                    'processed' => $i + 1,
                    'total' => $total,
                    'message' => "Синхронізовано {$i+1}/{$total} елементів"
                ]);
            }
        }
        
        // Фінальне очищення
        $this->pushProgress($task, [
            'progress' => 95,
            'message' => 'Очищення...'
        ]);
        
        $this->cleanup();
        
        $this->markFinished($task, null, "Синхронізовано {$total} елементів з API");
        
    } catch (\Exception $e) {
        $this->markFailed($task, $e->getMessage());
    }
}
```



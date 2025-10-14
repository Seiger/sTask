---
title: Початок роботи
sidebar_label: Початок роботи
sidebar_position: 2
---

## Вимоги

- Evolution CMS **3.2.0+**
- PHP **8.2+**
- Composer **2.2+**
- Одна з: **MySQL 8.0+** / **MariaDB 10.5+** / **PostgreSQL 10+** / **SQLite 3.25+**

## Встановлення

### Крок 1: Перейдіть до директорії Core

```console
cd core
```

### Крок 2: Оновіть Composer

```console
composer update
```

### Крок 3: Встановіть пакет

```console
php artisan package:installrequire seiger/stask "*"
```

### Крок 4: Опублікуйте ресурси

```console
php artisan vendor:publish --provider="Seiger\sTask\sTaskServiceProvider"
```

Ця команда опублікує:
- Файли конфігурації в `core/config/app/aliases/`
- Публічні ресурси (CSS, JS, зображення) в `public/assets/site/`
- Створить директорію зберігання `storage/stask/`

### Крок 5: Запустіть міграції

```console
php artisan migrate
```

Це створить дві таблиці бази даних:
- `s_workers` - Конфігурації воркерів
- `s_tasks` - Записи задач та історія виконання

### Крок 6: Виявіть воркерів

```console
php artisan stask:discover-workers
```

Це сканує ваші встановлені пакети на наявність воркерів, які реалізують `TaskInterface`, та реєструє їх автоматично.

> **Примітка:** Пакет автоматично інтегрується з інтерфейсом менеджера Evolution CMS. Додаткова конфігурація не потрібна!

## Де знайти модуль

Після встановлення, отримайте доступ до sTask через:

**Менеджер → Інструменти → Менеджер Задач**

Ви побачите:
- **Вкладка Панель** - Статистика задач та останні задачі
- **Вкладка Воркери** - Управління воркерами та виявлення
- **Вкладка Статистика** - Детальна аналітика

## Швидкий посібник

### 1. Створіть свою першу задачу (програмно)

```php
use Seiger\sTask\Facades\sTask;

// Створити просту задачу
$task = sTask::create(
    identifier: 'product_sync',  // Ідентифікатор воркера
    action: 'import',             // Дія для виконання
    data: [                        // Дані задачі
        'file' => '/path/to/products.csv',
        'delimiter' => ',',
        'skip_first_row' => true
    ],
    priority: 'normal',           // 'low', 'normal', 'high'
    userId: evo()->getLoginUserID()
);

echo "Задачу #{$task->id} створено успішно!\n";
```

### 2. Перевірте статус задачі

```php
// Отримати задачу за ID
$task = \Seiger\sTask\Models\sTaskModel::find(1);

// Перевірити статус
if ($task->isPending()) {
    echo "Задача очікує обробки\n";
}

if ($task->isRunning()) {
    echo "Задача виконується зараз\n";
    echo "Прогрес: {$task->progress}%\n";
}

if ($task->isFinished()) {
    echo "Задача завершена\n";
    echo "Статус: {$task->status_text}\n";
    echo "Повідомлення: {$task->message}\n";
}

// Отримати детальну інформацію
echo "Воркер: {$task->identifier}\n";
echo "Дія: {$task->action}\n";
echo "Створено: {$task->created_at}\n";
echo "Запустив: Користувач #{$task->started_by}\n";
```

### 3. Обробіть задачі що очікують

```php
// Обробити всі задачі що очікують
$processedCount = sTask::processPendingTasks();
echo "Оброблено {$processedCount} задач\n";

// Або обробити з власним розміром пакету
$processedCount = sTask::processPendingTasks(batchSize: 5);
```

### 4. Перегляньте логи задачі

```php
$task = \Seiger\sTask\Models\sTaskModel::find(1);

// Отримати всі логи
$logs = $task->getLogs();
foreach ($logs as $log) {
    echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}\n";
}

// Отримати останні 10 логів
$recentLogs = $task->getLastLogs(10);

// Отримати тільки помилки
$errorLogs = $task->getErrorLogs();
```

## Базові приклади використання

### Приклад 1: Імпорт товарів

```php
use Seiger\sTask\Facades\sTask;

// Створити задачу імпорту
$task = sTask::create(
    identifier: 'product',
    action: 'import',
    data: [
        'file' => storage_path('imports/products_2025.csv'),
        'update_existing' => true,
        'create_new' => true
    ],
    priority: 'high'
);

echo "Задачу імпорту створено: #{$task->id}\n";

// Задача буде оброблена автоматично воркером
// Ви можете моніторити прогрес в адмін інтерфейсі
```

### Приклад 2: Експорт даних

```php
// Створити задачу експорту
$task = sTask::create(
    identifier: 'product',
    action: 'export',
    data: [
        'format' => 'csv',
        'filters' => [
            'category_id' => 5,
            'active' => true
        ],
        'fields' => ['id', 'sku', 'name', 'price', 'stock']
    ],
    priority: 'normal'
);

// Очікувати завершення (в реальному сценарії перевіряйте через адмін або API)
while (!$task->fresh()->isFinished()) {
    sleep(1);
}

if ($task->status === 30) { // completed
    echo "Експорт завершено!\n";
    echo "Завантажити файл: {$task->result}\n";
}
```

### Приклад 3: Масова email кампанія

```php
// Створити задачу email
$task = sTask::create(
    identifier: 'email_campaign',
    action: 'send',
    data: [
        'template' => 'newsletter_2025',
        'recipients' => [
            ['email' => 'user1@example.com', 'name' => 'Іван Петренко'],
            ['email' => 'user2@example.com', 'name' => 'Марія Коваленко'],
            // ... більше одержувачів
        ],
        'subject' => 'Щомісячна розсилка - Січень 2025',
        'attachments' => [
            storage_path('newsletters/january_2025.pdf')
        ]
    ],
    priority: 'normal'
);
```

### Приклад 4: Заплановане очищення

```php
// Створити задачу очищення запланованої на пізніше
$task = \Seiger\sTask\Models\sTaskModel::create([
    'identifier' => 'system',
    'action' => 'cleanup',
    'status' => 10, // pending
    'priority' => 'low',
    'start_at' => now()->addHours(2), // Запустити через 2 години
    'meta' => [
        'clean_logs' => true,
        'clean_cache' => true,
        'older_than_days' => 30
    ]
]);

echo "Очищення заплановано на: {$task->start_at}\n";
```

## Управління воркерами

### Виявити нові воркери

```php
// Виявити та зареєструвати нові воркери
$registered = sTask::discoverWorkers();

echo "Знайдено та зареєстровано " . count($registered) . " нових воркерів:\n";
foreach ($registered as $worker) {
    echo "- {$worker->identifier} ({$worker->scope})\n";
}
```

### Пересканувати існуючі воркери

```php
// Оновити метадані для існуючих воркерів
$updated = sTask::rescanWorkers();

echo "Оновлено " . count($updated) . " воркерів\n";
```

### Очистити orphaned воркери

```php
// Видалити воркери, класи яких більше не існують
$deleted = sTask::cleanOrphanedWorkers();

echo "Видалено {$deleted} orphaned воркерів\n";
```

### Список всіх воркерів

```php
// Отримати всіх воркерів
$allWorkers = sTask::getWorkers(activeOnly: false);

echo "Всього воркерів: " . $allWorkers->count() . "\n\n";

foreach ($allWorkers as $worker) {
    echo "Воркер: {$worker->identifier}\n";
    echo "  Scope: {$worker->scope}\n";
    echo "  Назва: {$worker->title}\n";
    echo "  Статус: " . ($worker->active ? 'Активний' : 'Неактивний') . "\n";
    echo "  Клас: {$worker->class}\n";
    echo "\n";
}
```

### Фільтрувати воркерів за scope

```php
use Seiger\sTask\Models\sWorker;

// Отримати тільки sCommerce воркерів
$commerceWorkers = sWorker::byScope('scommerce')
    ->active()
    ->ordered()
    ->get();

foreach ($commerceWorkers as $worker) {
    echo "{$worker->identifier}: {$worker->title}\n";
}
```

### Активувати/деактивувати воркерів

```php
// Активувати воркер
sTask::activateWorker('product');
echo "Product воркер активовано\n";

// Деактивувати воркер
sTask::deactivateWorker('old_import');
echo "Old import воркер деактивовано\n";

// Або безпосередньо через модель
$worker = sWorker::where('identifier', 'product')->first();
$worker->active = true;
$worker->save();
```

## Статистика задач

```php
// Отримати комплексну статистику
$stats = sTask::getStats();

echo "Статистика задач:\n";
echo "  Очікують: {$stats['pending']}\n";
echo "  Виконуються: {$stats['running']}\n";
echo "  Завершені: {$stats['completed']}\n";
echo "  Невдалі: {$stats['failed']}\n";
echo "  Скасовані: {$stats['cancelled']}\n";
echo "  Всього: {$stats['total']}\n";

// Отримати деталі задач що очікують
$pending = sTask::getPendingTasks(limit: 10);

echo "\nЗадачі що очікують:\n";
foreach ($pending as $task) {
    echo "  #{$task->id}: {$task->identifier} -> {$task->action}\n";
    echo "    Пріоритет: {$task->priority}\n";
    echo "    Створено: {$task->created_at->diffForHumans()}\n";
}
```

## Операції очищення

### Очистити старі задачі

```php
// Видалити завершені задачі старші 30 днів
$deletedTasks = sTask::cleanOldTasks(days: 30);
echo "Видалено {$deletedTasks} старих задач\n";

// Власне очищення
use Seiger\sTask\Models\sTaskModel;

// Видалити невдалі задачі старші 7 днів
$deleted = sTaskModel::failed()
    ->where('finished_at', '<', now()->subDays(7))
    ->delete();

echo "Видалено {$deleted} старих невдалих задач\n";
```

### Очистити старі логи

```php
// Видалити файли логів старші 30 днів
$deletedLogs = sTask::cleanOldLogs(days: 30);
echo "Видалено {$deletedLogs} старих файлів логів\n";

// Очистити логи конкретної задачі
$task = sTaskModel::find(1);
$task->clearLogs();
echo "Логи очищено для задачі #{$task->id}\n";
```

## Artisan команди

### Виявити воркерів

```console
# Базове виявлення
php artisan stask:discover-workers

# Виявити з пересканеруванням
php artisan stask:discover-workers --rescan

# Виявити з очищенням
php artisan stask:discover-workers --clean

# Виявити з обома опціями
php artisan stask:discover-workers --rescan --clean
```

**Що робить:**
- `--rescan` - Оновлює метадані для існуючих воркерів
- `--clean` - Видаляє orphaned воркери (класи більше не існують)

### Опублікувати ресурси

```console
# Опублікувати або оновити ресурси
php artisan stask:publish
```

Ця команда повторно публікує всі ресурси пакету:
- CSS файли
- JavaScript файли
- Зображення та іконки
- Файли конфігурації

Використовуйте це після оновлень пакету для отримання останніх ресурсів.

## Перевірка конфігурації

Якщо ви розробляєте власний пакет, який інтегрується з sTask, перевірте чи встановлено sTask:

```php
// Перевірити чи встановлено sTask
if (evo()->getConfig('check_sTask', false)) {
    // sTask доступний
    $task = \Seiger\sTask\Facades\sTask::create(
        identifier: 'my_worker',
        action: 'process',
        data: []
    );
} else {
    // sTask не встановлено, використовуйте fallback
    $this->processDirectly();
}
```

Ви також можете перевірити версію:

```php
$version = evo()->getConfig('sTaskVer', 'unknown');
echo "Версія sTask: {$version}\n";
```

## Структура зберігання

sTask створює наступну структуру зберігання:

```
storage/
└── stask/
    ├── 1.log           # Логи задачі #1
    ├── 2.log           # Логи задачі #2
    ├── 3.log           # Логи задачі #3
    ├── 1.json          # Знімок прогресу задачі #1
    ├── 2.json          # Знімок прогресу задачі #2
    ├── .gc_progress    # Маркер збірки сміття
    └── ...
```

**Файли логів** (`*.log`):
- Містять детальні логи виконання
- Включають часові мітки, рівні (info/warning/error), повідомлення
- Автоматично очищаються після налаштованого періоду

**Файли прогресу** (`*.json`):
- Знімки прогресу в реальному часі
- Використовуються для моніторингу прогресу в адмін інтерфейсі
- Автоматично очищаються через 24 години

## Таблиці бази даних

### Таблиця s_workers

Зберігає конфігурації воркерів:

| Колонка | Тип | Опис |
|---------|-----|------|
| `id` | BIGINT | Первинний ключ |
| `uuid` | UUID | ID інтеграції зовнішньої системи |
| `identifier` | VARCHAR | Унікальний ідентифікатор воркера |
| `scope` | VARCHAR | Scope модуля/пакету |
| `class` | VARCHAR | Назва класу воркера |
| `active` | BOOLEAN | Статус активності |
| `position` | INT | Порядок відображення |
| `settings` | JSON | Налаштування воркера |
| `hidden` | INT | Прапорець видимості |

```php
// Приклади запитів
use Seiger\sTask\Models\sWorker;

// Отримати активних воркерів
$active = sWorker::active()->get();

// Отримати воркерів за scope
$commerce = sWorker::byScope('scommerce')->get();

// Отримати впорядкованих воркерів
$ordered = sWorker::ordered()->get();
```

### Таблиця s_tasks

Зберігає записи задач:

| Колонка | Тип | Опис |
|---------|-----|------|
| `id` | BIGINT | Первинний ключ |
| `identifier` | VARCHAR | Ідентифікатор воркера |
| `action` | VARCHAR | Назва дії |
| `status` | INT | Статус виконання (10-50) |
| `message` | VARCHAR | Повідомлення статусу |
| `started_by` | INT | ID користувача |
| `meta` | JSON | Дані задачі |
| `result` | TEXT | Дані результату/шлях до файлу |
| `start_at` | TIMESTAMP | Запланований старт |
| `finished_at` | TIMESTAMP | Час завершення |
| `attempts` | INT | Спроби виконання |
| `max_attempts` | INT | Максимум спроб |
| `priority` | VARCHAR | Пріоритет задачі |
| `progress` | INT | Відсоток прогресу |

```php
// Приклади запитів
use Seiger\sTask\Models\sTaskModel;

// Отримати задачі що очікують
$pending = sTaskModel::pending()->get();

// Отримати задачі що виконуються
$running = sTaskModel::running()->get();

// Отримати завершені задачі
$completed = sTaskModel::completed()->get();

// Отримати невдалі задачі
$failed = sTaskModel::failed()->get();

// Отримати задачі за ідентифікатором
$productTasks = sTaskModel::byIdentifier('product')->get();

// Отримати задачі за дією
$imports = sTaskModel::byAction('import')->get();

// Отримати високопріоритетні задачі
$urgent = sTaskModel::highPriority()->get();
```

## Наступні кроки

- Прочитайте [Керівництво розробника](./developers.md) для створення власних воркерів
- Досліджуйте [API довідник](./api.md) для розширеного використання
- Перегляньте [Приклади](./examples.md) для типових випадків використання

## Вирішення проблем

### Задачі не обробляються

1. Перевірте чи активні воркери:
```php
$worker = \Seiger\sTask\Models\sWorker::where('identifier', 'product')->first();
if (!$worker->active) {
    echo "Воркер неактивний!\n";
}
```

2. Перевірте статус задачі:
```php
$task = \Seiger\sTask\Models\sTaskModel::find(1);
echo "Статус: {$task->status_text}\n";
echo "Повідомлення: {$task->message}\n";
```

3. Перевірте логи:
```php
$logs = $task->getErrorLogs();
foreach ($logs as $log) {
    echo $log['message'] . "\n";
}
```

### Воркери не виявлено

1. Запустіть виявлення вручну:
```console
php artisan stask:discover-workers --clean
```

2. Перевірте чи клас реалізує `TaskInterface`:
```php
class MyWorker implements \Seiger\sTask\Contracts\TaskInterface
{
    // ...
}
```

3. Перевірте що ваш воркер знаходиться в валідному Composer пакеті та не в системних просторах імен (Illuminate, Symfony, тощо)

### Проблеми з правами доступу

Якщо ви отримуєте помилки прав доступу для storage:

```console
chmod -R 755 storage/stask
chown -R www-data:www-data storage/stask
```

Або через PHP:
```php
$path = storage_path('stask');
if (!is_writable($path)) {
    chmod($path, 0755);
}
```

---
title: Начало работы
sidebar_label: Начало работы
sidebar_position: 2
---

## Требования

- Evolution CMS **3.2.0+**
- PHP **8.2+**
- Composer **2.2+**
- Одна из: **MySQL 8.0+** / **MariaDB 10.5+** / **PostgreSQL 10+** / **SQLite 3.25+**

## Установка

### Шаг 1: Перейдите в директорию Core

```console
cd core
```

### Шаг 2: Обновите Composer

```console
composer update
```

### Шаг 3: Установите пакет

```console
php artisan package:installrequire seiger/stask "*"
```

### Шаг 4: Опубликуйте ресурсы

```console
php artisan vendor:publish --provider="Seiger\sTask\sTaskServiceProvider"
```

Эта команда опубликует:
- Файлы конфигурации в `core/config/app/aliases/`
- Публичные ресурсы (CSS, JS, изображения) в `public/assets/site/`
- Создаст директорию хранилища `storage/stask/`

### Шаг 5: Запустите миграции

```console
php artisan migrate
```

Это создаст две таблицы базы данных:
- `s_workers` - Конфигурации воркеров
- `s_tasks` - Записи задач и история выполнения

### Шаг 6: Настройка Обработчика Задач

Команда обработчика задач автоматически обрабатывает ожидающие задачи. Добавьте её в cron или планировщик задач:

```console
php artisan stask:worker
```

Для непрерывной обработки добавьте в crontab:

```cron
* * * * * cd /path/to/your/project && php artisan stask:worker >> /dev/null 2>&1
```

> **Примечание:** Воркеры автоматически обнаруживаются при открытии вкладки Воркеры в админ-панели. Ручное обнаружение не требуется!

## Где найти модуль

После установки, получите доступ к sTask через:

**Менеджер → Инструменты → Менеджер Задач**

Вы увидите:
- **Вкладка Панель** - Статистика задач и последние задачи
- **Вкладка Воркеры** - Управление воркерами и автоматическое обнаружение

## Быстрое руководство

### 1. Создайте свою первую задачу (программно)

```php
use Seiger\sTask\Facades\sTask;

// Создать простую задачу
$task = sTask::create(
    identifier: 'product_sync',  // Идентификатор воркера
    action: 'import',             // Действие для выполнения
    data: [                        // Данные задачи
        'file' => '/path/to/products.csv',
        'delimiter' => ',',
        'skip_first_row' => true
    ],
    priority: 'normal',           // 'low', 'normal', 'high'
    userId: evo()->getLoginUserID()
);

echo "Задача #{$task->id} создана успешно!\n";
```

### 2. Проверьте статус задачи

```php
// Получить задачу по ID
$task = \Seiger\sTask\Models\sTaskModel::find(1);

// Проверить статус
if ($task->isPending()) {
    echo "Задача ожидает обработки\n";
}

if ($task->isRunning()) {
    echo "Задача выполняется сейчас\n";
    echo "Прогресс: {$task->progress}%\n";
}

if ($task->isFinished()) {
    echo "Задача завершена\n";
    echo "Статус: {$task->status_text}\n";
    echo "Сообщение: {$task->message}\n";
}

// Получить детальную информацию
echo "Воркер: {$task->identifier}\n";
echo "Действие: {$task->action}\n";
echo "Создано: {$task->created_at}\n";
echo "Запустил: Пользователь #{$task->started_by}\n";
```

### 3. Обработайте ожидающие задачи

```php
// Обработать все ожидающие задачи
$processedCount = sTask::processPendingTasks();
echo "Обработано {$processedCount} задач\n";

// Или обработать с собственным размером пакета
$processedCount = sTask::processPendingTasks(batchSize: 5);
```

### 4. Просмотрите логи задачи

```php
$task = \Seiger\sTask\Models\sTaskModel::find(1);

// Получить все логи
$logs = $task->getLogs();
foreach ($logs as $log) {
    echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}\n";
}

// Получить последние 10 логов
$recentLogs = $task->getLastLogs(10);

// Получить только ошибки
$errorLogs = $task->getErrorLogs();
```

## Базовые примеры использования

### Пример 1: Импорт товаров

```php
use Seiger\sTask\Facades\sTask;

// Создать задачу импорта
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

echo "Задача импорта создана: #{$task->id}\n";

// Задача будет обработана автоматически воркером
// Вы можете мониторить прогресс в админ интерфейсе
```

### Пример 2: Экспорт данных

```php
// Создать задачу экспорта
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

// Ожидать завершения (в реальном сценарии проверяйте через админ или API)
while (!$task->fresh()->isFinished()) {
    sleep(1);
}

if ($task->status === 30) { // completed
    echo "Экспорт завершен!\n";
    echo "Скачать файл: {$task->result}\n";
}
```

### Пример 3: Массовая email кампания

```php
// Создать задачу email
$task = sTask::create(
    identifier: 'email_campaign',
    action: 'send',
    data: [
        'template' => 'newsletter_2025',
        'recipients' => [
            ['email' => 'user1@example.com', 'name' => 'Иван Петров'],
            ['email' => 'user2@example.com', 'name' => 'Мария Иванова'],
            // ... больше получателей
        ],
        'subject' => 'Ежемесячная рассылка - Январь 2025',
        'attachments' => [
            storage_path('newsletters/january_2025.pdf')
        ]
    ],
    priority: 'normal'
);
```

### Пример 4: Запланированная очистка

```php
// Создать задачу очистки запланированную на позже
$task = \Seiger\sTask\Models\sTaskModel::create([
    'identifier' => 'system',
    'action' => 'cleanup',
    'status' => 10, // pending
    'priority' => 'low',
    'start_at' => now()->addHours(2), // Запустить через 2 часа
    'meta' => [
        'clean_logs' => true,
        'clean_cache' => true,
        'older_than_days' => 30
    ]
]);

echo "Очистка запланирована на: {$task->start_at}\n";
```

## Управление воркерами

### Обнаружить новые воркеры

```php
// Обнаружить и зарегистрировать новые воркеры
$registered = sTask::discoverWorkers();

echo "Найдено и зарегистрировано " . count($registered) . " новых воркеров:\n";
foreach ($registered as $worker) {
    echo "- {$worker->identifier} ({$worker->scope})\n";
}
```

### Пересканировать существующие воркеры

```php
// Обновить метаданные для существующих воркеров
$updated = sTask::rescanWorkers();

echo "Обновлено " . count($updated) . " воркеров\n";
```

### Очистить orphaned воркеры

```php
// Удалить воркеры, классы которых больше не существуют
$deleted = sTask::cleanOrphanedWorkers();

echo "Удалено {$deleted} orphaned воркеров\n";
```

### Список всех воркеров

```php
// Получить всех воркеров
$allWorkers = sTask::getWorkers(activeOnly: false);

echo "Всего воркеров: " . $allWorkers->count() . "\n\n";

foreach ($allWorkers as $worker) {
    echo "Воркер: {$worker->identifier}\n";
    echo "  Scope: {$worker->scope}\n";
    echo "  Название: {$worker->title}\n";
    echo "  Статус: " . ($worker->active ? 'Активный' : 'Неактивный') . "\n";
    echo "  Класс: {$worker->class}\n";
    echo "\n";
}
```

### Фильтровать воркеров по scope

```php
use Seiger\sTask\Models\sWorker;

// Получить только sCommerce воркеров
$commerceWorkers = sWorker::byScope('scommerce')
    ->active()
    ->ordered()
    ->get();

foreach ($commerceWorkers as $worker) {
    echo "{$worker->identifier}: {$worker->title}\n";
}
```

### Активировать/деактивировать воркеров

```php
// Активировать воркер
sTask::activateWorker('product');
echo "Product воркер активирован\n";

// Деактивировать воркер
sTask::deactivateWorker('old_import');
echo "Old import воркер деактивирован\n";

// Или напрямую через модель
$worker = sWorker::where('identifier', 'product')->first();
$worker->active = true;
$worker->save();
```

## Статистика задач

```php
// Получить комплексную статистику
$stats = sTask::getStats();

echo "Статистика задач:\n";
echo "  Ожидают: {$stats['pending']}\n";
echo "  Выполняются: {$stats['running']}\n";
echo "  Завершены: {$stats['completed']}\n";
echo "  Неудачные: {$stats['failed']}\n";
echo "  Отменены: {$stats['cancelled']}\n";
echo "  Всего: {$stats['total']}\n";

// Получить детали ожидающих задач
$pending = sTask::getPendingTasks(limit: 10);

echo "\nОжидающие задачи:\n";
foreach ($pending as $task) {
    echo "  #{$task->id}: {$task->identifier} -> {$task->action}\n";
    echo "    Приоритет: {$task->priority}\n";
    echo "    Создано: {$task->created_at->diffForHumans()}\n";
}
```

## Операции очистки

### Очистить старые задачи

```php
// Удалить завершенные задачи старше 30 дней
$deletedTasks = sTask::cleanOldTasks(days: 30);
echo "Удалено {$deletedTasks} старых задач\n";

// Собственная очистка
use Seiger\sTask\Models\sTaskModel;

// Удалить неудачные задачи старше 7 дней
$deleted = sTaskModel::failed()
    ->where('finished_at', '<', now()->subDays(7))
    ->delete();

echo "Удалено {$deleted} старых неудачных задач\n";
```

### Очистить старые логи

```php
// Удалить файлы логов старше 30 дней
$deletedLogs = sTask::cleanOldLogs(days: 30);
echo "Удалено {$deletedLogs} старых файлов логов\n";

// Очистить логи конкретной задачи
$task = sTaskModel::find(1);
$task->clearLogs();
echo "Логи очищены для задачи #{$task->id}\n";
```

## Artisan команды

### Обнаружить воркеров

```console
# Базовое обнаружение
php artisan stask:discover-workers

# Обнаружить с пересканированием
php artisan stask:discover-workers --rescan

# Обнаружить с очисткой
php artisan stask:discover-workers --clean

# Обнаружить с обеими опциями
php artisan stask:discover-workers --rescan --clean
```

**Что делает:**
- `--rescan` - Обновляет метаданные для существующих воркеров
- `--clean` - Удаляет orphaned воркеры (классы больше не существуют)

### Опубликовать ресурсы

```console
# Опубликовать или обновить ресурсы
php artisan stask:publish
```

Эта команда повторно публикует все ресурсы пакета:
- CSS файлы
- JavaScript файлы
- Изображения и иконки
- Файлы конфигурации

Используйте это после обновлений пакета для получения последних ресурсов.

## Проверка конфигурации

Если вы разрабатываете собственный пакет, который интегрируется с sTask, проверьте установлен ли sTask:

```php
// Проверить установлен ли sTask
if (evo()->getConfig('check_sTask', false)) {
    // sTask доступен
    $task = \Seiger\sTask\Facades\sTask::create(
        identifier: 'my_worker',
        action: 'process',
        data: []
    );
} else {
    // sTask не установлен, используйте fallback
    $this->processDirectly();
}
```

Вы также можете проверить версию:

```php
$version = evo()->getConfig('sTaskVer', 'unknown');
echo "Версия sTask: {$version}\n";
```

## Структура хранилища

sTask создает следующую структуру хранилища:

```
storage/
└── stask/
    ├── 1.log           # Логи задачи #1
    ├── 2.log           # Логи задачи #2
    ├── 3.log           # Логи задачи #3
    ├── 1.json          # Снимок прогресса задачи #1
    ├── 2.json          # Снимок прогресса задачи #2
    ├── .gc_progress    # Маркер сборки мусора
    └── ...
```

**Файлы логов** (`*.log`):
- Содержат детальные логи выполнения
- Включают временные метки, уровни (info/warning/error), сообщения
- Автоматически очищаются после настроенного периода

**Файлы прогресса** (`*.json`):
- Снимки прогресса в реальном времени
- Используются для мониторинга прогресса в админ интерфейсе
- Автоматически очищаются через 24 часа

## Таблицы базы данных

### Таблица s_workers

Хранит конфигурации воркеров:

| Колонка | Тип | Описание |
|---------|-----|----------|
| `id` | BIGINT | Первичный ключ |
| `uuid` | UUID | ID интеграции внешней системы |
| `identifier` | VARCHAR | Уникальный идентификатор воркера |
| `scope` | VARCHAR | Scope модуля/пакета |
| `class` | VARCHAR | Название класса воркера |
| `active` | BOOLEAN | Статус активности |
| `position` | INT | Порядок отображения |
| `settings` | JSON | Настройки воркера |
| `hidden` | INT | Флаг видимости |

```php
// Примеры запросов
use Seiger\sTask\Models\sWorker;

// Получить активных воркеров
$active = sWorker::active()->get();

// Получить воркеров по scope
$commerce = sWorker::byScope('scommerce')->get();

// Получить упорядоченных воркеров
$ordered = sWorker::ordered()->get();
```

### Таблица s_tasks

Хранит записи задач:

| Колонка | Тип | Описание |
|---------|-----|----------|
| `id` | BIGINT | Первичный ключ |
| `identifier` | VARCHAR | Идентификатор воркера |
| `action` | VARCHAR | Название действия |
| `status` | INT | Статус выполнения (10-50) |
| `message` | VARCHAR | Сообщение статуса |
| `started_by` | INT | ID пользователя |
| `meta` | JSON | Данные задачи |
| `result` | TEXT | Данные результата/путь к файлу |
| `start_at` | TIMESTAMP | Запланированный старт |
| `finished_at` | TIMESTAMP | Время завершения |
| `attempts` | INT | Попытки выполнения |
| `max_attempts` | INT | Максимум попыток |
| `priority` | VARCHAR | Приоритет задачи |
| `progress` | INT | Процент прогресса |

```php
// Примеры запросов
use Seiger\sTask\Models\sTaskModel;

// Получить ожидающие задачи
$pending = sTaskModel::pending()->get();

// Получить выполняющиеся задачи
$running = sTaskModel::running()->get();

// Получить завершенные задачи
$completed = sTaskModel::completed()->get();

// Получить неудачные задачи
$failed = sTaskModel::failed()->get();

// Получить задачи по идентификатору
$productTasks = sTaskModel::byIdentifier('product')->get();

// Получить задачи по действию
$imports = sTaskModel::byAction('import')->get();

// Получить высокоприоритетные задачи
$urgent = sTaskModel::highPriority()->get();
```

## Следующие шаги

- Прочитайте [Руководство разработчика](./developers.md) для создания собственных воркеров
- Исследуйте [Руководство админ интерфейса](./admin.md) для управления задачами и воркерами
- Проверьте [GitHub репозиторий](https://github.com/Seiger/sTask) для обновлений и примеров

## Решение проблем

### Задачи не обрабатываются

1. Проверьте активны ли воркеры:
```php
$worker = \Seiger\sTask\Models\sWorker::where('identifier', 'product')->first();
if (!$worker->active) {
    echo "Воркер неактивен!\n";
}
```

2. Проверьте статус задачи:
```php
$task = \Seiger\sTask\Models\sTaskModel::find(1);
echo "Статус: {$task->status_text}\n";
echo "Сообщение: {$task->message}\n";
```

3. Проверьте логи:
```php
$logs = $task->getErrorLogs();
foreach ($logs as $log) {
    echo $log['message'] . "\n";
}
```

### Воркеры не обнаружены

1. Запустите обнаружение вручную:
```console
php artisan stask:discover-workers --clean
```

2. Проверьте реализует ли класс `TaskInterface`:
```php
class MyWorker implements \Seiger\sTask\Contracts\TaskInterface
{
    // ...
}
```

3. Проверьте что ваш воркер находится в валидном Composer пакете и не в системных пространствах имен (Illuminate, Symfony, и т.д.)

### Проблемы с правами доступа

Если вы получаете ошибки прав доступа для storage:

```console
chmod -R 755 storage/stask
chown -R www-data:www-data storage/stask
```

Или через PHP:
```php
$path = storage_path('stask');
if (!is_writable($path)) {
    chmod($path, 0755);
}
```

# Довідник

Ця сторінка фіксує стабільні сигнали пакета, які потрібні dDocs, інтеграціям і
агентам міграції.

## Service Provider

`Seiger\sTask\sTaskServiceProvider` реєструє модуль менеджера Evolution,
маршрути, міграції, views, переклади, Livewire компонент, консольну команду і
EvoUI presets таблиць.

| Сигнал | Призначення |
| --- | --- |
| `cms.settings` | Налаштування менеджера з `config/sTaskCheck.php`. |
| `stask.tasks.table` | EvoUI preset завдань з `config/tasks/table.php`. |
| `stask.workers.table` | EvoUI preset воркерів з `config/workers/table.php`. |
| `stask.logs.table` | EvoUI preset логів з `config/logs/table.php`. |
| `tasks.table` | Назва publishable конфігу таблиці завдань. |
| `workers.table` | Назва publishable конфігу таблиці воркерів. |
| `logs.table` | Назва publishable конфігу таблиці логів. |
| `sTaskAlias` | Alias package plugin bridge. |
| `sTaskCheck` | Джерело налаштувань реєстрації модуля. |

## Runtime класи

| Клас | Відповідальність |
| --- | --- |
| `DashboardData` | Картки панелі, останні задачі і останні помилки. |
| `MetricsService` | Метрики runtime і статистика. |
| `PublishAssets` | Публікує manager assets пакета і тримає EvoUI-facing файли актуальними. |
| `ModulePanel` | Livewire EvoUI панель менеджера. |
| `TasksTableData` | Дані таблиці/списку задач. |
| `WorkersTableData` | Дані таблиці/списку воркерів. |
| `LogsTableData` | Дані логів і деталей задачі. |
| `TaskProgress` | Нормалізація прогресу і повідомлень задачі. |
| `WorkerDiscovery` | Пошук worker класів з урахуванням namespace exclusions. |
| `WorkerService` | Створення і керування запуском задач воркерів. |
| `BaseWorker` | Базовий клас кастомних воркерів. |
| `ArtisanWorker` | Вбудований воркер для дозволених Artisan команд. |
| `ComposerUpdateWorker` | Вбудований воркер для Composer maintenance. |
| `TaskWorker` | Консольний worker, який обробляє чергу задач. |

## Exceptions

| Exception | Коли виникає |
| --- | --- |
| `WorkerNotFoundException` | Ідентифікатор воркера не знайдено. |
| `WorkerClassNotFoundException` | Клас воркера не autoloadиться. |
| `WorkerInvalidInterfaceException` | Клас не відповідає worker contract. |

## Worker Contract

Воркер має оголошувати `identifier()`, `scope()`, `title()`, `description()` і
`settings()`. Мапа `handles` описує доступні task actions. Discovery може
пропускати namespace через `excluded_namespaces`.

## Console Commands

```console
php artisan stask:worker
php artisan stask:publish
php artisan stask:publish --no-prune
```

Повна сигнатура publish command:
`stask:publish {--no-prune : Do not delete existing files before publish}`.

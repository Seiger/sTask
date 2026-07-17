# CLI, статусы и маршруты

## CLI

### `stask:worker`

```bash
php artisan stask:worker
```

Вариантов нет. Создаёт запланированные задачи, контролирует демон-работников, последовательно обрабатывает все готовые задачи в очереди, выполняет cleanup-if-idle и возвращает выходной код 0, даже если отдельные задачи не выполняются (исключения фиксируются для каждой задачи).

Назначенный врач: каждую минуту.

### `stask:publish`

```bash
php artisan stask:publish
```

Копирует пакетные ресурсы для публичного `assets/site`; использует `Filesystem`. После обновления пакета запускайте снова.

### Связанные команды Эволюции

```bash
php artisan migrate
php artisan package:discover
php artisan route:list --path=stask
php artisan schedule:list
php artisan cache:clear-full
```

Точная их доступность зависит от версии Evolution CMS.

## Статус задачи

| Код | Константа PHP | Текст API | Активно | Финал |
| ---: | --- | --- | --- | --- |
| 10 | `TASK_STATUS_QUEUED` | Ожидается | Да | нет |
| 30 | `TASK_STATUS_PREPARING` | Подготовка | Да | нет |
| 50 | `TASK_STATUS_RUNNING` | Бег | Да | нет |
| 80 | `TASK_STATUS_FINISHED` | завершено | нет | Да |
| 100 | `TASK_STATUS_FAILED` | неудача | нет | Да |

Неизвестный код → `unknown`.

## Государство-супервизор

| Ценность | Значение | rerequiredesLaunch |
| --- | --- | --- |
| `healthy` | Доступен процесс | нет |
| `starting` | Запуск в процессе | нет |
| `degraded` | Состояние здоровья ухудшилось | Да |
| `failed` | Проверка и неудача запуска | Да |
| `stopped` | Процесс остановлен/не найден | Да |

## Значения расписания

Типы: `manual`, `once`, `periodic`, `regular`, `supervisor`.

Частоты:

- периодические: `minutely`, `every_5min`, `every_15min`, `every_30min`, `hourly`, `daily`, `weekly`, `monthly`;
- обычный: `every_5min`, `every_15min`, `every_30min`, `hourly`.

## Маршруты менеджеров

У всех есть префикс `/stask`, название маршрута префикс `sTask.` и промежуточное `mgr`. Полная таблица с методами и контекстом полезной нагрузки: [Маршруты, файлы прогресса и загрузки](../04-development/routes-and-progress.md).

Ключ:

```text
GET  /stask
POST /stask/worker/{identifier}/run/{action}
GET  /stask/task/{id}/progress
GET  /stask/task/{id}
GET  /stask/performance/summary
POST /stask/cache/clear
```

## Публичные курсы PHP

| Класс | Роль |
| --- | --- |
| `Seiger\sTask\sTask` | Обслуживание фасада |
| `Seiger\sTask\Facades\sTask` | Фасад Laravel |
| `Seiger\sTask\Workers\BaseWorker` | Рабочая база |
| `Seiger\sTask\Contracts\TaskInterface` | Трудовой контракт |
| `Seiger\sTask\Contracts\SupervisorWorkerInterface` | Демонические возможности |
| `Seiger\sTask\Support\SupervisorStatus` | Снимок неизменного здоровья |
| `Seiger\sTask\Enums\SupervisorState` | Деймон говорит |
| `Seiger\sTask\Models\sTaskModel` | задача Eloquent model |
| `Seiger\sTask\Models\sWorker` | worker Eloquent модель |
| `Seiger\sTask\Models\sSupervisorState` | Модель живого состояния |
| `Seiger\sTask\Services\TaskProgress` | Прогресс файла |
| `Seiger\sTask\Services\WorkerDiscovery` | Обнаружение реестра |
| `Seiger\sTask\Services\WorkerService` | Разрешение/кэш |
| `Seiger\sTask\Services\SupervisorService` | Надзор за жизненным циклом |
| `Seiger\sTask\Services\MetricsService` | Статистика/метрики кэша |

## Исключения

- `WorkerNotFoundException`;
- `WorkerClassNotFoundException`;
- `WorkerInvalidInterfaceException`.

Каждое исключение разрешения имеет контекстную полезность для логирования. Уровень менеджера/API может преобразовывать их в JSON-сообщения; Не показывайте конечного пользователя Stack Trace.

## Помощники по форматированию в интерфейсе

- `niceCount(int|float)` — компактный счёт;
- `niceSize(bytes)` — читаемый размер;
- `niceEta(seconds)` — продолжительность/время прибытия/время работы.

Это Evolution/evo-ui помощники во время выполнения, а не фасад sTask.

# Архитектура и жизненный цикл

## Основные понятия

- **Worker** — PHP-класс, выполняющий одно или несколько actions.
- **Task** — сохранённая попытка выполнить action выбранного worker identifier.
- **Action** — строка `make`, `sync_stock` и т. п.; `BaseWorker` вызывает соответствующий метод `taskMake()` или `taskSyncStock()`.
- **Schedule** — JSON в `s_workers.settings.schedule`.
- **Progress** — файловый snapshot и журнал в `storage/stask/{taskId}.log`.
- **Metadata** — входные параметры задания в поле `meta`.
- **Result** — структурированный результат, записанный воркером.

## Выполнение

`stask:worker`:

1. читает активные воркеры и их расписания;
2. создаёт отсутствующие scheduled tasks или выполняет supervisor pass;
3. выбирает queued tasks с `start_at IS NULL OR start_at <= now()`;
4. проверяет worker record, PHP-класс и `TaskInterface`;
5. переводит task в running, устанавливает `start_at` и увеличивает `attempts`;
6. вызывает action;
7. завершает task автоматически, если воркер не сделал этого сам;
8. при exception переводит task в failed и пишет диагностический progress.

Готовые задания загружаются без batch limit и выполняются последовательно.

## Статусы

| Код | Константа | API-текст | Значение |
| ---: | --- | --- | --- |
| 10 | `TASK_STATUS_QUEUED` | `pending` | ожидает запуска |
| 30 | `TASK_STATUS_PREPARING` | `preparing` | промежуточная подготовка |
| 50 | `TASK_STATUS_RUNNING` | `running` | выполняется |
| 80 | `TASK_STATUS_FINISHED` | `completed` | успешно завершено |
| 100 | `TASK_STATUS_FAILED` | `failed` | ошибка или emergency stop |

Отдельного persisted-статуса cancelled нет. Автоматический requeue failed-заданий также отсутствует: retry policy реализует прикладной воркер или интегратор.

## Live progress

Менеджер периодически запрашивает progress endpoint по HTTP, увеличивает интервал при отсутствии изменений и прекращает polling после final status или повторных сетевых ошибок. Это не SSE и не WebSocket.

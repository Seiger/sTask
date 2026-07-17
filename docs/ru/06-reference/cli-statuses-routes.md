# CLI, статусы и маршруты

## Команды

```bash
php artisan stask:worker
php artisan stask:publish
php artisan stask:publish --no-prune
```

`stask:worker` не имеет options: создаёт scheduled tasks, проверяет supervisor workers и последовательно обрабатывает готовую очередь. `stask:publish` публикует manager assets; `--no-prune` сохраняет уже существующие файлы.

## Статусы заданий

| Код | PHP-константа | API-текст | Final |
| ---: | --- | --- | --- |
| 10 | `TASK_STATUS_QUEUED` | pending | нет |
| 30 | `TASK_STATUS_PREPARING` | preparing | нет |
| 50 | `TASK_STATUS_RUNNING` | running | нет |
| 80 | `TASK_STATUS_FINISHED` | completed | да |
| 100 | `TASK_STATUS_FAILED` | failed | да |

## Supervisor states

`healthy`, `starting`, `degraded`, `failed`, `stopped`. Live state хранится в `s_supervisor_states`; meaningful lifecycle events дополнительно создают записи task history.

## Manager routes

Все маршруты используют prefix `/stask`, имена `sTask.*` и middleware `mgr`:

- `sTask.index` — manager shell;
- `sTask.task.details` — отдельные детали задания;
- `sTask.worker.task.run` — запуск action воркера;
- `sTask.task.progress` — progress snapshot/history;
- `sTask.task.download` — загрузка результата;
- `sTask.task.upload` и chunk routes — загрузка входных файлов;
- `sTask.performance.summary` — performance summary.

Это manager endpoints. Не публикуйте их как внешний API без отдельной авторизации и проверки permissions.

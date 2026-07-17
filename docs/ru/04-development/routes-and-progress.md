# Маршруты, файлы прогресса и загрузки

## Статус API

Все нижеописанные маршруты находятся в группе менеджера промежуточного программного обеспечения `mgr`. Это внутренняя HTTP-поверхность менеджера, а не публичный REST API для внешних клиентов. Используйте аутентификацию сессии и CSRF для POST.

## Маршруты

| Метод | Путь | Название маршрута | Цель |
| --- | --- | --- | --- |
| GET | `/stask` | `sTask.index` | Оболочка модуля |
| GET | `/stask/stats` | `sTask.stats` | Counts |
| ПОСТ | `/stask/task` | `sTask.task.create` | Создание задач фасада |
| ПОСТ | `/stask/task/store` | `sTask.task.store` | Создать псевдоним |
| GET | `/stask/task/{id}` | `sTask.task.show` | Полная информация о задаче |
| ПОСТ | `/stask/worker/{identifier}/run/{action}` | `sTask.worker.task.run` | Create + Launch Worker |
| GET | `/stask/task/{id}/progress` | `sTask.task.progress` | Снимок прогресса/история |
| GET | `/stask/task/{id}/download` | `sTask.task.download` | Результат скачать |
| ПОСТ | `/stask/task/{id}/upload` | `sTask.task.upload` | Загрузка, привязанная к задаче |
| ПОСТ | `/stask/worker/{identifier}/upload` | `sTask.worker.upload` | Загрузка предзадачного работника |
| ПОСТ | `/stask/clean` | `sTask.clean` | Удалить старые завершённые задачи |
| GET | `/stask/server-limits` | `sTask.serverLimits` | Ограничения на загрузку в PHP |
| GET | `/stask/workers` | `sTask.workers` | Обнаружить и перенаправить |
| ПОСТ | `/stask/worker/clean-orphaned` | `sTask.worker.clean` | Удалить отсутствующие классы |
| ПОСТ | `/stask/worker/activate` | `sTask.worker.activate` | Активировать по идентификатору |
| ПОСТ | `/stask/worker/deactivate` | `sTask.worker.deactivate` | Деактивация по идентификатору |
| GET | `/stask/performance/summary` | `sTask.performance.summary` | Сводка метрик |
| GET | `/stask/performance/workers` | `sTask.performance.workers` | Статистика рабочих |
| GET | `/stask/performance/alerts` | `sTask.performance.alerts` | Оповещения |
| GET | `/stask/cache/stats` | `sTask.cache.stats` | Статистика рабочего кэша |
| ПОСТ | `/stask/cache/clear` | `sTask.cache.clear` | Очистить рабочий кэш |

## Запуск

```http
POST /stask/worker/search_index/run/make
Content-Type: application/json
X-CSRF-TOKEN: ...

{"batch_size":100,"force":false}
```

Контроллер берёт вложенные `options` или всё тело, удаляет `_token` и `options`, разрешает активного работника и вызывает `createTask()`.

Успешный ответ:

```json
{"success":true,"id":123,"message":"Task created successfully"}
```

HTTP-код может оставаться 200 даже при `success=false`; клиент должен проверить флаг JSON.

После того как контроллер отклика использует `fastcgi_finish_request()` или синхронный резерв, затем пытается запустить `stask:worker`. Это не гарантирует отдельного процесса очереди на всех SAPI.

## Конечная точка прогресса

```http
GET /stask/task/123/progress?include_log=0
Accept: application/json
```

Success:

```json
{
  "success": true,
  "code": 200,
  "id": 123,
  "status": "running",
  "progress": 42,
  "processed": 420,
  "total": 1000,
  "eta": "37s",
  "message": "Обрабатываю пакет",
  "log_lines": []
}
```

Без `include_log=0` endpoint добавляет последние 50 сообщений. Недействительное удостоверение — 400; Отсутствующий файл прогресса — 404.

## Формат файла

Путь:

```text
core/storage/stask/{taskId}.log
```

Каждая строка, предназначенная только для приложения:

```text
status|progress|processed|total|eta|message
```

`readProgress()` читает последнюю действительную строку; `readLog()` извлекает сообщение из последних N строк. Ошибка в записи намеренно не приводит к краху бизнес-задачи, поэтому отсутствие живого прогресса не доказывает, что задача не выполнена.

## Уборка

Когда отсутствуют задачи в очереди/подготовке/выполнении, `stask:worker` удаляет `*.json` старше 24 часов и временный JSON старше 10 часов. Текущий `TaskProgress` фактически использует `*.log`, поэтому эти лог-файлы не удаляются автоматически этим циклом. Создайте отдельную политику удержания для `storage/stask/*.log` после согласования с требованиями аудита.

## Загрузка/скачать

У контроллера есть обычные и фрагментированные пути загрузки, конечные точки сервера и разрешённые расширения, разрешённые для конкретных работников. Файлы хранятся под `storage/stask/uploads`.

Правила интегратора:

- Не полагайтесь только на расширение;
- проверить MIME и фактический формат в worker;
- предельный размер и количество чанков;
- генерировать серверные имена файлов;
- запрещает обход пути;
- удаление временных/результатных файлов по политике сохранения;
- Не возвращать путь загрузки, пока файл не появится и не принадлежит Задаче.

Точная загрузка зависит от контракта виджета/работника; Не думайте о эндпоинте как о универсальном файловом API.

## Разрешения

`sTaskController::index()` и `show()` явно проверяют разрешение `stask`; Часть с методами действий опирается только на `mgr`. Инфраструктурно ограничите маршрутизацию сессии менеджера модуля на сессию, а в пользовательских контроллерах повторяйте проверку разрешения на разрушительные операции.

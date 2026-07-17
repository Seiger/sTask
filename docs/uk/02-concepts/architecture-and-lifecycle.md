# Архітектура і життєвий цикл

## Компоненти

| Компонент | Відповідальність |
| --- | --- |
| `sWorker` | реєстраційний запис воркера, class, active/hidden, position, JSON settings |
| `TaskInterface` | мінімальний metadata/UI contract воркера |
| `BaseWorker` | config, schedules, task creation, action dispatch, progress і finalization |
| `sTaskModel` | persisted task, status, meta/result, timestamps і scopes |
| `TaskWorker` | створення scheduled tasks і послідовне виконання готової черги |
| `TaskProgress` | append-only live progress у `storage/stask/{id}.log` |
| `WorkerDiscovery` | пошук concrete `TaskInterface` класів у Composer classmap |
| `WorkerService` | resolution, validation і cache worker instances |
| `SupervisorService` | serialized inspection/start/restart довготривалих процесів |
| EvoUI + Livewire | manager tables, filters, modal windows і HTTP polling progress |

## Терміни

- **Worker** — PHP-клас, який знає, як виконувати одну або кілька actions.
- **Task** — persisted спроба виконати action конкретного worker identifier.
- **Action** — рядок на кшталт `make` або `sync_stock`; `BaseWorker` перетворює його на `taskMake()` або `taskSyncStock()`.
- **Schedule** — JSON у `s_workers.settings.schedule`, за яким `stask:worker` підтримує наступний queued task.
- **Progress** — volatile snapshot/history у файловому `.log`; поле `s_tasks.progress` автоматично не синхронізується кожним `pushProgress()`.
- **Metadata (`meta`)** — нормалізовані вхідні параметри task, cast моделі `array`.
- **Result** — завершальний payload або шлях, який записав worker; тип БД `LONGTEXT`, cast моделі `array`.
- **Message** — короткий persisted стан/помилка в `s_tasks.message`; live history зберігається окремо.

## Створення

Є два основні шляхи:

1. `sTask::create($identifier, $action, $data, $priority, $userId)`.
2. `$worker->createTask($action, $options)` у `BaseWorker`.

Обидва нормалізують meta рекурсивним сортуванням associative keys і шукають активний duplicate за `identifier + action + normalized meta`. Активними є status `10`, `30`, `50`.

Це захищає лише від однакових активних records. Це не глобальний distributed lock і не заміна ідемпотентності business operation.

## Виконання

`stask:worker` виконує такі кроки:

1. читає всі active workers;
2. для enabled schedules створює відсутні наступні tasks або виконує supervisor pass;
3. вибирає всі queued tasks, де `start_at IS NULL OR start_at <= now()`;
4. для кожного task знаходить active worker record і class;
5. перевіряє `TaskInterface`;
6. ставить status `running`, `start_at = now()`, збільшує `attempts`;
7. викликає `invokeAction()`;
8. якщо worker не фіналізував task, ставить `finished` автоматично;
9. при exception ставить `failed` і записує error progress;
10. коли активних tasks немає, видаляє progress-файли старші 24 годин.

Поточна команда завантажує всі готові queued rows без batch limit і обробляє послідовно. Плануйте обсяг так, щоб один cron-прохід не зависав на невизначений час.

## Status

| Код | Константа | Текст | Значення |
| ---: | --- | --- | --- |
| 10 | `TASK_STATUS_QUEUED` | `pending` | очікує часу/worker pass |
| 30 | `TASK_STATUS_PREPARING` | `preparing` | проміжний стан для прикладного коду |
| 50 | `TASK_STATUS_RUNNING` | `running` | action виконується або вважається активною |
| 80 | `TASK_STATUS_FINISHED` | `completed` | успішний final state |
| 100 | `TASK_STATUS_FAILED` | `failed` | помилка або emergency stop |

Окремого persisted cancelled status немає. `isFinished()` повертає `true` лише для `80` та `100`.

## Timestamps і тривалість

- `created_at` — коли створено record.
- `start_at` — запланований час до виконання, а після `markAsRunning()` — фактичний початок.
- `finished_at` — finalization.
- `updated_at` — остання зміна record.
- accessor `duration` — секунди від `start_at` до `finished_at`; для running task — від `start_at` до поточного часу.

Через подвійне значення `start_at` майбутній queued task показує запланований старт, а completed task — фактичний start, встановлений під час запуску.

## Retry

`markAsRunning()` збільшує `attempts`. `canRetry()` істинний для failed task, доки `attempts < max_attempts`. Проте стандартний `TaskWorker` не повертає failed task автоматично в queued і не має окремої retry-команди. Retry policy повинен реалізувати інтегратор або worker. Не обіцяйте користувачам автоматичні повтори лише через `max_attempts = 3`.

## Progress і live UI

`pushProgress()` додає один pipe-separated рядок до `.log`. JavaScript watcher:

- стартує з інтервалу 1.2 секунди;
- збільшує delay до 25 секунд, якщо snapshot не змінюється;
- перевіряє раз на 5 секунд у hidden/невидимій вкладці;
- не робить паралельних запитів;
- зупиняється на `finished`, `failed`, `completed` або після п’яти network failures;
- після final status просить Livewire оновити поверхню.

Це HTTP polling, не push transport.

# Інтерфейс менеджера

Модуль має п’ять Livewire-вкладок: **Панель**, **Завдання**, **Воркери**, **Логи**, **Статистика**. Перемикання відбувається без повного перезавантаження manager frame. Початкову вкладку можна передати query-параметром `get`; невідоме значення замінюється на `dashboard`.

## Доступ

Відкривати module shell і task details може manager user з permission `stask`. Усі HTTP routes знаходяться в middleware group `mgr`. Деякі action endpoints покладаються лише на `mgr` middleware і не викликають `hasPermission('stask')` повторно, тому не публікуйте `/stask/*` за межі manager authentication.

## Спільні правила EvoUI tables

Таблиці **Завдання**, **Воркери**, **Логи** підтримують:

- пошук;
- per page: 15, 30, 50, 100, 200 (default 30);
- table/list view;
- сортування лише за колонками, позначеними `sortable` у preset;
- multi-select та date-range filters;
- row actions;
- подвійний клік у всіх рядкових представленнях: на Панелі, у Завданнях і Логах він відкриває деталі task, а у Воркерах — modal редагування.

Пошук і фільтри застосовуються на сервері. List view змінює представлення, а не набір даних.

## Панель

![Панель sTask із ключовими показниками](../../assets/screenshots/stask-dashboard.png)

### Картки

Панель показує counts queued, running, finished, failed, workers та active workers. Далі йдуть останні tasks і, за наявності, окрема таблиця останніх errors.

### Останні tasks

Колонки: ID, Воркер, Дія, Статус, Прогрес, Початок виконання, Дії. Для active task рядок отримує progress URL, а `stask-module.js` періодично читає snapshot без log history (`include_log=0`).

Подвійний клік або іконка `eye` відкриває modal з основними полями, task log, meta і result. Вміст readonly.

### Live progress

Progress bar/значення і message оновлюються через HTTP polling. Підтриманий безпечний inline Markdown: backticks, bold, strikethrough, emphasis. HTML спочатку escape-иться. На final status Livewire оновлює панель один раз.

## Завдання

![Таблиця завдань sTask](../../assets/screenshots/stask-tasks.png)

### Пошук

Шукає по numeric ID, `identifier`, `action`, `message`.

### Фільтри

- **Воркер** — searchable список з людськими `worker->title`, а не сирими identifiers.
- **Дія** — distinct actions з БД.
- **Статус** — queued, preparing, running, completed, failed.
- **Користувач** — manager users, які вже запускали tasks, плюс `system` для `started_by IS NULL OR <= 0`.
- **Діапазон створення** — межі включно від start-of-day до end-of-day.

Priority та Attempts не є актуальними колонками або фільтрами цієї вкладки. Вони залишаються полями runtime/schema для сумісності, але не документуються як UI control.

### Колонки

| Колонка | Значення |
| --- | --- |
| ID | `#id`; newest first за default |
| Воркер | localized/human title або identifier fallback |
| Дія | action code |
| Статус | badge за numeric status |
| Прогрес | `0–100%`; active row може оновлюватися live |
| Запустив | username або `system` |
| Повідомлення | persisted message з safe Markdown rendering |
| Початок виконання | `start_at`; для future queued task це запланований час |
| Завершено | `finished_at` |

### Дії

- `eye` — modal details.
- `player-eject` — emergency stop для queued/preparing/running.

Emergency stop ставить status failed, `finished_at = now()` і message «Завдання аварійно зупинено». Він **не завершує PHP/OS process**. Якщо process продовжує роботу, він може ще змінити дані або progress file.

Подвійний клік по рядку відкриває details modal.

## Воркери

![Реєстр воркерів із розкладами та supervisor uptime](../../assets/screenshots/stask-workers.png)

### Пошук і фільтри

Пошук: identifier, scope, class. Фільтри:

- active/inactive;
- class available/missing;
- visible/hidden.

### Колонки

- Identifier.
- Воркер — title з class instance.
- Опис — excerpt до 96 символів.
- Розклад — chip; для healthy supervisor badge показує uptime через `niceEta()`.
- Кількість tasks — `niceCount()` (compact localized number).
- Остання дія.
- Останній запуск — timestamp останнього task record.

Hidden — це manager visibility flag; worker record не видаляється. Inactive worker не можна запускати і scheduler його пропускає.

### Toolbar і row actions

- `database-cog` — discover + rescan + clean orphaned + clear worker cache.
- `player-play` — запускає selected/row worker лише якщо active, class існує і є `taskMake()`.
- `edit` — modal settings.
- `power` — active toggle.
- `eye/eye-off` — visibility toggle.

Refresh registry може видалити records, whose class більше не існує. Перед натисканням у production перевірте, що Composer autoload повний і deploy не перебуває в проміжному стані.

### Modal воркера

Readonly: title, identifier, scope, class, description. Editable: active, hidden, position, schedule, додатковий JSON settings payload.

Додатковий JSON не повинен містити ключ `schedule`: при save він вилучається і замінюється значеннями form. Invalid JSON не зберігається; provider залишає попередні custom settings.

Для supervisor-capable class modal також показує:

- ключ;
- state badge;
- PID;
- heartbeat;
- час роботи (`niceEta`);
- останню діагностику;
- останній перехід.

Supervisor option прихована, якщо class не реалізує `SupervisorWorkerInterface`.

## Логи

![Журнал виконання завдань](../../assets/screenshots/stask-logs.png)

Це не окрема log table: вкладка читає `s_tasks` і показує task history більш детально.

### Пошук і фільтри

Пошук: ID, identifier, action, message. Фільтри: worker title, action, status, user включно з `system`, created date range.

### Колонки

ID-link, worker title, identifier, action, status, progress, started by, created, start, finished, updated, **Час роботи**.

**Час роботи** використовує task duration: для final task `finished_at - start_at`, для active task `now - start_at`. Форматування виконує `niceEta()`. Назва translation key спільна з supervisor uptime, але тут це тривалість task.

ID відкриває окрему сторінку details. Подвійний клік відкриває readonly modal з message, meta, result і worker class.

## Статистика

![Статистика sTask за останні 24 години](../../assets/screenshots/stask-statistics.png)

Показує performance cards за останні 24 години, alerts і worker cache stats.

Реальні на поточній реалізації:

- task counts;
- success/error rate з status records;
- worker grouping;
- cache hits, misses, evictions, hit rate, cache size;
- очищення worker cache.

Обмеження: `MetricsService` поки повертає `0` для average duration, average memory і total execution time при агрегації з task records; common errors також порожній placeholder. Не використовуйте ці значення як production SLI без зовнішньої телеметрії.

`niceSize()` використовується для human-readable memory values там, де є реальне byte value; `niceCount()` — для counts; `niceEta()` — для seconds/duration.

## Безпека даних

Meta, result, message і progress log видно manager users з доступом до модуля. Не передавайте passwords, API tokens, session strings або персональні дані, якщо їх не потрібно показувати оператору. Upload/download endpoints мають worker-specific validation, але worker усе одно повинен перевіряти тип, розмір і зміст файлу.

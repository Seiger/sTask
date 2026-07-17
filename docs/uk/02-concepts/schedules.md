# Розклади

Schedule зберігається в `s_workers.settings.schedule`. Редактор на вкладці **Воркери** нормалізує payload до полів `enabled`, `type`, `datetime`, `frequency`, `time`, `start_time`, `end_time`.

## Manual

```json
{"enabled": false, "type": "manual"}
```

Автоматичний task не створюється. Запуск виконує менеджерська кнопка або PHP-код.

## Once

```json
{
  "enabled": true,
  "type": "once",
  "datetime": "2026-07-20 03:30:00"
}
```

`stask:worker` створює task лише якщо datetime ще в майбутньому і worker не має incomplete task. Якщо час уже минув до першого scheduler pass, task не створиться.

## Periodic

Підтримані frequency:

| Frequency | Поля | Наступний запуск |
| --- | --- | --- |
| `minutely` | time не потрібен | наступна хвилина |
| `every_5min` | time не потрібен | найближча хвилина, кратна 5 |
| `every_15min` | time не потрібен | найближча хвилина, кратна 15 |
| `every_30min` | time не потрібен | найближча хвилина, кратна 30 |
| `hourly` | `time = *:MM` | наступна година/хвилина |
| `daily` | `time = HH:MM` | сьогодні або завтра |
| `weekly` | `time`, `days[]` | наступний вибраний день |
| `monthly` | `time` | поточний день місяця; UI не надає окремого day field |

Приклад щодня о 02:15:

```json
{
  "enabled": true,
  "type": "periodic",
  "frequency": "daily",
  "time": "02:15"
}
```

Практичне обмеження UI 2.x: modal має time, але не показує редактор `days` для weekly і `day` для monthly. Такі значення можна зберегти лише через JSON settings/код; перед production перевірте їх реальним `stask:worker`.

## Regular у часовому вікні

```json
{
  "enabled": true,
  "type": "regular",
  "frequency": "every_15min",
  "start_time": "08:00",
  "end_time": "18:00"
}
```

Доступні інтервали: `every_5min`, `every_15min`, `every_30min`, `hourly`. Вікно повинно бути в межах одного календарного дня: якщо `end_time < start_time`, наступний час не розраховується. Overnight window на кшталт `22:00–06:00` поточною реалізацією не підтриманий.

Пошук наступного slot починається з `start_time` і додає interval, доки candidate не стане пізнішим за `now`. Після кінця вікна функція повертає `null`; task для наступного дня у цьому проході не створюється. Це важливе operational обмеження: перевірте потрібну поведінку на межі дня.

## Supervisor

`type = supervisor` доступний у modal лише для class, що реалізує `SupervisorWorkerInterface`. Він не створює health-check task щохвилини. Scheduler оновлює один live-state row, а task rows створюються лише для meaningful lifecycle events.

Докладно: [Supervisor-процеси](supervisor.md).

## Правило одного incomplete task

Для once/periodic/regular scheduler перевіряє relation worker tasks зі scope `incomplete()` і не створює наступний task, якщо є queued/preparing/running record. Довгий або завислий task таким чином блокує подальший розклад цього identifier.

Emergency stop звільняє record, переводячи його у failed, але не вбиває OS process. Спочатку встановіть, чи процес ще працює, і лише потім запускайте наступний task.

## Cron і Laravel scheduler

Provider додає команду:

```php
$schedule->command(TaskWorker::class)->everyMinute();
```

Це визначення не запускає scheduler самостійно. Інфраструктура має виконувати `php artisan schedule:run` щохвилини або тримати `schedule:work` під зовнішнім process supervisor.

## Типові помилки

- **Нічого не створюється** — worker inactive, schedule disabled, немає `taskMake()`, invalid time або вже є incomplete task.
- **Once пропущений** — scheduler уперше побачив datetime після того, як вона стала past.
- **Weekly не працює** — відсутній `days` array.
- **Regular зупинився ввечері** — поточний алгоритм не переносить next slot на наступний день.
- **Дублікати** — паралельно запущено кілька `stask:worker`; duplicate check не є atomic distributed lock.

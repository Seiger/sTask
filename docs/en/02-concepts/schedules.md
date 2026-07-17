# Schedules

Schedule is stored in `s_workers.settings.schedule`. The editor on the **Workers** tab normalizes payload to fields `enabled`, `type`, `datetime`, `frequency`, `time`, `start_time`, `end_time`.

## Manual

```json
{"enabled": false, "type": "manual"}
```

An automatic task is not created. The launch is performed by a manager button or PHP code.

## Once

```json
{
  "enabled": true,
  "type": "once",
  "datetime": "2026-07-20 03:30:00"
}
```

`stask:worker` creates a task only if the datetime is still in the future and the worker does not have an incomplete task. If the time has already elapsed before the first scheduler pass, the task will not be created.

## Periodic

Supported frequencies:

| Frequency | Fields | Next Launch |
| --- | --- | --- |
| `minutely` | Time Not Required | next minute |
| `every_5min` | Time Not Required | nearest minute multiples of 5 |
| `every_15min` | Time Not Required | nearest minute multiples of 15 |
| `every_30min` | Time Not Required | nearest minute in multiples of 30 |
| `hourly` | `time = *:MM` | next hour/minute |
| `daily` | `time = HH:MM` | Today or Tomorrow |
| `weekly` | `time`, `days[]` | Next Chosen Day |
| `monthly` | `time` | current day of the month; UI does not provide a separate day field |

Example every day at 02:15:

```json
{
  "enabled": true,
  "type": "periodic",
  "frequency": "daily",
  "time": "02:15"
}
```

Practical limitation of UI 2.x: modal has time, but does not show the `days` editor for weekly and `day` for monthly. Such values can only be saved via JSON settings/code; Before production, check them with real `stask:worker`.

## Regular in the time window

```json
{
  "enabled": true,
  "type": "regular",
  "frequency": "every_15min",
  "start_time": "08:00",
  "end_time": "18:00"
}
```

Available intervals: `every_5min`, `every_15min`, `every_30min`, `hourly`. The window must be within one calendar day: if `end_time < start_time`, the next time is not calculated. Overnight window like `22:00–06:00` the current implementation is not supported.

Searching for the next slot starts at `start_time` and adds interval until the candidate is later than `now`. After the end of the window, the function returns `null`; task for the next day is not created in this pass. This is an important operational limitation: check the desired behavior at the end of the day.

## Supervisor

`type = supervisor` is only available in the modal for a class that implements `SupervisorWorkerInterface`. It doesn't create a health-check task every minute. Scheduler updates one live-state row, and task rows are created only for meaningful lifecycle events.

Details: [Process Supervisor](supervisor.md).

## Single incomplete task rule

For once/periodic/regular, scheduler checks the relation worker tasks with scope `incomplete()` and does not create the next task if there is a queued/preparing/running record. A long or frozen task thus blocks further scheduling of this identifier.

Emergency stop releases the record, putting it in failed, but does not kill the OS process. First, set if the process is still running, and only then run the next task.

## Cron and Laravel scheduler

Provider adds the command:

```php
$schedule->command(TaskWorker::class)->everyMinute();
```

This definition does not run scheduler on its own. The infrastructure must perform `php artisan schedule:run` every minute or keep `schedule:work` under an external process supervisor.

## Common mistakes

- **Nothing is created** — worker inactive, schedule disabled, no `taskMake()`, invalid time, or there is already an incomplete task.
- **Once skipped** — scheduler first saw the datetime after it became past.
- **Weekly does not work** - `days` array is missing.
- **Regular stopped in the evening** — the current algorithm does not move the next slot to the next day.
- **Duplicates** — several `stask:worker` run in parallel; Duplicate Check is not Atomic Distributed Lock.

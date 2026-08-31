# Перехід з sTask 1.x на 2.x

Це evidence-based checklist, а не автоматичний upgrader. У repository немає повного machine-readable migration contract для всіх сторонніх 1.x workers, тому перевіряйте кожен custom class.

## Що змінило у 2.x surface

- EvoUI/Livewire module з п’ятьма вкладками.
- Table/list views, server filters і readonly detail modals.
- Адаптивний HTTP polling live progress.
- Duplicate suppression для active identifier/action/normalized meta.
- Worker registry refresh і class/title filters.
- Schedule types manual/once/periodic/regular/supervisor.
- Окремий `SupervisorWorkerInterface`, `SupervisorStatus`, `s_supervisor_states`.
- Emergency stop як DB-level failed transition.
- Compact UI: Priority/Attempts прибрані з актуальних columns/filters.

## Перед оновленням

1. Зробіть backup БД, `core/composer.lock`, custom workers і `storage/stask` за потреби audit.
2. Зафіксуйте active tasks; дайте їм завершитися.
3. Інвентаризуйте workers:

   ```sql
   SELECT id, identifier, class, active, settings FROM s_workers ORDER BY id;
   ```

4. Знайдіть custom classes, що реалізують старий contract.
5. Перевірте PHP 8.4 та evo-ui 1.2+.

## Адаптація worker

Рекомендована форма:

```php
final class ExampleWorker extends BaseWorker
{
    public function identifier(): string { return 'example'; }
    public function scope(): string { return 'custom'; }
    public function icon(): string { return '<i data-lucide="settings"></i>'; }
    public function title(): string { return 'Example'; }
    public function description(): string { return 'Example worker'; }

    public function taskMake(sTaskModel $task, array $options = []): void
    {
        // business logic
        $task->update(['progress' => 100, 'result' => ['ok' => true]]);
        $this->markFinished($task, null, 'Done');
    }
}
```

Перевірте:

- action naming `task{StudlyAction}`;
- signatures з `sTaskModel` і array options;
- metadata methods;
- no `$modx`; використовуйте `evo()`/services;
- finalization і exception path;
- progress messages однорядкові;
- secrets не потрапляють у UI.

## Schedule migration

Старі custom schedule keys перенесіть до:

```json
{
  "schedule": {
    "enabled": true,
    "type": "periodic",
    "frequency": "hourly",
    "time": "*:10",
    "datetime": "",
    "start_time": "",
    "end_time": ""
  }
}
```

2.x scheduler очікує `taskMake()` для conventional schedules. Manual/once/periodic/regular не є cron expressions.

## Supervisor migration

Не моделюйте щохвилинну health check як звичайний queued task. Для daemon class додайте `SupervisorWorkerInterface`, стабільний key, read-only inspection, detached start/restart і grace. sTask створюватиме task rows лише для lifecycle events.

## База даних

Запустіть package migrations і перевірте:

- основні tables не були destructive recreation;
- `s_supervisor_states` створена;
- permission `stask` активний;
- existing worker identifiers не змінилися випадково;
- settings JSON валідний.

Поточні базові migrations мають `Schema::create`, тому reinstall-safe behavior залежить від актуального upstream reference. Завжди оновлюйте до перевіреного 2.x commit і запускайте migration smoke на копії production schema.

## Assets і cache

```bash
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

Старі JS/CSS у browser cache можуть ламати tab switching або live progress.

## Acceptance test

- sTask відкривається з permission role;
- кожна з п’яти вкладок працює;
- registry бачить custom workers;
- manual `taskMake` завершується;
- future schedule створює queued task;
- live progress оновлюється polling-ом;
- double click відкриває modal;
- emergency stop позначає тестовий active record failed;
- supervisor state переходить starting → healthy без event flood;
- dDocs показує localized sTask tree.

## Відкат

Відкочуйте code/lock і DB узгоджено. Не видаляйте `s_supervisor_states` або нові fields, доки 2.x process може працювати. Якщо 1.x не розуміє нові settings, збережіть backup і підготуйте explicit transform.

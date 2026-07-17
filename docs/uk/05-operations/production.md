# Production-рекомендації

## Процесна модель

`stask:worker` — run-and-exit command. Вона створює scheduled tasks, обробляє готові queued rows послідовно і завершується. Для постійного обслуговування запускайте Laravel scheduler щохвилини.

```cron
* * * * * cd /var/www/example/core && /usr/bin/flock -n /run/lock/example-stask.lock /usr/bin/php artisan schedule:run >> /var/log/example-scheduler.log 2>&1
```

`flock` — infrastructure guard від overlap. Виберіть writable lock path і перевірте, що один довгий task не блокує критичні unrelated scheduler jobs. Альтернатива — окремий systemd unit/timer з `RefuseManualStart`/locking policy.

## Власник і права

Web user і CLI user повинні мати сумісні права на:

- `core/storage/stask`;
- Laravel cache/storage;
- result upload directory;
- application resources, які змінює worker.

Не запускайте cron від root без потреби: root-created progress/cache files часто ламають manager process.

## Таймаути

Manager run path може викликати `set_time_limit(0)`, а CLI command не встановлює per-task timeout. Таймаути повинні бути в worker:

- HTTP connect/read timeout;
- DB statement timeout;
- maximum items/batches per task;
- deadline у metadata;
- graceful cancellation checkpoints.

Розбивайте довгі jobs на ідемпотентні chunks. Один monolithic task блокує наступний schedule того самого worker identifier.

## Конкурентність

Поточний CLI вибирає queued rows без atomic claim query/`FOR UPDATE SKIP LOCKED`. Тому:

- тримайте один `stask:worker` на інсталяцію;
- застосовуйте зовнішній overlap lock;
- робіть action ідемпотентною;
- для критичних integrations використовуйте domain-level idempotency key;
- не плутайте duplicate lookup з транзакційною гарантією.

Якщо потрібна паралельність, спочатку спроєктуйте claim/lease contract; просте збільшення кількості процесів небезпечне.

## Retry і backoff

`attempts/max_attempts` самі по собі не запускають retry. Production policy має визначити:

- retryable exception classes/status codes;
- maximum attempts;
- `start_at` для backoff;
- dead-letter/manual review;
- duplicate/idempotency behavior;
- alert після остаточної помилки.

## Моніторинг

Мінімальні checks:

- scheduler heartbeat/останній успішний запуск;
- кількість queued tasks і age найстаршого;
- running task age;
- failed rate;
- writeability `storage/stask`;
- disk usage progress/results;
- class-missing/inactive workers;
- `s_supervisor_states.last_seen_at` і heartbeat freshness;
- daemon PID identity.

Вбудована вкладка Статистика не замінює APM: duration/memory/common-errors частково placeholders.

## Логи

Є три різні джерела:

1. `s_tasks.message/meta/result` — persisted audit/state.
2. `storage/stask/{id}.log` — live append-only progress.
3. application logs через Laravel `Log` — exceptions, discovery, launch warnings.

Визначте retention окремо. `cleanOldTasks()` видаляє лише старі finished DB rows; progress `.log`, failed tasks і supervisor state не очищаються.

## Retention

Приклад policy, яку треба реалізувати у вашому operations layer:

- finished tasks: 30–90 днів;
- failed tasks: довше або до incident review;
- progress logs: 7–30 днів після final task;
- upload/results: за business/legal policy;
- supervisor state: один актуальний row на key; orphan rows — після inventory check.

Не видаляйте active task progress. Перед очищенням звіряйте final status і file ownership.

## Supervisor/systemd

sTask Supervisor — application-level lifecycle adapter, не повна заміна systemd/Supervisor. Якщо process одночасно рестартить systemd і sTask adapter, узгодьте єдиного owner, інакше можливі restart loops.

Рекомендовані ролі:

- systemd забезпечує boot, user, resource limits і crash restart;
- worker adapter читає health/heartbeat і повертає diagnostic state;
- лише один із них виконує restart, або обидва мають спільний backoff/lock contract.

## Deploy

Безпечна послідовність:

1. зупинити створення нових jobs або дочекатися завершення критичних;
2. `composer install` з lock;
3. `php artisan migrate --force` після backup і migration review;
4. `package:discover`/autoload rebuild;
5. `stask:publish`;
6. `cache:clear-full`;
7. refresh worker registry після цілісного deploy;
8. smoke `stask:worker`;
9. перевірити manager UI і scheduler.

Не запускайте registry cleanup посеред deploy, коли classes тимчасово відсутні.

## Security

- permission `stask` лише operational roles;
- `run_artisan` окремо для ArtisanWorker;
- whitelist/blacklist небезпечних commands;
- CSRF для POST;
- secrets не в meta/result/message/progress;
- uploads в непублічному storage;
- workers validate input і authorization, навіть якщо route manager-only;
- service account з мінімальними filesystem/DB privileges.

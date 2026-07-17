# Production recommendations

## Process Model

`stask:worker` — run-and-exit command. It creates scheduled tasks, processes ready queued rows sequentially, and exits. For ongoing maintenance, run the Laravel scheduler every minute.

```cron
* * * * * cd /var/www/example/core && /usr/bin/flock -n /run/lock/example-stask.lock /usr/bin/php artisan schedule:run >> /var/log/example-scheduler.log 2>&1
```

`flock` — Infrastructure Guard from Overlap. Select the writable lock path and check that one long task does not block critical unrelated scheduler jobs. An alternative is a separate systemd unit/timer with `RefuseManualStart`/locking policy.

## Ownership and permissions

Web user and CLI user must have compatible permissions to:

- `core/storage/stask`;
- Laravel cache/storage;
- result upload directory;
- application resources that the worker changes.

Don't run cron from root unnecessarily: root-created progress/cache files often break the manager process.

## Timeout

Manager run path can call `set_time_limit(0)`, and the CLI command does not set a per-task timeout. Timeouts must be in the worker:

- HTTP connect/read timeout;
- DB statement timeout;
- maximum items/batches per task;
- deadline in metadata;
- graceful cancellation checkpoints.

Break down long jobs into idempotent chunks. One monolithic task blocks the next schedule of the same worker identifier.

## Competitiveness

The current CLI selects queued rows without atomic claim query/`FOR UPDATE SKIP LOCKED`. Therefore:

- keep one `stask:worker` per installation;
- Use external overlap lock;
- make the action idempotent;
- for critical integrations, use the domain-level idempotency key;
- Do not confuse duplicate lookup with transactional guarantee.

If concurrency is required, first design a claim/lease contract; Simply increasing the number of processes is dangerous.

## Retry and backoff

`attempts/max_attempts` do not run retry by themselves. Production policy should determine:

- retryable exception classes/status codes;
- maximum attempts;
- `start_at` for backoff;
- dead-letter/manual review;
- duplicate/idempotency behavior;
- alert after the final error.

## Monitoring

Minimum checks:

- scheduler heartbeat/last successful launch;
- the number of queued tasks and the age of the oldest;
- running task age;
- failed rate;
- writeability `storage/stask`;
- disk usage progress/results;
- class-missing/inactive workers;
- `s_supervisor_states.last_seen_at` and heartbeat freshness;
- daemon PID identity.

The built-in Statistics tab does not replace APM: duration/memory/common-errors with partial placeholders.

## Logs

There are three different sources:

1. `s_tasks.message/meta/result` — persisted audit/state.
2. `storage/stask/{id}.log` — live append-only progress.
3. application logs via Laravel `Log` — exceptions, discovery, launch warnings.

Define retention individually. `cleanOldTasks()` only deletes old finished DB rows; progress `.log`, failed tasks, and supervisor state are not cleared.

## Retention

An example of a policy to implement in your operations layer:

- finished tasks: 30–90 days;
- failed tasks: longer or before the incident review;
- progress logs: 7–30 days after the final task;
- upload/results: for business/legal policy;
- supervisor state: one actual row per key; orphan rows — after inventory check.

Do not delete active task progress. Check the final status and file ownership before cleaning.

## Supervisor/systemd

sTask Supervisor is an application-level lifecycle adapter, not a complete replacement for systemd/Supervisor. If the process restarts systemd and sTask adapter at the same time, agree on a single owner, otherwise restart loops are possible.

Recommended roles:

- systemd provides boot, user, resource limits and crash restart;
- Worker Adapter reads Health/Heartbeat and returns a diagnostic state;
- only one of them performs restart, or both have a common backoff/lock contract.

## Deploy

Safe Sequence:

1. stop the creation of new jobs or wait for the completion of critical ones;
2. `composer install` with lock;
3. `php artisan migrate --force` after backup and migration review;
4. `package:discover`/autoload rebuild;
5. `stask:publish`;
6. `cache:clear-full`;
7. refresh worker registry after a holistic deploy;
8. smoke `stask:worker`;
9. Check manager UI and scheduler.

Do not run registry cleanup in the middle of deploy when classes are temporarily absent.

## Security

- permission `stask` only operational roles;
- `run_artisan` separately for ArtisanWorker;
- whitelist/blacklist of dangerous commands;
- CSRF for POST;
- secrets not in meta/result/message/progress;
- uploads in non-public storage;
- workers validate input and authorization, even if route manager-only;
- service account with minimal filesystem/DB privileges.

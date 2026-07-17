# Process Supervisor

Supervisor schedule is designed for detached long-term process: listener, transport daemon or other worker-owned runtime. sTask does not know how to check the PID or heartbeat of a particular process; This is done by Adapter in Worker Class.

## Contract

Worker simultaneously remains a regular `TaskInterface`/`BaseWorker` and adds:

```php
use Seiger\sTask\Contracts\SupervisorWorkerInterface;
use Seiger\sTask\Support\SupervisorStatus;

interface SupervisorWorkerInterface
{
    public function supervisorKey(): string;
    public function inspectSupervisor(): SupervisorStatus;
    public function startSupervisor(): SupervisorStatus;
    public function restartSupervisor(): SupervisorStatus;
    public function supervisorStartupGraceSeconds(): int;
}
```

`inspectSupervisor()` should not change the runtime. `startSupervisor()` and `restartSupervisor()` should return quickly after detached launch; They don't have to wait for the Daemon to complete.

## SupervisorStatus

```php
return new SupervisorStatus(
    state: SupervisorState::Healthy,
    pid: 18422,
    heartbeatAt: now()->subSeconds(5),
    startedAt: now()->subHours(3),
    uptimeSeconds: 10800,
    message: 'Listener is receiving updates',
    fingerprint: 'listener:healthy:v1',
);
```

Fields:

- `state` — `healthy`, `starting`, `degraded`, `failed`, `stopped`;
- `pid` — informational PID, nullable;
- `heartbeatAt` — the last confirmation of life;
- `startedAt` and `uptimeSeconds` — process start/uptime, which the UI shows;
- `message` — diagnostics that are safe for the manager;
- `fingerprint` is a stable adapter fingerprint. If not specified, sTask hashes state + message.

Do not include in message/fingerprint tokens, session secrets, or full command lines with credentials.

## One scheduler pass

1. sTask receives a non-empty `supervisorKey()`.
2. Forms `key_hash = sha256(worker_id + ':' + supervisor_key)`.
3. Takes file-cache lock `stask:supervisor:{key_hash}` for 55 seconds.
4. Causes read-only inspection.
5. If state `stopped`, `degraded` or `failed` and startup grace has expired, it calls start/restart.
6. Renews `s_supervisor_states`.
7. Creates a final task row only for meaningful event: started, restarted, recovered, failed, stopped, state/diagnostic change.

Healthy snapshot with the same fingerprint only updates `last_seen_at`, heartbeat/uptime and `repeat_count`; A new task is not created every minute.

## Startup grace

`launch_requested_at` remembers the last launch request. Until `max(1, supervisorStartupGraceSeconds())` has passed, restart/restart is suppressed. After healthy status, the field is cleared.

Grace does not prove that the process has started. The adapter must correctly distinguish between `starting`, `healthy`, `degraded`, `failed`, `stopped` by PID/heartbeat/runtime evidence.

## Transitions and event tasks

| Observation | Action | Event task |
| --- | --- | --- |
| First `stopped` | `startSupervisor()` | `started`, or `failed/stopped` by return status |
| Existing `degraded/failed` | `restartSupervisor()` | `restarted`, or failure |
| `starting` in Grace | launch does not repeat | only when changing state/fingerprint |
| Unhealthy → `healthy` | state persist | `recovered` |
| state changed | state persist | event with the new state |
| fingerprint has changed | diagnostic persist | `diagnostic_changed` |
| healthy unchanged | cursor refresh | no task |

An event task has an action `supervisor`, `started_by = 0`, `max_attempts = 1`, timestamps, `now()`. Failed/degraded/stopped become task status failed; Other events — finished.

## Workers tab

For supervisor schedule, the table shows a chip with `activity-heartbeat`. If state healthy and `uptime_seconds` known, badge shows formatted uptime through `niceEta()` instead of the word "Works". Modal shows key, state, PID, heartbeat, uptime, last diagnosis, and transition time.

## Table state and growth

`key_hash` unique, so one row is `worker_id + supervisor_key` updated for the same pair. A normal heartbeat does not create history rows. The table only grows when new worker IDs/keys appear; There is no automatic pruning.

After uninstalling or reinstalling workers, orphan state rows are not cleared with a foreign key, because schema does not create FK/cascade. Make a routine cleaning only after verifying that the worker/key no longer exists.

## Operational checklist

- launch detached and returns quickly;
- supervisor key is stable and non-empty;
- heartbeat has a clear timeout;
- The PID is checked together with the process identity, not just `posix_kill($pid, 0)`;
- start/restart idempotent;
- Grace is larger than a typical startup time;
- external systemd/Supervisor does not conflict with adapter restart policy;
- Logs and State do not contain secrets.

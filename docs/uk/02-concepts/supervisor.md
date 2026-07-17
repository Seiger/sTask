# Supervisor-процеси

Supervisor schedule призначений для detached довготривалого процесу: listener, transport daemon або іншого worker-owned runtime. sTask не знає, як перевірити PID чи heartbeat конкретного процесу; це робить adapter у worker class.

## Contract

Worker одночасно залишається звичайним `TaskInterface`/`BaseWorker` і додає:

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

`inspectSupervisor()` не повинен змінювати runtime. `startSupervisor()` і `restartSupervisor()` мають повернутися швидко після detached launch; вони не повинні чекати завершення daemon.

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

Поля:

- `state` — `healthy`, `starting`, `degraded`, `failed`, `stopped`;
- `pid` — informational PID, nullable;
- `heartbeatAt` — останнє підтвердження життя;
- `startedAt` і `uptimeSeconds` — process start/uptime, які показує UI;
- `message` — безпечна для менеджера діагностика;
- `fingerprint` — стабільний adapter fingerprint. Якщо не заданий, sTask hash-ує state + message.

Не включайте в message/fingerprint tokens, session secrets або повні command lines з credentials.

## Один scheduler pass

1. sTask отримує непорожній `supervisorKey()`.
2. Формує `key_hash = sha256(worker_id + ':' + supervisor_key)`.
3. Бере file-cache lock `stask:supervisor:{key_hash}` на 55 секунд.
4. Викликає read-only inspection.
5. Якщо state `stopped`, `degraded` або `failed` і startup grace минув — викликає start/restart.
6. Оновлює `s_supervisor_states`.
7. Створює final task row тільки для meaningful event: started, restarted, recovered, failed, stopped, state/diagnostic change.

Healthy snapshot з тим самим fingerprint лише оновлює `last_seen_at`, heartbeat/uptime і `repeat_count`; новий task щохвилини не створюється.

## Startup grace

`launch_requested_at` запам’ятовує останній launch request. Доки не минуло `max(1, supervisorStartupGraceSeconds())`, повторний start/restart пригнічується. Після healthy status поле очищується.

Grace не доводить, що процес запустився. Adapter повинен коректно розрізняти `starting`, `healthy`, `degraded`, `failed`, `stopped` за PID/heartbeat/runtime evidence.

## Переходи й event tasks

| Спостереження | Дія | Event task |
| --- | --- | --- |
| перший `stopped` | `startSupervisor()` | `started`, або `failed/stopped` за return status |
| існуючий `degraded/failed` | `restartSupervisor()` | `restarted`, або failure |
| `starting` у grace | launch не повторюється | лише при зміні state/fingerprint |
| не-healthy → `healthy` | state persist | `recovered` |
| state змінився | state persist | event з новим state |
| fingerprint змінився | diagnostic persist | `diagnostic_changed` |
| healthy без змін | cursor refresh | немає task |

Event task має action `supervisor`, `started_by = 0`, `max_attempts = 1`, timestamps `now()`. Failed/degraded/stopped стають task status failed; інші події — finished.

## Вкладка Воркери

Для supervisor schedule таблиця показує chip з `activity-heartbeat`. Якщо state healthy і `uptime_seconds` відомий, badge показує formatted uptime через `niceEta()` замість слова «Працює». Modal показує key, state, PID, heartbeat, uptime, останню діагностику й час переходу.

## Таблиця state і ріст

`key_hash` unique, тому для однієї пари `worker_id + supervisor_key` оновлюється один row. Нормальний heartbeat не створює history rows. Таблиця росте лише коли з’являються нові worker IDs/keys; автоматичного pruning немає.

Після видалення або перевстановлення workers orphan state rows не очищаються foreign key-ом, бо schema не створює FK/cascade. Планове очищення робіть лише після звірки, що worker/key більше не існує.

## Operational checklist

- launch detached і повертається швидко;
- supervisor key стабільний і непорожній;
- heartbeat має чіткий timeout;
- PID перевіряється разом з process identity, а не лише `posix_kill($pid, 0)`;
- start/restart ідемпотентні;
- grace більший за типовий startup time;
- зовнішній systemd/Supervisor не конфліктує з adapter restart policy;
- logs і state не містять secrets.

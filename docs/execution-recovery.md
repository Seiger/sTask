# Bounded Task Execution

The queue runner and explicit `sTask::execute()` handoff use the existing atomic
`tryClaim()` contract. The claimed attempt executes in an isolated PHP child;
the child validates its lease token and owns a nonblocking task lock before
invoking the worker. A local runner lock prevents overlapping scheduling passes.
These file locks coordinate one shared storage filesystem, not independent hosts.

The default wall-clock limit is 120 seconds for `sChatCenter` and 900 seconds for
other workers. Override `seiger.settings.sTask.execution_timeout` for long-running
imports or exports. `seiger.settings.sTask.php_binary` can select the PHP CLI
binary. The parent releases its PDO connection while waiting for the child and
updates its file heartbeat. Execute only after the creating transaction commits.

Only 100 due rows are loaded per runner pass. Web wakeups use `QueueWakeup` to
coalesce process starts within 30 seconds before PHP bootstraps. External queue
producers can use the same service; an existing minute scheduler remains necessary.

## Interrupted Work

Timeout, process failure or exit without completion marks the same running attempt
failed with `delivery_outcome: unknown` and `retry_safe: false`. No automatic retry
is scheduled. External effects may already have occurred, so reconcile them before
creating another task. Worker-deferred rows stay queued instead of being finalized
as successful by `TaskExecutor`.

Execution leases live under `storage/stask/leases`. Recovery settles an expired
lease only if its task lock is free and the attempt still matches. A database
failure retains the lease for a later pass. Historical running tasks without leases
are left untouched: their age alone cannot prove that their owner is dead.

## Deployment

Deploy all changed runtime classes together, including `ExecutionLock`,
`QueueWakeup`, `TaskExecution` and `TaskExecutor`, then rebuild the Composer
autoload map if the site uses an authoritative class map. Restart long-lived
processes so they load matching code. Keep the minute runner schedule enabled.
Do not delete lease files or replay historical tasks as part of installation.

The existing manager-memory fixes remain in place. Metrics and workers-table
snapshots continue using aggregate and bounded payload-free queries.

## Verification

Run `composer test`, `composer test:execution` and `composer test:metrics`.
The process fixture uses temporary SQLite storage and fake children, never a live
queue or provider. For a live rollout, inspect fresh task outcomes and heartbeat
timestamps without retrying old uncertain sends.

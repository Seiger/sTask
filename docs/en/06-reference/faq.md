# FAQ

## sTask is Laravel Queue?

No. sTask has its own Eloquent tables, worker contract, and run-and-exit command. It does not use the broker/queue connection Laravel as the main execution engine.

## Do I need Redis?

Not for basic work. Worker cache/metrics use Laravel Cache, and supervisor lock explicitly takes store `file`. The configured application cache should still be in good working order.

## Is there an SSE or WebSocket?

No. Live progress is an adaptive HTTP polling endpoint `/stask/task/{id}/progress`.

## Does the start button do the task in background?

The controller tries to close the FastCGI response and start the CLI worker; Fallback may be synchronous or not trigger due to disabled functions. Cron/scheduler is a reliable production path.

## Does emergency stop PHP process?

No. It only translates the DB record to failed. The OS process must be stopped separately.

## Are there automatic retries?

No. Attempts/max attempts are saved, but failed rows are not automatically requeued by the default command.

## What does `system` mean in user filter?

Tasks with `started_by` null or `<= 0`, including scheduler/supervisor events.

## Why does the worker filter show the name and not the identifier?

Provider resolves `worker->title` and uses the identifier only as a fallback. Query filters tasks by identifiers associated with selected worker IDs.

## Why wasn't a task with the same payload created a second time?

Active duplicate suppression compares the identifier, action, and normalized meta for status queued/preparing/running and returns the existing model.

## Is duplicate suppression race-safe?

Not completely. There is no unique key for active payload hash in schema. Parallel processes can go through a lookup at the same time.

## Where is progress stored?

`core/storage/stask/{taskId}.log`. The DB `progress` is updated separately and does not automatically display each file snapshot.

## Why is there no progress but task is working?

File write failures are deliberately ignored so as not to hack the business task. Check permissions and application logs.

## How do I clear my history?

`sTask::cleanOldTasks($days)` only deletes old finished DB tasks. Failed tasks, `.log`, uploads/results, and supervisor state require a separate policy.

## How do I run multiple workers in parallel?

The current CLI does not have an atomic multi-process claim. Do not scale processes horizontally without a new claim/lease design and idempotency.

## Why didn't regular schedule create a task the next day?

The algorithm searches for candidate only in the current daytime window and returns null after end. This is a known limitation of the current implementation.

## How does a Supervisor schedule differ from a periodic health check?

Supervisor updates one live-state row every minute and creates a task only for a meaningful lifecycle event. Periodic schedule supports regular queued `taskMake`.

## Is supervisor state removed from worker?

No automatically: relation does not have a DB foreign key/cascade. Orphan rows clean up after inventory.

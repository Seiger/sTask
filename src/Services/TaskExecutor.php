<?php namespace Seiger\sTask\Services;

use Seiger\sTask\Models\{sTaskModel,sWorker};
use Illuminate\Support\Facades\Log;

/** Execute due tasks through one atomic claim and a bounded child process. */
final class TaskExecutor
{
    /** Claim a due task once, then supervise its isolated process to completion. */
    public function run(sTaskModel $task): bool
    {
        // The queue snapshot can be shared by overlapping UI/scheduler runners.
        // Claim before resolving the worker or writing progress/failure state.
        if (!$task->tryClaim()) {
            return false;
        }
        app(TaskExecution::class)->run($task);
        return true;
    }

    /**
     * Invoke a worker only inside the child whose execution lease was validated.
     *
     * The command holds the task lock and validates the token before calling this
     * method. Do not reclaim the row or increment its attempt a second time.
     *
     * @param sTaskModel $task Claimed task owned by the current child process.
     * @return void
     * @since 2.4.0
     */
    public function runClaimed(sTaskModel $task): void
    {
        try {
            $workerRecord = sWorker::where('identifier', $task->identifier)->where('active', true)->first();

            if (!$workerRecord) {
                throw new \RuntimeException("Worker '{$task->identifier}' not found or inactive");
            }

            $className = $workerRecord->class;
            if (!$className || !class_exists($className)) {
                throw new \RuntimeException("Worker class '{$className}' not found");
            }

            $worker = app($className);
            if (!$worker instanceof \Seiger\sTask\Contracts\TaskInterface) {
                throw new \RuntimeException("Class '{$className}' must implement TaskInterface");
            }

            // Initialize progress tracking
            TaskProgress::init([
                'id'         => (int)$task->id,
                'identifier' => $task->identifier,
                'action'     => $task->action,
                'status'     => $task->status_text,
                'progress'   => 0,
                'message'    => '**' . __('sTask::global.starting_task') . '**',
            ]);

            // Delegate the real work to worker via invokeAction
            // Worker will handle all progress updates and messages
            $worker->invokeAction($task->action, $task, $task->meta ?? []);

            // Ensure finalization if worker didn't mark finished
            $freshTask = $task->fresh();
            if ($freshTask && $freshTask->isRunning()) {
                $task->markAsFinished('Task completed successfully');
                $freshTask = $task->fresh();

                TaskProgress::write([
                    'id'         => (int)$freshTask->id,
                    'identifier' => $freshTask->identifier,
                    'action'     => $freshTask->action,
                    'status'     => $freshTask->status_text,
                    'progress'   => max(0, min(100, (int)$freshTask->progress)),
                    'message'    => '**' . __('sTask::global.task_completed') . '**',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('task.worker failed: ' . $e->getMessage(), ['task' => $task->id]);
            $task->markAsFailed('Failed: ' . $e->getMessage());
            TaskProgress::write([
                'id'         => (int)$task->id,
                'identifier' => $task->identifier ?? 'unknown',
                'action'     => $task->action ?? 'unknown',
                'status'     => $task->status_text,
                'code'       => 500,
                'message'    => '**Failed @ ' . basename($e->getFile()) . ':' . $e->getLine() . ' — ' . $e->getMessage() . '**',
            ]);
        }
    }

}

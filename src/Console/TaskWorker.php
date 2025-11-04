<?php namespace Seiger\sTask\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Models\sWorker;
use Seiger\sTask\Services\TaskProgress;
use Seiger\sTask\Services\WorkerService;

/**
 * TaskWorker - Console command for processing sTask background tasks
 *
 * This command processes queued sTask jobs from the database queue.
 * It runs continuously, claiming and executing any type of background tasks
 * through their respective worker implementations. Tasks can include
 * import/export operations, data synchronization, cleanup jobs, notifications,
 * and any other background work defined by worker modules.
 *
 * Features:
 * - Checks scheduled workers and creates tasks for those that should run
 * - Processes any type of background tasks from sTaskModel queue
 * - Supports multiple worker types via database configuration
 * - Tracks progress through TaskProgress file-based system
 * - Handles task status updates (running, finished, failed)
 * - Provides comprehensive error handling and logging
 * - Scheduled to run every minute via Laravel Scheduler
 * - Extensible architecture for custom task types
 * - Asynchronous execution with proper environment setup
 * - Automatic cleanup of old progress files when idle
 *
 * Task Processing Flow:
 * 1. Checks all active workers with enabled schedules
 * 2. Creates tasks for workers that should run now (via shouldRunNow())
 * 3. Retrieves all tasks ready for execution (pending status)
 * 4. For each task, resolves the worker class from database
 * 5. Validates worker implements TaskInterface
 * 6. Calls the appropriate action method via BaseWorker
 * 7. Updates task status and progress through TaskProgress
 * 8. Handles errors gracefully with proper cleanup
 * 9. Cleans up old progress files if no active tasks remain
 *
 * Usage:
 * php artisan stask:worker
 *
 * @package Seiger\sTask
 * @author Seiger IT Team
 * @since 1.0.0
 */
class TaskWorker extends Command
{
    /** @var string */
    protected $signature = 'stask:worker';

    /** @var string */
    protected $description = 'Process all queued sTask jobs and exit.';

    /**
     * Execute the console command.
     *
     * First checks all scheduled workers and creates tasks for those that should run.
     * Then retrieves all tasks ready for execution and processes them sequentially.
     * Each task is executed through its respective worker implementation.
     * After processing, performs cleanup of old progress files if idle.
     *
     * @return int Command exit code (0 for success)
     */
    public function handle(): int
    {
        // Step 1: Check scheduled workers and create tasks
        $created = $this->checkScheduledWorkers();

        // Step 2: Process all queued tasks that are ready
        // If start_at is set, check if time has come; otherwise process immediately
        $tasks = sTaskModel::queued()
            ->where(function($query) {
                $query->whereNull('start_at')
                    ->orWhere('start_at', '<=', now());
            })
            ->get();
        $processed = 0;

        foreach ($tasks as $task) {
            $this->runOne($task);
            $processed++;
        }

        $this->info("[stask:worker] created {$created} scheduled task(s), processed {$processed} task(s).");
        $this->cleanupIfIdle();
        return self::SUCCESS;
    }

    /**
     * Check scheduled workers and create tasks for those that should run now.
     *
     * For 'once' type: Tasks are created when settings are saved (with future created_at).
     *                  This method only needs to mark them as ready when time comes.
     * For 'periodic' and 'regular': Creates tasks when schedule matches current time.
     *
     * Iterates through all active workers with enabled schedules,
     * checks if they should run according to their schedule configuration,
     * and creates new tasks if needed (avoiding duplicates).
     *
     * @return int Number of tasks created
     */
    private function checkScheduledWorkers(): int
    {
        $workers = sWorker::where('active', true)->get();
        $created = 0;

        foreach ($workers as $workerRecord) {
            try {
                // Resolve worker instance
                $worker = app(WorkerService::class)->resolveWorker($workerRecord->identifier);

                if (!$worker) {
                    continue;
                }

                // Check if worker has taskMake method (scheduled workers only)
                if (!method_exists($worker, 'taskMake')) {
                    continue;
                }

                // Get schedule configuration
                $schedule = $worker->getSchedule();

                // Skip if schedule not enabled
                if (!($schedule['enabled'] ?? false)) {
                    continue;
                }

                // For 'once' type, task is created when settings are saved
                // Just skip here as task already exists with future created_at
                if (($schedule['type'] ?? 'manual') === 'once') {
                    continue;
                }

                // Check if should run now (for periodic and regular types)
                if (!$worker->shouldRunNow()) {
                    continue;
                }

                // Check if worker already has an incomplete task
                $existingTask = $workerRecord->tasks()
                    ->incomplete()
                    ->first();

                if ($existingTask) {
                    continue; // Skip if task already exists
                }

                // Create new task for this worker
                $task = $worker->createTask('make', ['manual' => false]);
                $created++;

                $this->info("[stask:worker] Created scheduled task #{$task->id} for worker '{$workerRecord->identifier}'");

            } catch (\Throwable $e) {
                Log::warning("[stask:worker] Failed to check schedule for worker '{$workerRecord->identifier}': " . $e->getMessage());
            }
        }

        return $created;
    }

    /**
     * Execute one claimed task via its worker implementation.
     *
     * This method handles the complete lifecycle of a single task:
     * 1. Resolves the worker class from database configuration
     * 2. Validates the worker implements TaskInterface
     * 3. Executes the task through the worker's action method
     * 4. Ensures proper task finalization and status updates
     * 5. Handles any errors with appropriate cleanup
     *
     * @param sTaskModel $task The task to execute
     * @return void
     */
    private function runOne(sTaskModel $task): void
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

            // Mark task as running
            $task->markAsRunning();

            // Initialize progress tracking
            TaskProgress::init([
                'id'         => (int)$task->id,
                'identifier' => $task->identifier,
                'action'     => $task->action,
                'status'     => $task->status_text,
                'progress'   => 0,
                'message'    => '***' . __('sTask::global.starting_task') . '***',
            ]);

            // Delegate the real work to worker via invokeAction
            // Worker will handle all progress updates and messages
            $worker->invokeAction($task->action, $task, $task->meta ?? []);

            // Ensure finalization if worker didn't mark finished
            $freshTask = $task->fresh();
            if (!$freshTask->isFinished()) {
                $task->markAsFinished('Task completed successfully');

                TaskProgress::write([
                    'id'         => (int)$task->id,
                    'identifier' => $task->identifier,
                    'action'     => $task->action,
                    'status'     => $task->status_text,
                    'progress'   => 100,
                    'message'    => '***' . __('sTask::global.task_completed') . '***',
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

    /**
     * Clean up old progress files if no active tasks remain.
     *
     * This method performs garbage collection of old TaskProgress files
     * when the system is idle (no pending or running tasks). It helps
     * prevent disk space accumulation while avoiding cleanup during
     * active task processing.
     *
     * Cleanup process:
     * 1. Check if there are any pending or running tasks
     * 2. If idle, remove progress files older than 24 hours
     * 3. Remove old temporary files older than 1 hour
     *
     * @return void
     */
    private function cleanupIfIdle(): void
    {
        // Only cleanup if no active tasks
        $activeTasks = sTaskModel::where('status', sTaskModel::TASK_STATUS_QUEUED)
            ->orWhere('status', sTaskModel::TASK_STATUS_PREPARING)
            ->orWhere('status', sTaskModel::TASK_STATUS_RUNNING)
            ->count();

        if ($activeTasks > 0) {
            return; // Skip cleanup if there are active tasks
        }

        $dir = TaskProgress::dir();
        $now = time();
        $ttl = 86400; // 24 hours
        $deleted = 0;

        // Delete expired JSON progress files
        foreach (glob($dir . '/*.json') ?: [] as $path) {
            clearstatcache(false, $path);
            $mtime = @filemtime($path) ?: 0;
            if ($mtime && ($now - $mtime) > $ttl) {
                if (@unlink($path)) {
                    $deleted++;
                }
            }
        }

        // Cleanup old temp files (10 hour)
        foreach (glob($dir . '/\.~*.json') ?: [] as $path) {
            clearstatcache(false, $path);
            $mtime = @filemtime($path) ?: 0;
            if ($mtime && ($now - $mtime) > 36000) {
                @unlink($path);
            }
        }

        if ($deleted > 0) {
            $this->info("[stask:worker] cleaned up {$deleted} old progress file(s).");
        }
    }

    /**
     * Define the command's schedule.
     *
     * Configures the command to run every minute via Laravel Scheduler.
     * This ensures tasks are processed regularly without manual intervention.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule The schedule instance
     * @return void
     */
    public function schedule(Schedule $schedule)
    {
        $schedule->command(static::class)->everyMinute();
    }
}

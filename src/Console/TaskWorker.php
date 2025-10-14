<?php namespace Seiger\sTask\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Models\sWorker;
use Seiger\sTask\Services\TaskProgress;
use Seiger\sTask\Facades\sTask;

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
 * 1. Retrieves all tasks ready for execution (pending status)
 * 2. For each task, resolves the worker class from database
 * 3. Validates worker implements TaskInterface
 * 4. Calls the appropriate action method via BaseWorker
 * 5. Updates task status and progress through TaskProgress
 * 6. Handles errors gracefully with proper cleanup
 * 7. Cleans up old progress files if no active tasks remain
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
     * Retrieves all tasks ready for execution and processes them sequentially.
     * Each task is executed through its respective worker implementation.
     * After processing, performs cleanup of old progress files if idle.
     *
     * @return int Command exit code (0 for success)
     */
    public function handle(): int
    {
        $tasks = sTaskModel::pending()->get();
        $processed = 0;

        foreach ($tasks as $task) {
            $this->runOne($task);
            $processed++;
        }

        $this->info("[stask:worker] processed {$processed} task(s).");

        // Perform cleanup if no active tasks remain
        $this->cleanupIfIdle();

        return self::SUCCESS;
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
            // Resolve worker by identifier from database
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
                'message'    => 'Starting...',
            ]);

            // Delegate the real work to worker via invokeAction
            // Worker will handle all progress updates and messages
            $worker->invokeAction($task->action, $task, $task->meta ?? []);

            // Ensure finalization if worker didn't mark finished
            $freshTask = $task->fresh();
            if (!$freshTask->isFinished()) {
                $task->markAsCompleted('Task completed successfully');

                TaskProgress::write([
                    'id'         => (int)$task->id,
                    'identifier' => $task->identifier,
                    'action'     => $task->action,
                    'status'     => 'completed',
                    'progress'   => 100,
                    'message'    => 'Task completed successfully',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('task.worker failed: ' . $e->getMessage(), ['task' => $task->id]);

            $task->markAsFailed('Failed: ' . $e->getMessage());

            TaskProgress::write([
                'id'         => (int)$task->id,
                'identifier' => $task->identifier ?? 'unknown',
                'action'     => $task->action ?? 'unknown',
                'status'     => 'failed',
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
        $activeTasks = sTaskModel::where('status', 10) // pending
            ->orWhere('status', 20) // running
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

        // Cleanup old temp files (1 hour)
        foreach (glob($dir . '/\.~*.json') ?: [] as $path) {
            clearstatcache(false, $path);
            $mtime = @filemtime($path) ?: 0;
            if ($mtime && ($now - $mtime) > 3600) {
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


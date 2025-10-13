<?php namespace Seiger\sTask;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Models\sWorker;
use Seiger\sTask\Services\TaskLogger;
use Seiger\sTask\Services\WorkerDiscovery;
use Seiger\sTask\Contracts\TaskInterface;

/**
 * Class sTask
 *
 * This class handles asynchronous task management for Evolution CMS.
 * Provides methods for creating, executing, and monitoring background tasks.
 *
 * @package Seiger\sTask
 * @author Seiger IT Team
 * @since 1.0.0
 */
class sTask
{
    /**
     * Create a new task
     *
     * @param string $identifier Worker identifier
     * @param string $action Action to perform
     * @param array $data Task data and parameters
     * @param string $priority Task priority (low, normal, high)
     * @param int $userId User ID who initiated the task
     * @return sTaskModel
     */
    public function create(string $identifier, string $action, array $data = [], string $priority = 'normal', int $userId = null): sTaskModel
    {
        return sTaskModel::create([
            'identifier' => $identifier,
            'action' => $action,
            'meta' => $data,
            'priority' => $priority,
            'started_by' => $userId,
            'status' => 10, // pending
            'progress' => 0,
            'attempts' => 0,
            'max_attempts' => 3,
        ]);
    }

    /**
     * Execute a task
     *
     * @param sTaskModel $task
     * @return bool
     */
    public function execute(sTaskModel $task): bool
    {
        try {
            // Mark task as running
            $task->markAsRunning();

            // Log task start
            $this->log($task, 'info', 'Task started', [
                'identifier' => $task->identifier,
                'action' => $task->action,
                'attempt' => $task->attempts
            ]);

            // Get worker for this task identifier
            $worker = $this->resolveWorker($task->identifier);
            
            if (!$worker) {
                throw new \Exception("Worker for identifier '{$task->identifier}' not found");
            }

            // Validate task data
            if (method_exists($worker, 'validate') && !$worker->validate($task->meta)) {
                throw new \Exception('Task validation failed');
            }

            // Execute the task
            $result = $worker->execute($task->meta);

            if ($result) {
                $task->markAsCompleted('Task completed successfully');
                $this->log($task, 'info', 'Task completed successfully');
                return true;
            } else {
                throw new \Exception('Task execution returned false');
            }

        } catch (\Exception $e) {
            $task->markAsFailed($e->getMessage());

            $this->log($task, 'error', 'Task failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Retry if attempts not exceeded
            if ($task->attempts < $task->max_attempts) {
                $this->retry($task);
            }

            return false;
        }
    }

    /**
     * Retry a failed task
     *
     * @param sTaskModel $task
     * @return void
     */
    public function retry(sTaskModel $task): void
    {
        $delay = 60;
        
        $task->update([
            'status' => 10, // pending
            'message' => "Retrying in {$delay} seconds",
        ]);

        $this->log($task, 'info', "Task will be retried in {$delay} seconds", [
            'attempt' => $task->attempts,
            'max_attempts' => $task->max_attempts
        ]);
    }

    /**
     * Get pending tasks
     *
     * @param int $limit
     * @return Collection
     */
    public function getPendingTasks(int $limit = 10): Collection
    {
        $priorities = [
            'high' => 1,
            'normal' => 5,
            'low' => 10,
        ];

        return sTaskModel::where('status', 10) // pending
            ->orderByRaw("CASE priority 
                WHEN 'high' THEN {$priorities['high']} 
                WHEN 'normal' THEN {$priorities['normal']} 
                WHEN 'low' THEN {$priorities['low']} 
                ELSE {$priorities['normal']} END")
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Process pending tasks
     *
     * @param int $batchSize
     * @return int Number of processed tasks
     */
    public function processPendingTasks(int $batchSize = null): int
    {
        $batchSize = $batchSize ?? 10;
        $processed = 0;

        $tasks = $this->getPendingTasks($batchSize);

        foreach ($tasks as $task) {
            if ($this->execute($task)) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Get task statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'pending' => sTaskModel::where('status', 10)->count(),
            'running' => sTaskModel::where('status', 20)->count(),
            'completed' => sTaskModel::where('status', 30)->count(),
            'failed' => sTaskModel::where('status', 40)->count(),
            'cancelled' => sTaskModel::where('status', 50)->count(),
            'total' => sTaskModel::count(),
        ];
    }

    /**
     * Clean old completed tasks
     *
     * @param int $days Number of days to keep completed tasks
     * @return int Number of deleted tasks
     */
    public function cleanOldTasks(int $days = 30): int
    {
        $cutoff = now()->subDays($days);
        
        return sTaskModel::where('status', 30) // completed
            ->where('finished_at', '<', $cutoff)
            ->delete();
    }

    /**
     * Clean old log files
     *
     * @param int $days Number of days to keep logs
     * @return int Number of deleted log files
     */
    public function cleanOldLogs(int $days = 30): int
    {
        $logger = app(TaskLogger::class);
        return $logger->clearOldLogs($days);
    }

    /**
     * Log a message for a task
     *
     * @param sTaskModel $task
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function log(sTaskModel $task, string $level, string $message, array $context = []): void
    {
        // Write to task log file
        $logger = app(TaskLogger::class);
        $logger->log($task, $level, $message, $context);
    }

    /**
     * Resolve worker class for task identifier
     *
     * @param string $identifier
     * @return TaskInterface|null
     */
    private function resolveWorker(string $identifier): ?TaskInterface
    {
        // First try to get from database
        $worker = sWorker::where('identifier', $identifier)->where('active', true)->first();
        
        if ($worker && $worker->canBeUsed()) {
            try {
                return $worker->getInstance();
            } catch (\Exception $e) {
                Log::error("Failed to resolve worker for identifier '{$identifier}': " . $e->getMessage());
            }
        }

        // Try auto-discovery if worker not found
        $this->autoDiscoverWorkers();
        
        // Try again after discovery
        $worker = sWorker::where('identifier', $identifier)->where('active', true)->first();
        
        if ($worker && $worker->canBeUsed()) {
            try {
                return $worker->getInstance();
            } catch (\Exception $e) {
                Log::error("Failed to resolve worker for identifier '{$identifier}': " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Auto-discover workers from registered packages
     *
     * @return void
     */
    private function autoDiscoverWorkers(): void
    {
        // Auto-discovery is handled by WorkerDiscovery service
        // This method is kept for compatibility but delegates to discoverWorkers()
        $this->discoverWorkers();
    }

    /**
     * Discover and register new workers
     *
     * @return array
     */
    public function discoverWorkers(): array
    {
        $discovery = app(WorkerDiscovery::class);
        return $discovery->discover();
    }

    /**
     * Register a single worker
     *
     * @param string $className
     * @return Worker|null
     */
    public function registerWorker(string $className): ?Worker
    {
        $discovery = app(WorkerDiscovery::class);
        return $discovery->registerWorker($className);
    }

    /**
     * Re-scan and update existing workers
     *
     * @return array
     */
    public function rescanWorkers(): array
    {
        $discovery = app(WorkerDiscovery::class);
        return $discovery->rescan();
    }

    /**
     * Clean orphaned workers
     *
     * @return int
     */
    public function cleanOrphanedWorkers(): int
    {
        $discovery = app(WorkerDiscovery::class);
        return $discovery->cleanOrphaned();
    }

    /**
     * Get all workers
     *
     * @param bool $activeOnly
     * @return Collection
     */
    public function getWorkers(bool $activeOnly = false): Collection
    {
        $query = sWorker::ordered();
        
        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Get worker by identifier
     *
     * @param string $identifier
     * @return sWorker|null
     */
    public function getWorker(string $identifier): ?sWorker
    {
        return sWorker::where('identifier', $identifier)->first();
    }

    /**
     * Activate worker
     *
     * @param string $identifier
     * @return bool
     */
    public function activateWorker(string $identifier): bool
    {
        $worker = $this->getWorker($identifier);
        
        if (!$worker) {
            return false;
        }

        $worker->active = true;
        return $worker->save();
    }

    /**
     * Deactivate worker
     *
     * @param string $identifier
     * @return bool
     */
    public function deactivateWorker(string $identifier): bool
    {
        $worker = $this->getWorker($identifier);
        
        if (!$worker) {
            return false;
        }

        $worker->active = false;
        return $worker->save();
    }
}
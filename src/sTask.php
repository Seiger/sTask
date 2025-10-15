<?php namespace Seiger\sTask;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Models\sWorker;
use Seiger\sTask\Services\WorkerDiscovery;
use Seiger\sTask\Services\WorkerService;
use Seiger\sTask\Services\MetricsService;
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
     * Worker service instance
     *
     * @var WorkerService
     */
    private WorkerService $workerService;

    /**
     * Metrics service instance
     *
     * @var MetricsService
     */
    private MetricsService $metricsService;

    /**
     * sTask constructor
     */
    public function __construct()
    {
        $this->workerService = app(WorkerService::class);
        $this->metricsService = app(MetricsService::class);
    }
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
    public function create(string $identifier, string $action, array $data = [], string $priority = 'normal', ?int $userId = null): sTaskModel
    {
        return sTaskModel::create([
            'identifier' => $identifier,
            'action' => $action,
            'meta' => $data,
            'priority' => $priority,
            'started_by' => $userId,
            'status' => sTaskModel::TASK_STATUS_QUEUED,
            'progress' => 0,
            'attempts' => 0,
            'max_attempts' => 3,
        ]);
    }

    /**
     * Execute a task by invoking its action method
     *
     * @param sTaskModel $task
     * @return bool
     */
    public function execute(sTaskModel $task): bool
    {
        try {
            // Record task start metrics
            $this->metricsService->recordTaskStart($task);
            
            // Mark task as running
            $task->markAsRunning();

            // Get worker for this task identifier using optimized service
            $worker = $this->workerService->resolveWorker($task->identifier);

            // Invoke the action method
            $worker->invokeAction($task->action, $task, $task->meta);

            $task->markAsFinished('Task completed successfully');
            
            // Record successful completion metrics
            $this->metricsService->recordTaskEnd($task, true);
            
            return true;
        } catch (\Exception $e) {
            $task->markAsFailed($e->getMessage());
            
            // Record failed completion metrics
            $this->metricsService->recordTaskEnd($task, false, $e->getMessage());

            // If max attempts reached, mark as failed permanently
            if ($task->attempts >= $task->max_attempts) {
                $task->markAsFailed('Max retry attempts reached. Task failed permanently.');
            }
            return false;
        }
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
    public function processPendingTasks(?int $batchSize = null): int
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
            'pending' => sTaskModel::queued()->count(),
            'running' => sTaskModel::running()->count(),
            'completed' => sTaskModel::finished()->count(),
            'failed' => sTaskModel::failed()->count(),
            'total' => sTaskModel::count(),
            'total_workers' => sWorker::count(),
            'active_workers' => sWorker::active()->count(),
        ];
    }

    /**
     * Get performance metrics
     *
     * @param int $hours Number of hours to analyze
     * @return array Performance metrics
     */
    public function getPerformanceMetrics(int $hours = 24): array
    {
        return $this->metricsService->getSystemSummary($hours);
    }

    /**
     * Get worker performance statistics
     *
     * @param string|null $identifier Specific worker identifier
     * @param int $hours Number of hours to analyze
     * @return array Worker statistics
     */
    public function getWorkerStats(?string $identifier = null, int $hours = 24): array
    {
        return $this->metricsService->getWorkerStats($identifier, $hours);
    }

    /**
     * Get performance alerts
     *
     * @return array Performance alerts
     */
    public function getPerformanceAlerts(): array
    {
        return $this->metricsService->getPerformanceAlerts();
    }

    /**
     * Get worker service cache statistics
     *
     * @return array Cache statistics
     */
    public function getCacheStats(): array
    {
        return $this->workerService->getCacheStats();
    }

    /**
     * Clear worker cache
     *
     * @param string|null $identifier Worker identifier to clear, or null for all
     * @return void
     */
    public function clearWorkerCache(?string $identifier = null): void
    {
        $this->workerService->clearCache($identifier);
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

        return sTaskModel::finished()
            ->where('finished_at', '<', $cutoff)
            ->delete();
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
     * @return sWorker|null
     */
    public function registerWorker(string $className): ?sWorker
    {
        $discovery = app(WorkerDiscovery::class);
        return $discovery->registerWorker($className);
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

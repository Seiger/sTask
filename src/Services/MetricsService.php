<?php namespace Seiger\sTask\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Seiger\sTask\Models\sTaskModel;

/**
 * MetricsService - Performance monitoring and metrics collection
 *
 * This service collects and analyzes performance metrics for the sTask system,
 * providing insights into task execution, worker performance, and system health.
 *
 * Key Features:
 * - Task execution metrics (duration, memory usage, success rate)
 * - Worker performance statistics
 * - System resource monitoring
 * - Historical data analysis
 * - Performance alerts and notifications
 *
 * Metrics Collected:
 * - Task execution time
 * - Memory usage per task
 * - Database query performance
 * - File I/O operations
 * - Worker utilization
 * - Error rates and types
 *
 * @package Seiger\sTask\Services
 * @author Seiger IT Team
 * @since 1.0.0
 */
class MetricsService
{
    /**
     * Cache key prefix for metrics
     */
    private const METRICS_PREFIX = 'stask_metrics_';
    
    /**
     * Default metrics retention period (24 hours)
     */
    private const METRICS_TTL = 86400;
    
    /**
     * Maximum number of metrics to store in memory
     */
    private const MAX_METRICS_CACHE = 1000;

    /**
     * Record task start metrics
     *
     * @param sTaskModel $task The task being started
     * @return void
     */
    public function recordTaskStart(sTaskModel $task): void
    {
        $metrics = [
            'task_id' => $task->id,
            'identifier' => $task->identifier,
            'action' => $task->action,
            'started_at' => microtime(true),
            'memory_start' => memory_get_usage(true),
            'peak_memory_start' => memory_get_peak_usage(true),
            'started_by' => $task->started_by,
            'priority' => $task->priority,
        ];

        $this->storeTaskMetrics($task->id, 'start', $metrics);
    }

    /**
     * Record task completion metrics
     *
     * @param sTaskModel $task The completed task
     * @param bool $success Whether the task completed successfully
     * @param string|null $errorMessage Error message if failed
     * @return void
     */
    public function recordTaskEnd(sTaskModel $task, bool $success, ?string $errorMessage = null): void
    {
        $startMetrics = $this->getTaskMetrics($task->id, 'start');
        
        if (!$startMetrics) {
            Log::warning('Task start metrics not found', ['task_id' => $task->id]);
            return;
        }

        $endTime = microtime(true);
        $duration = $endTime - $startMetrics['started_at'];
        $memoryUsed = memory_get_usage(true) - $startMetrics['memory_start'];
        $peakMemory = memory_get_peak_usage(true);

        $metrics = [
            'task_id' => $task->id,
            'identifier' => $task->identifier,
            'action' => $task->action,
            'finished_at' => $endTime,
            'duration' => $duration,
            'memory_used' => $memoryUsed,
            'peak_memory' => $peakMemory,
            'success' => $success,
            'error_message' => $errorMessage,
            'final_status' => $task->status,
            'attempts' => $task->attempts,
        ];

        $this->storeTaskMetrics($task->id, 'end', $metrics);
        $this->aggregateMetrics($metrics);
        $this->cleanupTaskMetrics($task->id);
    }

    /**
     * Record worker performance metrics
     *
     * @param string $identifier Worker identifier
     * @param array $metrics Performance metrics
     * @return void
     */
    public function recordWorkerMetrics(string $identifier, array $metrics): void
    {
        $key = self::METRICS_PREFIX . 'worker_' . $identifier;
        $existing = Cache::get($key, []);
        
        $existing[] = array_merge($metrics, [
            'timestamp' => microtime(true),
            'date' => now()->toDateString(),
        ]);

        // Keep only recent metrics (last 100 entries per worker)
        if (count($existing) > 100) {
            $existing = array_slice($existing, -100);
        }

        Cache::put($key, $existing, self::METRICS_TTL);
    }

    /**
     * Get system performance summary
     *
     * @param int $hours Number of hours to analyze (default: 24)
     * @return array<string, mixed> Performance summary
     */
    public function getSystemSummary(int $hours = 24): array
    {
        $since = now()->subHours($hours);
        
        $tasks = sTaskModel::where('created_at', '>=', $since)->get();
        
        $summary = [
            'period' => [
                'hours' => $hours,
                'from' => $since->toISOString(),
                'to' => now()->toISOString(),
            ],
            'tasks' => [
                'total' => $tasks->count(),
                'completed' => $tasks->where('status', sTaskModel::TASK_STATUS_FINISHED)->count(),
                'failed' => $tasks->where('status', sTaskModel::TASK_STATUS_FAILED)->count(),
                'running' => $tasks->where('status', sTaskModel::TASK_STATUS_RUNNING)->count(),
                'queued' => $tasks->where('status', sTaskModel::TASK_STATUS_QUEUED)->count(),
            ],
            'performance' => $this->calculatePerformanceMetrics($tasks),
            'workers' => $this->getWorkerStatistics($tasks),
            'errors' => $this->getErrorStatistics($tasks),
        ];

        return $summary;
    }

    /**
     * Get worker performance statistics
     *
     * @param string|null $identifier Specific worker identifier, or null for all
     * @param int $hours Number of hours to analyze
     * @return array<string, mixed> Worker statistics
     */
    public function getWorkerStats(?string $identifier = null, int $hours = 24): array
    {
        $since = now()->subHours($hours);
        
        $query = sTaskModel::where('created_at', '>=', $since);
        
        if ($identifier) {
            $query->where('identifier', $identifier);
        }
        
        $tasks = $query->get();
        
        $stats = [];
        
        foreach ($tasks->groupBy('identifier') as $workerId => $workerTasks) {
            $stats[$workerId] = [
                'identifier' => $workerId,
                'total_tasks' => $workerTasks->count(),
                'success_rate' => $this->calculateSuccessRate($workerTasks),
                'average_duration' => $this->calculateAverageDuration($workerTasks),
                'total_execution_time' => $this->calculateTotalExecutionTime($workerTasks),
                'memory_usage' => $this->calculateMemoryUsage($workerTasks),
                'error_rate' => $this->calculateErrorRate($workerTasks),
                'last_execution' => $workerTasks->max('updated_at')?->toISOString(),
            ];
        }

        return $stats;
    }

    /**
     * Get performance alerts based on thresholds
     *
     * @return array<string, mixed> Performance alerts
     */
    public function getPerformanceAlerts(): array
    {
        $alerts = [];
        $thresholds = $this->getPerformanceThresholds();
        
        $summary = $this->getSystemSummary(1); // Last hour
        
        // Check task success rate
        if ($summary['performance']['success_rate'] < $thresholds['min_success_rate']) {
            $alerts[] = [
                'type' => 'low_success_rate',
                'severity' => 'warning',
                'message' => "Task success rate is below threshold: {$summary['performance']['success_rate']}%",
                'value' => $summary['performance']['success_rate'],
                'threshold' => $thresholds['min_success_rate'],
            ];
        }

        // Check average execution time
        if ($summary['performance']['average_duration'] > $thresholds['max_avg_duration']) {
            $alerts[] = [
                'type' => 'high_execution_time',
                'severity' => 'warning',
                'message' => "Average task execution time exceeds threshold: {$summary['performance']['average_duration']}s",
                'value' => $summary['performance']['average_duration'],
                'threshold' => $thresholds['max_avg_duration'],
            ];
        }

        // Check memory usage
        if ($summary['performance']['average_memory'] > $thresholds['max_avg_memory']) {
            $alerts[] = [
                'type' => 'high_memory_usage',
                'severity' => 'warning',
                'message' => "Average memory usage exceeds threshold: " . $this->formatBytes($summary['performance']['average_memory']),
                'value' => $summary['performance']['average_memory'],
                'threshold' => $thresholds['max_avg_memory'],
            ];
        }

        return $alerts;
    }

    /**
     * Store task metrics in cache
     *
     * @param int $taskId Task ID
     * @param string $type Metric type (start, end)
     * @param array $metrics Metrics data
     * @return void
     */
    private function storeTaskMetrics(int $taskId, string $type, array $metrics): void
    {
        $key = self::METRICS_PREFIX . 'task_' . $taskId . '_' . $type;
        Cache::put($key, $metrics, self::METRICS_TTL);
    }

    /**
     * Get task metrics from cache
     *
     * @param int $taskId Task ID
     * @param string $type Metric type (start, end)
     * @return array|null Metrics data or null if not found
     */
    private function getTaskMetrics(int $taskId, string $type): ?array
    {
        $key = self::METRICS_PREFIX . 'task_' . $taskId . '_' . $type;
        return Cache::get($key);
    }

    /**
     * Clean up task metrics from cache
     *
     * @param int $taskId Task ID
     * @return void
     */
    private function cleanupTaskMetrics(int $taskId): void
    {
        $startKey = self::METRICS_PREFIX . 'task_' . $taskId . '_start';
        $endKey = self::METRICS_PREFIX . 'task_' . $taskId . '_end';
        
        Cache::forget($startKey);
        Cache::forget($endKey);
    }

    /**
     * Aggregate metrics for system-wide statistics
     *
     * @param array $metrics Task completion metrics
     * @return void
     */
    private function aggregateMetrics(array $metrics): void
    {
        $key = self::METRICS_PREFIX . 'aggregate';
        $aggregate = Cache::get($key, []);
        
        $date = now()->toDateString();
        if (!isset($aggregate[$date])) {
            $aggregate[$date] = [
                'total_tasks' => 0,
                'successful_tasks' => 0,
                'total_duration' => 0,
                'total_memory' => 0,
                'max_duration' => 0,
                'max_memory' => 0,
            ];
        }
        
        $aggregate[$date]['total_tasks']++;
        if ($metrics['success']) {
            $aggregate[$date]['successful_tasks']++;
        }
        
        $aggregate[$date]['total_duration'] += $metrics['duration'];
        $aggregate[$date]['total_memory'] += $metrics['memory_used'];
        $aggregate[$date]['max_duration'] = max($aggregate[$date]['max_duration'], $metrics['duration']);
        $aggregate[$date]['max_memory'] = max($aggregate[$date]['max_memory'], $metrics['memory_used']);
        
        // Keep only last 30 days
        $aggregate = array_slice($aggregate, -30, null, true);
        
        Cache::put($key, $aggregate, self::METRICS_TTL * 30);
    }

    /**
     * Calculate performance metrics from tasks
     *
     * @param \Illuminate\Support\Collection $tasks Task collection
     * @return array<string, mixed> Performance metrics
     */
    private function calculatePerformanceMetrics($tasks): array
    {
        $completed = $tasks->where('status', sTaskModel::TASK_STATUS_FINISHED);
        $failed = $tasks->where('status', sTaskModel::TASK_STATUS_FAILED);
        
        return [
            'success_rate' => $this->calculateSuccessRate($tasks),
            'average_duration' => $this->calculateAverageDuration($completed),
            'average_memory' => $this->calculateAverageMemory($completed),
            'total_execution_time' => $this->calculateTotalExecutionTime($completed),
            'error_rate' => $this->calculateErrorRate($tasks),
        ];
    }

    /**
     * Calculate success rate from tasks
     *
     * @param \Illuminate\Support\Collection $tasks Task collection
     * @return float Success rate percentage
     */
    private function calculateSuccessRate($tasks): float
    {
        if ($tasks->isEmpty()) {
            return 0.0;
        }
        
        $successful = $tasks->where('status', sTaskModel::TASK_STATUS_FINISHED)->count();
        return round(($successful / $tasks->count()) * 100, 2);
    }

    /**
     * Calculate average duration from tasks
     *
     * @param \Illuminate\Support\Collection $tasks Task collection
     * @return float Average duration in seconds
     */
    private function calculateAverageDuration($tasks): float
    {
        if ($tasks->isEmpty()) {
            return 0.0;
        }
        
        // This would need to be calculated from actual execution times
        // For now, return a placeholder
        return 0.0;
    }

    /**
     * Calculate average memory usage from tasks
     *
     * @param \Illuminate\Support\Collection $tasks Task collection
     * @return int Average memory usage in bytes
     */
    private function calculateAverageMemory($tasks): int
    {
        if ($tasks->isEmpty()) {
            return 0;
        }
        
        // This would need to be calculated from actual memory usage
        // For now, return a placeholder
        return 0;
    }

    /**
     * Calculate total execution time from tasks
     *
     * @param \Illuminate\Support\Collection $tasks Task collection
     * @return float Total execution time in seconds
     */
    private function calculateTotalExecutionTime($tasks): float
    {
        if ($tasks->isEmpty()) {
            return 0.0;
        }
        
        // This would need to be calculated from actual execution times
        // For now, return a placeholder
        return 0.0;
    }

    /**
     * Calculate error rate from tasks
     *
     * @param \Illuminate\Support\Collection $tasks Task collection
     * @return float Error rate percentage
     */
    private function calculateErrorRate($tasks): float
    {
        if ($tasks->isEmpty()) {
            return 0.0;
        }
        
        $failed = $tasks->where('status', sTaskModel::TASK_STATUS_FAILED)->count();
        return round(($failed / $tasks->count()) * 100, 2);
    }

    /**
     * Get worker statistics from tasks
     *
     * @param \Illuminate\Support\Collection $tasks Task collection
     * @return array<string, mixed> Worker statistics
     */
    private function getWorkerStatistics($tasks): array
    {
        return $tasks->groupBy('identifier')->map(function ($workerTasks, $identifier) {
            return [
                'identifier' => $identifier,
                'task_count' => $workerTasks->count(),
                'success_rate' => $this->calculateSuccessRate($workerTasks),
            ];
        })->toArray();
    }

    /**
     * Get error statistics from tasks
     *
     * @param \Illuminate\Support\Collection $tasks Task collection
     * @return array<string, mixed> Error statistics
     */
    private function getErrorStatistics($tasks): array
    {
        $failed = $tasks->where('status', sTaskModel::TASK_STATUS_FAILED);
        
        return [
            'total_errors' => $failed->count(),
            'error_rate' => $this->calculateErrorRate($tasks),
            'common_errors' => $this->getCommonErrors($failed),
        ];
    }

    /**
     * Get common errors from failed tasks
     *
     * @param \Illuminate\Support\Collection $failedTasks Failed task collection
     * @return array<string, int> Common errors with counts
     */
    private function getCommonErrors($failedTasks): array
    {
        // This would analyze error messages and group them
        // For now, return a placeholder
        return [];
    }

    /**
     * Get performance thresholds for alerts
     *
     * @return array<string, mixed> Performance thresholds
     */
    private function getPerformanceThresholds(): array
    {
        return [
            'min_success_rate' => 95.0,      // 95% minimum success rate
            'max_avg_duration' => 300.0,     // 5 minutes maximum average duration
            'max_avg_memory' => 100 * 1024 * 1024, // 100MB maximum average memory
        ];
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes Bytes to format
     * @return string Formatted string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

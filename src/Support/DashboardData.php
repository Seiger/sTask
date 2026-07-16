<?php namespace Seiger\sTask\Support;

use Illuminate\Support\Collection;
use Seiger\sTask\Facades\sTask as sTaskFacade;
use Seiger\sTask\Models\sTaskModel;

/**
 * Build presentation-ready data for the sTask dashboard and performance tabs.
 *
 * @since 2.0.0
 */
class DashboardData
{
    public function stats(): array
    {
        return sTaskFacade::getStats();
    }

    public function cards(): array
    {
        $stats = $this->stats();

        return [
            $this->card('pending_tasks', 'clock', 'neutral', (int)($stats['pending'] ?? 0), 'waiting_execution'),
            $this->card('running_tasks', 'player-play', 'primary', (int)($stats['running'] ?? 0), 'in_progress'),
            $this->card('completed_tasks', 'circle-check', 'success', (int)($stats['completed'] ?? 0), 'successfully_finished'),
            $this->card('failed_tasks', 'circle-x', 'danger', (int)($stats['failed'] ?? 0), 'with_errors'),
            $this->card('total_tasks', 'clipboard-list', 'neutral', (int)($stats['total'] ?? 0), 'all_time'),
            $this->card('workers', 'cpu', 'info', (int)($stats['active_workers'] ?? 0), 'active_workers'),
        ];
    }

    public function recentTasks(int $limit = 8): Collection
    {
        return sTaskModel::with(['worker', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (sTaskModel $task): array => $this->taskRow($task));
    }

    public function recentErrors(int $limit = 5): Collection
    {
        return sTaskModel::with(['worker', 'user'])
            ->where('status', sTaskModel::TASK_STATUS_FAILED)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (sTaskModel $task): array => $this->taskRow($task));
    }

    public function performanceCards(): array
    {
        $summary = sTaskFacade::getPerformanceMetrics(24);
        $cache = sTaskFacade::getCacheStats();
        $performance = (array)($summary['performance'] ?? []);
        $tasks = (array)($summary['tasks'] ?? []);

        return [
            $this->metricCard('total_tasks', 'clipboard-list', 'neutral', (int)($tasks['total'] ?? 0), 'last_24_hours'),
            $this->metricCard('success_rate', 'activity', 'success', (float)($performance['success_rate'] ?? 0) . '%', 'performance'),
            $this->metricCard('average_duration', 'timer', 'info', (float)($performance['average_duration'] ?? 0) . 's', 'performance'),
            $this->metricCard('cache_entries', 'database', 'primary', (int)($cache['total_cached'] ?? $cache['cached_workers'] ?? 0), 'worker_cache'),
        ];
    }

    /**
     * Return localized, presentation-ready performance alerts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function performanceAlerts(): array
    {
        return collect(sTaskFacade::getPerformanceAlerts())
            ->map(function (array $alert): array {
                $type = (string)($alert['type'] ?? '');
                $severity = (string)($alert['severity'] ?? 'info');
                $value = $alert['value'] ?? 0;
                $valueLabel = match ($type) {
                    'low_success_rate' => number_format((float)$value, 2, '.', ' ') . '%',
                    'high_execution_time' => niceEta((float)$value),
                    'high_memory_usage' => niceSize((float)$value),
                    default => is_scalar($value) ? (string)$value : '',
                };
                $message = match ($type) {
                    'low_success_rate' => __('sTask::global.performance_alert_low_success_rate', ['value' => $valueLabel]),
                    'high_execution_time' => __('sTask::global.performance_alert_high_execution_time', ['value' => $valueLabel]),
                    'high_memory_usage' => __('sTask::global.performance_alert_high_memory_usage', ['value' => $valueLabel]),
                    default => (string)($alert['message'] ?? ''),
                };

                return array_replace($alert, [
                    'severity_label' => __('sTask::global.alert_severity_' . $severity),
                    'message' => $message,
                    'value_label' => $valueLabel,
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * Return localized worker-cache statistics with formatted memory usage.
     *
     * @return array<int, array{key: string, label: string, value: string}>
     */
    public function cacheStats(): array
    {
        return collect(sTaskFacade::getCacheStats())
            ->map(function (mixed $value, string $key): array {
                $valueLabel = match ($key) {
                    'memory_usage' => niceSize((float)$value),
                    'hit_rate' => number_format((float)$value, 2, '.', ' ') . '%',
                    default => is_numeric($value) ? number_format((float)$value, 0, '.', ' ') : (string)$value,
                };

                return [
                    'key' => $key,
                    'label' => __('sTask::global.cache_stat_' . $key),
                    'value' => $valueLabel,
                ];
            })
            ->values()
            ->all();
    }

    protected function card(string $titleKey, string $icon, string $status, int $value, string $labelKey): array
    {
        return [
            'title' => __('sTask::global.' . $titleKey),
            'icon' => $icon,
            'span' => 2,
            'status' => $status,
            'stats' => [
                [
                    'value' => niceCount($value),
                    'label' => __('sTask::global.' . $labelKey),
                ],
            ],
        ];
    }

    protected function metricCard(string $titleKey, string $icon, string $status, int|float|string $value, string $labelKey): array
    {
        return [
            'title' => __('sTask::global.' . $titleKey),
            'icon' => $icon,
            'span' => 3,
            'status' => $status,
            'stats' => [
                [
                    'value' => is_numeric($value) ? number_format((float)$value, is_float($value) ? 1 : 0, '.', ' ') : (string)$value,
                    'label' => __('sTask::global.' . $labelKey),
                ],
            ],
        ];
    }

    /**
     * Convert a task model into dashboard row data, including live-state hints.
     *
     * @param sTaskModel $task Persisted task with its worker and user relations
     * @return array<string, mixed>
     */
    protected function taskRow(sTaskModel $task): array
    {
        $status = sTaskModel::statusText((int)$task->status);

        return [
            'id' => (int)$task->id,
            'identifier' => (string)$task->identifier,
            'worker_title' => (string)($task->worker->title ?? $task->identifier),
            'action' => (string)$task->action,
            'status' => (int)$task->status,
            'status_label' => __('sTask::global.' . $status),
            'status_color' => $this->statusColor((int)$task->status),
            'is_active' => in_array((int)$task->status, sTaskModel::activeStatuses(), true),
            'progress' => max(0, min(100, (int)$task->progress)),
            'created_at' => $task->created_at?->format('Y-m-d H:i') ?? '',
            'start_at' => $task->start_at?->format('Y-m-d H:i') ?? '',
            'message' => trim((string)($task->message ?? '')),
            'detail_url' => route('sTask.task.show', $task->id),
        ];
    }

    protected function statusColor(int $status): string
    {
        return match ($status) {
            sTaskModel::TASK_STATUS_RUNNING => '#2563EB',
            sTaskModel::TASK_STATUS_FINISHED => '#16A34A',
            sTaskModel::TASK_STATUS_FAILED => '#DC2626',
            sTaskModel::TASK_STATUS_PREPARING => '#D97706',
            default => '#64748B',
        };
    }
}

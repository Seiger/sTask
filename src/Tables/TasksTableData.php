<?php namespace Seiger\sTask\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Models\sWorker as sWorker;

class TasksTableData
{
    public function __construct(
        protected array $context = [],
        protected array $state = [],
        protected array $config = [],
    ) {}

    public function total(): int
    {
        return (clone $this->query())->count();
    }

    public function rows(int $page, int $perPage): array
    {
        return $this->query()
            ->forPage(max(1, $page), max(1, $perPage))
            ->get()
            ->map(fn (sTaskModel $task): array => $this->row($task))
            ->all();
    }

    public function filterGroups(): array
    {
        return [
            [
                'key' => 'worker_id',
                'items' => $this->workerOptions(),
            ],
            [
                'key' => 'action',
                'items' => $this->actionOptions(),
            ],
            [
                'key' => 'status',
                'items' => [
                    ['id' => sTaskModel::TASK_STATUS_QUEUED, 'label' => __('sTask::global.pending')],
                    ['id' => sTaskModel::TASK_STATUS_PREPARING, 'label' => __('sTask::global.preparing')],
                    ['id' => sTaskModel::TASK_STATUS_RUNNING, 'label' => __('sTask::global.running')],
                    ['id' => sTaskModel::TASK_STATUS_FINISHED, 'label' => __('sTask::global.completed')],
                    ['id' => sTaskModel::TASK_STATUS_FAILED, 'label' => __('sTask::global.failed')],
                ],
            ],
            [
                'key' => 'priority',
                'items' => [
                    ['id' => 1, 'label' => __('sTask::global.priority_low')],
                    ['id' => 2, 'label' => __('sTask::global.priority_normal')],
                    ['id' => 3, 'label' => __('sTask::global.priority_high')],
                ],
            ],
            [
                'key' => 'attempts',
                'items' => $this->attemptOptions(),
            ],
        ];
    }

    public function modalData(int $id): array
    {
        return app(LogsTableData::class)->modalData($id);
    }

    public function modalTitle(array $data, ?int $id, string $mode): string
    {
        return app(LogsTableData::class)->modalTitle($data, $id, $mode);
    }

    protected function query(): Builder
    {
        $query = sTaskModel::query()->with(['worker', 'user']);

        $search = trim((string)($this->state['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $scope) use ($search): void {
                if (ctype_digit($search)) {
                    $scope->orWhere('id', (int)$search);
                }

                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
                $scope
                    ->orWhere('identifier', 'like', $like)
                    ->orWhere('action', 'like', $like)
                    ->orWhere('message', 'like', $like);
            });
        }

        $filters = (array)($this->state['filters'] ?? []);

        $workerIds = collect((array)($filters['worker_id'] ?? []))
            ->map(fn ($id): int => (int)$id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($workerIds !== []) {
            $identifiers = sWorker::query()
                ->whereIn('id', $workerIds)
                ->pluck('identifier')
                ->filter()
                ->values()
                ->all();

            if ($identifiers !== []) {
                $query->whereIn('identifier', $identifiers);
            }
        }

        $actions = collect((array)($filters['action'] ?? []))
            ->map(fn ($id): int => (int)$id)
            ->filter(fn (int $id): bool => $id > 0)
            ->map(fn (int $id): string => (string)($this->actionMap()[$id] ?? ''))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($actions !== []) {
            $query->whereIn('action', $actions);
        }

        $statuses = collect((array)($filters['status'] ?? []))
            ->map(fn ($status): int => (int)$status)
            ->filter(fn (int $status): bool => in_array($status, [
                sTaskModel::TASK_STATUS_QUEUED,
                sTaskModel::TASK_STATUS_PREPARING,
                sTaskModel::TASK_STATUS_RUNNING,
                sTaskModel::TASK_STATUS_FINISHED,
                sTaskModel::TASK_STATUS_FAILED,
            ], true))
            ->unique()
            ->values()
            ->all();

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $priorities = collect((array)($filters['priority'] ?? []))
            ->map(fn ($priority): int => (int)$priority)
            ->map(fn (int $priority): ?string => $this->priorityFilterValue($priority))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($priorities !== []) {
            $query->whereIn('priority', $priorities);
        }

        $attempts = collect((array)($filters['attempts'] ?? []))
            ->map(fn ($id): int => (int)$id - 1)
            ->filter(fn (int $attempt): bool => $attempt >= 0)
            ->unique()
            ->values()
            ->all();

        if ($attempts !== []) {
            $query->whereIn('attempts', $attempts);
        }

        $range = (array)($filters['created_at'] ?? []);
        if (($from = $this->dateBoundary((string)($range['from'] ?? ''), true)) !== null) {
            $query->where('created_at', '>=', $from);
        }

        if (($to = $this->dateBoundary((string)($range['to'] ?? ''), false)) !== null) {
            $query->where('created_at', '<=', $to);
        }

        $sort = $this->sortField((string)($this->state['sort'] ?? ''));
        $direction = ((string)($this->state['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->orderBy('id', 'desc');
    }

    protected function row(sTaskModel $task): array
    {
        $status = sTaskModel::statusText((int)$task->status);
        $priority = (string)($task->priority ?: 'normal');

        return [
            'id' => (int)$task->id,
            'wire_key' => 'stask-task-' . $task->id,
            'id_label' => '#' . $task->id,
            'id_link' => [
                'label' => '#' . $task->id,
                'href' => route('sTask.task.show', $task->id),
                'strong' => true,
            ],
            'identifier' => (string)$task->identifier,
            'worker_identifier' => (string)$task->identifier,
            'worker_title' => (string)($task->worker->title ?? $task->identifier),
            'action' => (string)$task->action,
            'status' => (int)$task->status,
            'status_badge' => [
                'label' => __('sTask::global.' . $status),
                'color' => $this->statusColor((int)$task->status),
            ],
            'priority' => $priority,
            'priority_badge' => [
                'label' => __("sTask::global.priority_{$priority}"),
                'color' => $this->priorityColor($priority),
            ],
            'progress' => max(0, min(100, (int)$task->progress)),
            'progress_label' => max(0, min(100, (int)$task->progress)) . '%',
            'attempts_label' => (int)$task->attempts . ' / ' . (int)$task->max_attempts,
            'message_excerpt' => str($task->message ?: __('sTask::global.raw_log_empty'))->limit(80)->toString(),
            'started_by' => (string)($task->user->username ?? 'system'),
            'created_at_label' => $task->created_at?->format('Y-m-d H:i') ?? '',
            'start_at_label' => $task->start_at?->format('Y-m-d H:i') ?? '',
            'finished_at_label' => $task->finished_at?->format('Y-m-d H:i') ?? '',
            'updated_at_label' => $task->updated_at?->format('Y-m-d H:i') ?? '',
            'detail_url' => route('sTask.task.show', $task->id),
        ];
    }

    protected function workerOptions(): array
    {
        return sWorker::query()
            ->orderBy('scope')
            ->orderBy('identifier')
            ->get(['id', 'identifier'])
            ->map(fn (sWorker $worker): array => [
                'id' => (int)$worker->id,
                'label' => (string)$worker->identifier,
            ])
            ->all();
    }

    protected function actionOptions(): array
    {
        return collect($this->actionMap())
            ->map(fn (string $action, int $id): array => ['id' => $id, 'label' => $action])
            ->values()
            ->all();
    }

    protected function actionMap(): array
    {
        return sTaskModel::query()
            ->select('action')
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->filter()
            ->values()
            ->mapWithKeys(fn (string $action, int $index): array => [$index + 1 => $action])
            ->all();
    }

    protected function attemptOptions(): array
    {
        return sTaskModel::query()
            ->select('attempts')
            ->distinct()
            ->orderBy('attempts')
            ->pluck('attempts')
            ->map(fn ($attempt): int => max(0, (int)$attempt))
            ->unique()
            ->values()
            ->map(fn (int $attempt): array => [
                'id' => $attempt + 1,
                'label' => (string)$attempt,
            ])
            ->all();
    }

    protected function dateBoundary(string $date, bool $start): ?Carbon
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            return $start ? Carbon::parse($date)->startOfDay() : Carbon::parse($date)->endOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function sortField(string $sort): string
    {
        $column = collect($this->config['columns'] ?? [])
            ->first(fn ($column) => ($column['key'] ?? null) === $sort && ($column['sortable'] ?? false));

        if (is_array($column) && !empty($column['sort_field'])) {
            return (string)$column['sort_field'];
        }

        return 'created_at';
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

    protected function priorityColor(string $priority): string
    {
        return match ($priority) {
            'high' => '#D97706',
            'low' => '#64748B',
            default => '#2563EB',
        };
    }

    protected function priorityFilterValue(int $priority): ?string
    {
        return match ($priority) {
            1 => 'low',
            2 => 'normal',
            3 => 'high',
            default => null,
        };
    }
}

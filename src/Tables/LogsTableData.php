<?php namespace Seiger\sTask\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Models\sWorker;

class LogsTableData
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
                'items' => sWorker::query()
                    ->orderBy('scope')
                    ->orderBy('identifier')
                    ->get(['id', 'identifier'])
                    ->map(fn (sWorker $worker): array => [
                        'id' => (int)$worker->id,
                        'label' => (string)$worker->identifier,
                    ])
                    ->all(),
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
        ];
    }

    public function modalData(int $id): array
    {
        $task = sTaskModel::with(['worker', 'user'])->find($id);

        if (!$task) {
            return [];
        }

        return $this->detailPayload($task);
    }

    public function modalTitle(array $data, ?int $id, string $mode): string
    {
        return __('sTask::global.task') . ' #' . ((int)($id ?: ($data['id'] ?? 0)));
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
                    ->orWhere('message', 'like', $like)
                    ->orWhere('meta', 'like', $like)
                    ->orWhere('result', 'like', $like);
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

        $statuses = collect((array)($filters['status'] ?? []))
            ->map(fn ($status): int => (int)$status)
            ->filter(fn (int $status): bool => in_array($status, $this->allowedStatuses(), true))
            ->unique()
            ->values()
            ->all();

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
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
        $payload = $this->basePayload($task);

        return array_replace($payload, [
            'wire_key' => 'stask-log-' . $task->id,
            'id_link' => [
                'label' => '#' . $task->id,
                'href' => route('sTask.task.show', $task->id),
                'strong' => true,
            ],
        ]);
    }

    protected function detailPayload(sTaskModel $task): array
    {
        return array_replace($this->basePayload($task), [
            'worker_class' => (string)($task->worker->class ?? ''),
            'message_code' => trim((string)($task->message ?? '')) !== '' ? (string)$task->message : __('sTask::global.raw_log_empty'),
            'meta_code' => $this->prettyPayload($task->meta),
            'result_code' => $this->prettyPayload($task->result),
        ]);
    }

    protected function basePayload(sTaskModel $task): array
    {
        $status = sTaskModel::statusText((int)$task->status);
        $priority = (string)($task->priority ?: 'normal');
        $progress = max(0, min(100, (int)$task->progress));

        return [
            'id' => (int)$task->id,
            'id_label' => '#' . $task->id,
            'worker_identifier' => (string)$task->identifier,
            'worker_title' => (string)($task->worker->title ?? $task->identifier),
            'action' => (string)$task->action,
            'status_badge' => [
                'label' => __('sTask::global.' . $status),
                'color' => $this->statusColor((int)$task->status),
            ],
            'priority_badge' => [
                'label' => __("sTask::global.priority_{$priority}"),
                'color' => $this->priorityColor($priority),
            ],
            'progress_label' => $progress . '%',
            'attempts_label' => (int)$task->attempts . ' / ' . (int)$task->max_attempts,
            'started_by' => (string)($task->user->username ?? 'system'),
            'created_at_label' => $task->created_at?->format('Y-m-d H:i') ?? '',
            'start_at_label' => $task->start_at?->format('Y-m-d H:i') ?? '',
            'finished_at_label' => $task->finished_at?->format('Y-m-d H:i') ?? '',
            'updated_at_label' => $task->updated_at?->format('Y-m-d H:i') ?? '',
        ];
    }

    protected function allowedStatuses(): array
    {
        return [
            sTaskModel::TASK_STATUS_QUEUED,
            sTaskModel::TASK_STATUS_PREPARING,
            sTaskModel::TASK_STATUS_RUNNING,
            sTaskModel::TASK_STATUS_FINISHED,
            sTaskModel::TASK_STATUS_FAILED,
        ];
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

    protected function prettyPayload(mixed $payload): string
    {
        if ($payload === null || $payload === '' || $payload === []) {
            return __('sTask::global.raw_log_empty');
        }

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            } else {
                return $payload;
            }
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: __('sTask::global.raw_log_empty');
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
}

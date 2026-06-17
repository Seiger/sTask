<?php namespace Seiger\sTask\Tables;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Models\sWorker as sWorker;
use Seiger\sTask\Services\WorkerService;

class WorkersTableData
{
    public function __construct(
        protected array $context = [],
        protected array $state = [],
        protected array $config = [],
    ) {}

    public function total(): int
    {
        return $this->workers()->count();
    }

    public function rows(int $page, int $perPage): array
    {
        $workers = $this->workers()
            ->forPage(max(1, $page), max(1, $perPage))
            ->values();

        $lastTasks = $this->lastTasksFor($workers->pluck('identifier')->all());

        return $workers
            ->map(fn (sWorker $worker): array => $this->row($worker, $lastTasks[$worker->identifier] ?? null))
            ->all();
    }

    public function filterGroups(): array
    {
        return [
            [
                'key' => 'active',
                'items' => [
                    ['id' => 1, 'label' => __('sTask::global.active')],
                    ['id' => 2, 'label' => __('sTask::global.inactive')],
                ],
            ],
            [
                'key' => 'class_exists',
                'items' => [
                    ['id' => 1, 'label' => __('sTask::global.available')],
                    ['id' => 2, 'label' => __('sTask::global.missing')],
                ],
            ],
            [
                'key' => 'hidden',
                'items' => [
                    ['id' => 1, 'label' => __('sTask::global.visible')],
                    ['id' => 2, 'label' => __('sTask::global.hidden')],
                ],
            ],
        ];
    }

    public function togglePublished(int $id): void
    {
        $worker = sWorker::query()->find($id);

        if (!$worker) {
            return;
        }

        $worker->update(['active' => !$worker->active]);
    }

    public function modalData(int $id): array
    {
        $worker = sWorker::query()->find($id);

        if (!$worker) {
            return [];
        }

        $schedule = (array)(data_get($worker->settings ?? [], 'schedule', []));

        return [
            'title' => $worker->title,
            'identifier' => (string)$worker->identifier,
            'scope' => (string)$worker->scope,
            'active' => (bool)$worker->active,
            'hidden' => (int)$worker->hidden > 0,
            'position' => (int)$worker->position,
            'schedule_enabled' => (bool)($schedule['enabled'] ?? false),
            'schedule_type' => (string)($schedule['type'] ?? 'manual'),
            'schedule_datetime' => (string)($schedule['datetime'] ?? ''),
            'schedule_frequency' => (string)($schedule['frequency'] ?? 'hourly'),
            'schedule_time' => (string)($schedule['time'] ?? ''),
            'schedule_start_time' => (string)($schedule['start_time'] ?? ''),
            'schedule_end_time' => (string)($schedule['end_time'] ?? ''),
            'schedule_interval' => (string)($schedule['interval'] ?? 'hourly'),
            'class' => (string)$worker->class,
            'description' => $worker->description,
            'settings_url' => route('sTask.worker.settings', $worker->identifier),
        ];
    }

    public function modalTitle(array $data, ?int $id, string $mode): string
    {
        $title = trim((string)($data['title'] ?? ''));

        return $title !== '' ? __('sTask::global.edit_worker') . ': ' . $title : __('sTask::global.edit_worker');
    }

    public function modalHeaderMeta(array $data, ?int $id, string $mode): array
    {
        return [
            ['label' => __('sTask::global.identifier'), 'value' => (string)($data['identifier'] ?? '')],
            ['label' => __('sTask::global.scope'), 'value' => (string)($data['scope'] ?? '')],
        ];
    }

    public function saveModal(array $data, ?int $id, string $mode): ?int
    {
        if (!$id) {
            return null;
        }

        $worker = sWorker::query()->find($id);

        if (!$worker) {
            return null;
        }

        $worker->update([
            'active' => (bool)($data['active'] ?? false),
            'hidden' => !empty($data['hidden']) ? 1 : 0,
            'position' => max(0, (int)($data['position'] ?? 0)),
        ]);

        $settings = $worker->settings ?? [];
        $settings['schedule'] = [
            'enabled' => (bool)($data['schedule_enabled'] ?? false),
            'type' => $this->allowedValue((string)($data['schedule_type'] ?? 'manual'), ['manual', 'once', 'periodic', 'regular'], 'manual'),
            'datetime' => trim((string)($data['schedule_datetime'] ?? '')),
            'frequency' => $this->allowedValue((string)($data['schedule_frequency'] ?? 'hourly'), ['hourly', 'daily', 'weekly'], 'hourly'),
            'time' => trim((string)($data['schedule_time'] ?? '')),
            'start_time' => trim((string)($data['schedule_start_time'] ?? '')),
            'end_time' => trim((string)($data['schedule_end_time'] ?? '')),
            'interval' => $this->allowedValue((string)($data['schedule_interval'] ?? 'hourly'), ['every_5min', 'every_15min', 'every_30min', 'hourly'], 'hourly'),
        ];

        $worker->update(['settings' => $settings]);
        app(WorkerService::class)->clearCache((string)$worker->identifier);

        return (int)$worker->id;
    }

    public function runWorker(int $id, array $action = []): ?int
    {
        $worker = sWorker::query()->find($id);

        if (!$worker || !$this->canRun($worker)) {
            return null;
        }

        $instance = $worker->getInstance();

        if (!$instance || !method_exists($instance, 'createTask')) {
            return null;
        }

        $task = $instance->createTask('make', ['manual' => true, 'source' => 'workers_table']);
        $this->launchTaskWorker();

        return (int)$task->id;
    }

    public function runSelectedWorker(array $action = [], ?int $id = null): ?int
    {
        return $id ? $this->runWorker((int)$id, $action) : null;
    }

    public function toggleSelectedActive(array $action = [], ?int $id = null): ?int
    {
        if (!$id) {
            return null;
        }

        $this->togglePublished((int)$id);

        return (int)$id;
    }

    protected function workers(): Collection
    {
        $query = sWorker::query()->withCount('tasks');

        $search = trim((string)($this->state['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(function ($scope) use ($like): void {
                $scope
                    ->orWhere('identifier', 'like', $like)
                    ->orWhere('scope', 'like', $like)
                    ->orWhere('class', 'like', $like);
            });
        }

        $filters = (array)($this->state['filters'] ?? []);
        $active = $this->selectedFilterIds('active');
        if ($active === [1]) {
            $query->where('active', true);
        } elseif ($active === [2]) {
            $query->where('active', false);
        }

        $hidden = $this->selectedFilterIds('hidden');
        if ($hidden === [1]) {
            $query->where('hidden', 0);
        } elseif ($hidden === [2]) {
            $query->where('hidden', '>', 0);
        }

        $workers = $query->get();
        $classExists = $this->selectedFilterIds('class_exists');
        if ($classExists === [1]) {
            $workers = $workers->filter(fn (sWorker $worker): bool => $worker->class_exists);
        } elseif ($classExists === [2]) {
            $workers = $workers->reject(fn (sWorker $worker): bool => $worker->class_exists);
        }

        return $this->sortWorkers($workers)->values();
    }

    protected function sortWorkers(Collection $workers): Collection
    {
        $sort = (string)($this->state['sort'] ?? ($this->config['default_sort'] ?? 'position'));
        $direction = ((string)($this->state['direction'] ?? ($this->config['default_direction'] ?? 'asc'))) === 'desc' ? 'desc' : 'asc';
        $field = $this->sortField($sort);

        return $workers->sortBy(function (sWorker $worker) use ($field) {
            return match ($field) {
                'title' => mb_strtolower($worker->title),
                'identifier' => mb_strtolower((string)$worker->identifier),
                'scope' => mb_strtolower((string)$worker->scope),
                'active' => $worker->active ? 1 : 0,
                'class_exists' => $worker->class_exists ? 1 : 0,
                'hidden' => (int)$worker->hidden,
                'tasks_count' => (int)$worker->tasks_count,
                'updated_at' => optional($worker->updated_at)->timestamp ?? 0,
                default => (int)$worker->position,
            };
        }, SORT_REGULAR, $direction === 'desc');
    }

    protected function row(sWorker $worker, ?sTaskModel $lastTask): array
    {
        $classExists = $worker->class_exists;
        $status = $lastTask ? sTaskModel::statusText((int)$lastTask->status) : 'unknown';

        return [
            'id' => (int)$worker->id,
            'wire_key' => 'stask-worker-' . $worker->id,
            'worker_link' => [
                'label' => $worker->title,
                'href' => route('sTask.worker.settings', $worker->identifier),
                'strong' => true,
            ],
            'identifier' => (string)$worker->identifier,
            'scope' => (string)$worker->scope,
            'class' => (string)$worker->class,
            'description' => $worker->description,
            'description_excerpt' => str($worker->description ?: __('sTask::global.worker_description'))->limit(96)->toString(),
            'active' => (bool)$worker->active,
            'active_badge' => [
                'label' => $worker->active ? __('sTask::global.active') : __('sTask::global.inactive'),
                'color' => $worker->active ? '#16A34A' : '#64748B',
            ],
            'class_exists' => $classExists,
            'class_exists_badge' => [
                'label' => $classExists ? __('sTask::global.available') : __('sTask::global.missing'),
                'color' => $classExists ? '#16A34A' : '#DC2626',
            ],
            'hidden' => (int)$worker->hidden > 0,
            'hidden_badge' => [
                'label' => (int)$worker->hidden > 0 ? __('sTask::global.hidden') : __('sTask::global.visible'),
                'color' => (int)$worker->hidden > 0 ? '#D97706' : '#16A34A',
            ],
            'tasks_count' => (int)$worker->tasks_count,
            'can_run' => $this->canRun($worker),
            'run_disabled' => !$this->canRun($worker),
            'last_task_badge' => [
                'label' => $lastTask ? __('sTask::global.' . $status) : __('sTask::global.no_tasks_yet'),
                'color' => $lastTask ? $this->statusColor((int)$lastTask->status) : '#64748B',
            ],
            'position' => (int)$worker->position,
            'updated_at_label' => $worker->updated_at?->format('Y-m-d H:i') ?? '',
            'edit_url' => route('sTask.worker.settings', $worker->identifier),
            'settings_url' => route('sTask.worker.settings', $worker->identifier),
        ];
    }

    protected function lastTasksFor(array $identifiers): Collection
    {
        return sTaskModel::query()
            ->whereIn('identifier', array_values(array_filter($identifiers)))
            ->orderByDesc('created_at')
            ->get()
            ->unique('identifier')
            ->keyBy('identifier');
    }

    protected function sortField(string $sort): string
    {
        $column = collect($this->config['columns'] ?? [])
            ->first(fn ($column) => ($column['key'] ?? null) === $sort && ($column['sortable'] ?? false));

        if (is_array($column) && !empty($column['sort_field'])) {
            return (string)$column['sort_field'];
        }

        return 'position';
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

    protected function canRun(sWorker $worker): bool
    {
        if (!$worker->active || !$worker->class_exists) {
            return false;
        }

        $instance = $worker->getInstance();

        return $instance && method_exists($instance, 'taskMake');
    }

    protected function allowedValue(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    protected function launchTaskWorker(): void
    {
        try {
            $artisanPath = defined('EVO_CORE_PATH') ? EVO_CORE_PATH . 'artisan' : '';

            if ($artisanPath === '' || !is_file($artisanPath)) {
                return;
            }

            $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
            $command = 'php ' . escapeshellarg($artisanPath) . ' stask:worker > /dev/null 2>&1 &';

            if (function_exists('exec') && !in_array('exec', $disabled, true)) {
                exec($command);
                return;
            }

            if (function_exists('shell_exec') && !in_array('shell_exec', $disabled, true)) {
                shell_exec($command);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to launch sTask worker from workers table action', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function selectedFilterIds(string $key): array
    {
        $filters = (array)($this->state['filters'] ?? []);

        return collect((array)($filters[$key] ?? []))
            ->map(fn ($value): int => (int)$value)
            ->filter(fn (int $value): bool => in_array($value, [1, 2], true))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}

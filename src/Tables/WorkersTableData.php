<?php namespace Seiger\sTask\Tables;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Seiger\sTask\Contracts\SupervisorWorkerInterface;
use Seiger\sTask\Models\sSupervisorState;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Models\sWorker as sWorker;
use Seiger\sTask\Services\WorkerDiscovery;
use Seiger\sTask\Services\WorkerService;
use Seiger\sTask\Support\LiveProgressRow;

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
        $activeTasks = $this->activeTasksFor($workers->pluck('identifier')->all());

        return $workers
            ->map(fn (sWorker $worker): array => $this->row(
                $worker,
                $lastTasks[$worker->identifier] ?? null,
                $activeTasks[$worker->identifier] ?? null,
            ))
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
        $worker = sWorker::query()->with('supervisorStates')->find($id);

        if (!$worker) {
            return;
        }

        $worker->update(['active' => !$worker->active]);
    }

    public function toggleVisibility(int $id): void
    {
        $worker = sWorker::query()->find($id);

        if (!$worker) {
            return;
        }

        $worker->update(['hidden' => (int)$worker->hidden > 0 ? 0 : 1]);
    }

    public function modalData(int $id): array
    {
        $worker = sWorker::query()->find($id);

        if (!$worker) {
            return [];
        }

        $schedule = (array)(data_get($worker->settings ?? [], 'schedule', []));
        $scheduleType = (string)($schedule['type'] ?? 'manual');
        $scheduleFrequency = $scheduleType === 'regular' && !empty($schedule['interval'])
            ? (string)$schedule['interval']
            : (string)($schedule['frequency'] ?? 'hourly');
        $scheduleTime = (string)($schedule['time'] ?? '');
        $scheduleHourlyMinute = str_starts_with($scheduleTime, '*:')
            ? substr($scheduleTime, 2)
            : '';
        $settingsPayload = $worker->settings ?? [];
        unset($settingsPayload['schedule']);
        $supervisorState = $this->latestSupervisorState($worker);
        $supportsSupervisor = $this->supportsSupervisor($worker);

        return [
            'title' => $worker->title,
            'identifier' => (string)$worker->identifier,
            'scope' => (string)$worker->scope,
            'active' => (bool)$worker->active,
            'hidden' => (int)$worker->hidden > 0,
            'position' => (int)$worker->position,
            'schedule_enabled' => (bool)($schedule['enabled'] ?? false),
            'schedule_type' => $scheduleType,
            'supports_supervisor' => $supportsSupervisor,
            'schedule_datetime' => (string)($schedule['datetime'] ?? ''),
            'schedule_frequency' => $scheduleFrequency,
            'schedule_hourly_minute' => $scheduleHourlyMinute,
            'schedule_time' => $scheduleFrequency === 'hourly' ? '' : $scheduleTime,
            'schedule_start_time' => (string)($schedule['start_time'] ?? ''),
            'schedule_end_time' => (string)($schedule['end_time'] ?? ''),
            'supervisor_key' => (string)($supervisorState?->supervisor_key ?? ''),
            'supervisor_state_badge' => $this->supervisorStateBadge($supervisorState),
            'supervisor_pid' => $supervisorState?->pid !== null ? (string)$supervisorState->pid : '',
            'supervisor_heartbeat_at' => $supervisorState?->heartbeat_at?->format('Y-m-d H:i:s') ?? '',
            'supervisor_uptime' => $supervisorState?->uptime_seconds !== null ? niceEta((float)$supervisorState->uptime_seconds) : '',
            'supervisor_last_diagnostic' => (string)($supervisorState?->message ?? ''),
            'supervisor_last_transition_at' => $supervisorState?->last_transition_at?->format('Y-m-d H:i:s') ?? '',
            'settings_payload' => json_encode($settingsPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            'class' => (string)$worker->class,
            'description' => $worker->description,
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
        $customSettings = $this->decodeSettingsPayload((string)($data['settings_payload'] ?? '{}'));

        if ($customSettings === null) {
            $customSettings = Arr::except($settings, ['schedule']);
        }

        $settings = $customSettings;
        $scheduleType = $this->allowedValue((string)($data['schedule_type'] ?? 'manual'), ['manual', 'once', 'periodic', 'regular', 'supervisor'], 'manual');
        if ($scheduleType === 'supervisor' && !$this->supportsSupervisor($worker)) {
            throw new \InvalidArgumentException(__('sTask::global.supervisor_schedule_unsupported'));
        }
        $scheduleFrequency = $this->allowedValue(
            (string)($data['schedule_frequency'] ?? 'hourly'),
            $scheduleType === 'regular'
                ? ['every_5min', 'every_15min', 'every_30min', 'hourly']
                : ['minutely', 'every_5min', 'every_15min', 'every_30min', 'hourly', 'daily', 'weekly', 'monthly'],
            'hourly'
        );
        $settings['schedule'] = [
            'enabled' => (bool)($data['schedule_enabled'] ?? false),
            'type' => $scheduleType,
            'datetime' => trim((string)($data['schedule_datetime'] ?? '')),
            'frequency' => $scheduleFrequency,
            'time' => $scheduleType === 'periodic' && $scheduleFrequency === 'hourly'
                ? '*:' . str_pad((string)max(0, min(59, (int)($data['schedule_hourly_minute'] ?? 0))), 2, '0', STR_PAD_LEFT)
                : trim((string)($data['schedule_time'] ?? '')),
            'start_time' => trim((string)($data['schedule_start_time'] ?? '')),
            'end_time' => trim((string)($data['schedule_end_time'] ?? '')),
        ];

        $worker->update(['settings' => $settings]);
        app(WorkerService::class)->clearCache((string)$worker->identifier);

        return (int)$worker->id;
    }

    public function scheduleFrequencyOptions(array $field, array $data, ?int $id = null, string $mode = 'edit'): array
    {
        return ((string)($data['schedule_type'] ?? 'manual')) === 'regular'
            ? [
                ['value' => 'every_5min', 'label' => 'sTask::global.interval_5min'],
                ['value' => 'every_15min', 'label' => 'sTask::global.interval_15min'],
                ['value' => 'every_30min', 'label' => 'sTask::global.interval_30min'],
                ['value' => 'hourly', 'label' => 'sTask::global.interval_hourly'],
            ]
            : [
                ['value' => 'minutely', 'label' => 'sTask::global.frequency_minutely'],
                ['value' => 'every_5min', 'label' => 'sTask::global.interval_5min'],
                ['value' => 'every_15min', 'label' => 'sTask::global.interval_15min'],
                ['value' => 'every_30min', 'label' => 'sTask::global.interval_30min'],
                ['value' => 'hourly', 'label' => 'sTask::global.frequency_hourly'],
                ['value' => 'daily', 'label' => 'sTask::global.frequency_daily'],
                ['value' => 'weekly', 'label' => 'sTask::global.frequency_weekly'],
                ['value' => 'monthly', 'label' => 'sTask::global.frequency_monthly'],
            ];
    }

    /**
     * Return schedule types supported by the selected worker implementation.
     *
     * Supervisor is an optional capability of a conventional worker. Manual,
     * one-time, periodic, and regular schedules therefore remain available to
     * every worker, while Supervisor is exposed only when its class implements
     * the dedicated lifecycle contract.
     *
     * @param array<string, mixed> $field Modal field definition
     * @param array<string, mixed> $data Current modal data
     * @param int|null $id Persisted worker identifier
     * @param string $mode Modal mode
     * @return array<int, array{value: string, label: string}> Supported schedule options
     */
    public function scheduleTypeOptions(array $field, array $data, ?int $id = null, string $mode = 'edit'): array
    {
        $options = [
            ['value' => 'manual', 'label' => 'sTask::global.schedule_manual'],
            ['value' => 'once', 'label' => 'sTask::global.schedule_once'],
            ['value' => 'periodic', 'label' => 'sTask::global.schedule_periodic'],
            ['value' => 'regular', 'label' => 'sTask::global.schedule_regular'],
        ];
        $worker = $id ? sWorker::query()->find($id) : null;

        if ($worker && $this->supportsSupervisor($worker)) {
            $options[] = ['value' => 'supervisor', 'label' => 'sTask::global.schedule_supervisor'];
        }

        return $options;
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

    public function runSelectedWorkerAttributes(array $action = [], ?int $id = null): array
    {
        if (!$id) {
            return [];
        }

        $worker = sWorker::query()->find($id);

        if (!$worker || !$this->canRun($worker)) {
            return ['disabled' => true];
        }

        return [];
    }

    public function toggleSelectedActive(array $action = [], ?int $id = null): ?int
    {
        if (!$id) {
            return null;
        }

        $this->togglePublished((int)$id);

        return (int)$id;
    }

    public function refreshWorkerRegistry(array $action = [], ?int $id = null): ?int
    {
        $discovery = app(WorkerDiscovery::class);

        $discovery->discover();
        $discovery->rescan();
        $discovery->cleanOrphaned();

        app(WorkerService::class)->clearCache();

        return null;
    }

    protected function workers(): Collection
    {
        $query = sWorker::query()->with('supervisorStates')->withCount('tasks');

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

    /**
     * Convert a worker and its task state into a module-table row.
     *
     * @param sWorker $worker Worker represented by the row
     * @param sTaskModel|null $lastTask Most recently created task
     * @param sTaskModel|null $activeTask Current task used for live progress
     * @return array<string, mixed>
     */
    protected function row(sWorker $worker, ?sTaskModel $lastTask, ?sTaskModel $activeTask): array
    {
        $classExists = $worker->class_exists;
        $schedule = (array)data_get($worker->settings ?? [], 'schedule', []);
        $supervisorState = $this->latestSupervisorState($worker);

        return [
            'id' => (int)$worker->id,
            'wire_key' => 'stask-worker-' . $worker->id,
            'row_attributes' => LiveProgressRow::attributes($activeTask),
            'worker_title' => $worker->title,
            'identifier' => (string)$worker->identifier,
            'scope' => (string)$worker->scope,
            'class' => (string)$worker->class,
            'description' => $worker->description,
            'description_excerpt' => str($worker->description ?: __('sTask::global.worker_description'))->limit(96)->toString(),
            'schedule_label' => $this->scheduleLabel($schedule),
            'schedule_display' => $this->scheduleDisplay($schedule, $supervisorState),
            'supervisor_state_badge' => $this->supervisorStateBadge($supervisorState),
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
            'tasks_count_label' => niceCount((int)$worker->tasks_count),
            'can_run' => $this->canRun($worker),
            'run_disabled' => !$this->canRun($worker),
            'last_action_label' => $lastTask?->action ?? '',
            'position' => (int)$worker->position,
            'last_run_at_label' => $lastTask?->created_at?->format('Y-m-d H:i') ?? '',
            'updated_at_label' => $worker->updated_at?->format('Y-m-d H:i') ?? '',
        ];
    }

    /**
     * Replace the raw JSON settings field with optional worker-owned manager surfaces.
     *
     * Workers may expose a trusted settings form through renderSettings() and a
     * read-only generated file list through getGeneratedFiles(). The returned field
     * descriptors are rendered by the matching EvoUI modal field extensions.
     *
     * @param array<int, array<string, mixed>> $fields Configured modal fields
     * @param array<string, mixed> $data Current modal payload
     * @param int|null $id Persisted worker identifier
     * @return array<int, array<string, mixed>> Modal fields adapted to worker capabilities
     * @since 2.2.0
     */
    public function modalFields(array $fields, array $data, ?int $id = null): array
    {
        $worker = $id ? sWorker::query()->find($id) : null;
        $settingsFormHtml = $worker ? $this->renderWorkerSettings($worker) : '';
        $generatedFiles = $worker ? $this->workerGeneratedFiles($worker) : [];

        if ($settingsFormHtml === '' && $generatedFiles === []) {
            return $fields;
        }

        return collect($fields)
            ->flatMap(function (array $field) use ($settingsFormHtml, $generatedFiles): array {
                if (($field['name'] ?? null) !== 'settings_payload') {
                    return [$field];
                }

                if ($settingsFormHtml !== '') {
                    $field['type'] = 'worker-settings';
                    $field['html'] = $settingsFormHtml;
                }

                if ($generatedFiles === []) {
                    return [$field];
                }

                return [
                    $field,
                    [
                        'name' => 'generated_files',
                        'type' => 'worker-files',
                        'label' => false,
                        'title' => __('sTask::global.generated_files'),
                        'open_label' => __('sTask::global.open_file'),
                        'files' => $generatedFiles,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    protected function scheduleLabel(array $schedule): string
    {
        if (empty($schedule['enabled'])) {
            return '';
        }

        $type = (string)($schedule['type'] ?? 'manual');

        if ($type === 'supervisor') {
            return __('sTask::global.schedule_supervisor_short');
        }

        if ($type === 'once') {
            $datetime = trim((string)($schedule['datetime'] ?? ''));

            return $datetime !== '' ? __('sTask::global.schedule_once_label') . ' ' . $datetime : '';
        }

        if ($type === 'regular') {
            $frequency = (string)($schedule['frequency'] ?? $schedule['interval'] ?? 'hourly');
            $label = $this->scheduleFrequencyLabel($frequency);
            $start = trim((string)($schedule['start_time'] ?? ''));
            $end = trim((string)($schedule['end_time'] ?? ''));

            return $start !== '' && $end !== ''
                ? $label . ' ' . $start . '-' . $end
                : $label;
        }

        if ($type !== 'periodic') {
            return '';
        }

        $frequency = (string)($schedule['frequency'] ?? 'hourly');
        $label = $this->scheduleFrequencyLabel($frequency);
        $time = trim((string)($schedule['time'] ?? ''));

        return $time !== '' && !in_array($frequency, ['minutely', 'every_5min', 'every_15min', 'every_30min'], true)
            ? $label . ' ' . __('sTask::global.schedule_at') . ' ' . $time
            : $label;
    }

    /**
     * Build the compact schedule cell used by the workers table and list view.
     *
     * @param array<string, mixed> $schedule Worker schedule settings
     * @param sSupervisorState|null $supervisorState Current persisted supervisor state
     * @return array<int, array<string, mixed>> Schedule chip descriptors
     */
    protected function scheduleDisplay(array $schedule, ?sSupervisorState $supervisorState): array
    {
        $label = $this->scheduleLabel($schedule);

        if ($label === '') {
            return [];
        }

        $item = [
            'label' => $label,
            'icon' => 'clock',
        ];

        if ((string)($schedule['type'] ?? '') === 'supervisor') {
            $item['icon'] = 'activity-heartbeat';
            $badge = $this->supervisorScheduleBadge($supervisorState);

            if ($badge) {
                $item['badge'] = $badge['label'];
                $item['color'] = $badge['color'];
            }
        }

        return [$item];
    }

    protected function scheduleFrequencyLabel(string $frequency): string
    {
        return match ($frequency) {
            'minutely' => __('sTask::global.frequency_minutely'),
            'every_5min' => __('sTask::global.interval_5min'),
            'every_15min' => __('sTask::global.interval_15min'),
            'every_30min' => __('sTask::global.interval_30min'),
            'daily' => __('sTask::global.frequency_daily'),
            'weekly' => __('sTask::global.frequency_weekly'),
            'monthly' => __('sTask::global.frequency_monthly'),
            default => __('sTask::global.frequency_hourly'),
        };
    }

    /**
     * Resolve the most recently observed supervisor state for a worker.
     *
     * @param sWorker $worker Worker with its supervisorStates relation loaded
     * @return sSupervisorState|null Latest live state, if the supervisor has been observed
     */
    protected function latestSupervisorState(sWorker $worker): ?sSupervisorState
    {
        return $worker->supervisorStates
            ->sortByDesc(fn (sSupervisorState $state): int => $state->last_seen_at?->timestamp ?? 0)
            ->first();
    }

    /**
     * Build the translated supervisor-state badge used by the worker table and modal.
     *
     * @param sSupervisorState|null $state Current persisted supervisor state
     * @return array{label: string, color: string}|null Badge descriptor or null
     */
    protected function supervisorStateBadge(?sSupervisorState $state): ?array
    {
        if (!$state) {
            return null;
        }

        $value = (string)$state->state;

        return [
            'label' => __('sTask::global.supervisor_state_' . $value),
            'color' => match ($value) {
                'healthy' => '#16A34A',
                'starting' => '#2563EB',
                'degraded' => '#D97706',
                'failed', 'stopped' => '#DC2626',
                default => '#64748B',
            },
        ];
    }

    /**
     * Build the table badge, replacing a healthy state label with process uptime.
     *
     * @param sSupervisorState|null $state Current persisted supervisor state
     * @return array{label: string, color: string}|null Badge descriptor or null
     */
    protected function supervisorScheduleBadge(?sSupervisorState $state): ?array
    {
        $badge = $this->supervisorStateBadge($state);

        if ($badge && (string)$state?->state === 'healthy' && $state?->uptime_seconds !== null) {
            $badge['label'] = niceEta((float)$state->uptime_seconds);
        }

        return $badge;
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

    /**
     * Resolve the newest active task for every visible worker.
     *
     * @param array<int, string> $identifiers Visible worker identifiers
     * @return Collection<string, sTaskModel>
     */
    protected function activeTasksFor(array $identifiers): Collection
    {
        return sTaskModel::query()
            ->whereIn('identifier', array_values(array_filter($identifiers)))
            ->whereIn('status', sTaskModel::activeStatuses())
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

    protected function decodeSettingsPayload(string $payload): ?array
    {
        $payload = trim($payload);

        if ($payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? Arr::except($decoded, ['schedule']) : null;
    }

    /**
     * Render a worker-owned settings form for the manager edit modal.
     *
     * Rendering failures are isolated from the workers table so an invalid optional
     * integration form cannot make the complete sTask manager surface unavailable.
     *
     * @param sWorker $worker Persisted worker whose runtime class may provide renderSettings()
     * @return string Trusted worker settings markup or an empty string when unsupported
     * @since 2.2.0
     */
    protected function renderWorkerSettings(sWorker $worker): string
    {
        if (!$worker->class_exists) {
            return '';
        }

        try {
            $instance = $worker->getInstance();

            return $instance && method_exists($instance, 'renderSettings')
                ? trim((string)$instance->renderSettings())
                : '';
        } catch (\Throwable $e) {
            Log::warning('Failed to render custom sTask worker settings', [
                'identifier' => (string)$worker->identifier,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Normalize optional generated file metadata exposed by a worker.
     *
     * Only named entries cross the provider boundary. Missing URLs remain valid so
     * workers may report files that are visible but not publicly downloadable.
     *
     * @param sWorker $worker Persisted worker whose runtime class may provide getGeneratedFiles()
     * @return array<int, array{filename: string, url: string}> Safe file descriptors for the modal
     * @since 2.2.0
     */
    protected function workerGeneratedFiles(sWorker $worker): array
    {
        if (!$worker->class_exists) {
            return [];
        }

        try {
            $instance = $worker->getInstance();

            if (!$instance || !method_exists($instance, 'getGeneratedFiles')) {
                return [];
            }

            return collect((array)$instance->getGeneratedFiles())
                ->filter(fn ($file): bool => is_array($file) && trim((string)($file['filename'] ?? '')) !== '')
                ->map(fn (array $file): array => [
                    'filename' => trim((string)$file['filename']),
                    'url' => trim((string)($file['url'] ?? '')),
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Failed to load generated sTask worker files', [
                'identifier' => (string)$worker->identifier,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    protected function canRun(sWorker $worker): bool
    {
        if (!$worker->active || !$worker->class_exists) {
            return false;
        }

        $instance = $worker->getInstance();

        return $instance && method_exists($instance, 'taskMake');
    }

    /**
     * Determine whether a worker exposes the optional Supervisor lifecycle capability.
     *
     * @param sWorker $worker Persisted worker configuration
     * @return bool True when the resolved worker implements SupervisorWorkerInterface
     */
    protected function supportsSupervisor(sWorker $worker): bool
    {
        if (!$worker->class_exists) {
            return false;
        }

        try {
            return $worker->getInstance() instanceof SupervisorWorkerInterface;
        } catch (\Throwable) {
            return false;
        }
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

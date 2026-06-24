<x-evo::module-tab-shell :tabs="$tabs" model="activeTab">
    <div x-show="activeTab === 'dashboard'" x-cloak>
        <x-evo::dashboard :cards="$dashboardCards">
            <x-slot:body>
                <section class="evo-ui-dashboard-section">
                    <div class="evo-ui-card__header">
                        <x-evo::icon name="activity" />
                        <h3>@lang('sTask::global.recent_tasks')</h3>
                    </div>

                    @if($recentTaskRows->isNotEmpty())
                        <div class="evo-ui-table-wrap">
                            <table class="evo-ui-table evo-ui-table--module">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>@lang('sTask::global.worker')</th>
                                        <th>@lang('sTask::global.action')</th>
                                        <th>@lang('sTask::global.status')</th>
                                        <th>@lang('sTask::global.progress')</th>
                                        <th>@lang('sTask::global.created')</th>
                                        <th>@lang('sTask::global.actions')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentTaskRows as $task)
                                        <tr wire:key="stask-dashboard-task-{{ $task['id'] }}" wire:dblclick="openTaskDetails({{ (int)$task['id'] }})">
                                            <td>#{{ $task['id'] }}</td>
                                            <td>{{ $task['worker_title'] }}</td>
                                            <td>{{ $task['action'] }}</td>
                                            <td><x-evo::badge :label="$task['status_label']" :color="$task['status_color']" /></td>
                                            <td>{{ $task['progress'] }}%</td>
                                            <td>{{ $task['created_at'] }}</td>
                                            <td class="evo-ui-row-actions-cell">
                                                <div class="evo-ui-row-actions">
                                                    <button
                                                        type="button"
                                                        class="evo-ui-row-action evo-ui-row-action--primary"
                                                        title="@lang('sTask::global.details')"
                                                        aria-label="@lang('sTask::global.details')"
                                                        wire:click.stop="openTaskDetails({{ (int)$task['id'] }})"
                                                    >
                                                        <x-evo::icon name="eye" />
                                                        <span class="evo-ui-sr-only">@lang('sTask::global.details')</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="evo-ui-empty">@lang('sTask::global.no_tasks_yet')</p>
                    @endif
                </section>

                @if($recentErrorRows->isNotEmpty())
                    <section class="evo-ui-dashboard-section">
                        <div class="evo-ui-card__header">
                            <x-evo::icon name="circle-x" />
                            <h3>@lang('sTask::global.recent_error_logs')</h3>
                        </div>

                        <div class="evo-ui-table-wrap">
                            <table class="evo-ui-table evo-ui-table--module">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>@lang('sTask::global.worker')</th>
                                        <th>@lang('sTask::global.message')</th>
                                        <th>@lang('sTask::global.created')</th>
                                        <th>@lang('sTask::global.actions')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentErrorRows as $task)
                                        <tr wire:key="stask-dashboard-error-{{ $task['id'] }}" wire:dblclick="openTaskDetails({{ (int)$task['id'] }})">
                                            <td>#{{ $task['id'] }}</td>
                                            <td>{{ $task['worker_title'] }}</td>
                                            <td>{{ $task['message'] !== '' ? $task['message'] : __('sTask::global.raw_log_empty') }}</td>
                                            <td>{{ $task['created_at'] }}</td>
                                            <td class="evo-ui-row-actions-cell">
                                                <div class="evo-ui-row-actions">
                                                    <button
                                                        type="button"
                                                        class="evo-ui-row-action evo-ui-row-action--primary"
                                                        title="@lang('sTask::global.details')"
                                                        aria-label="@lang('sTask::global.details')"
                                                        wire:click.stop="openTaskDetails({{ (int)$task['id'] }})"
                                                    >
                                                        <x-evo::icon name="eye" />
                                                        <span class="evo-ui-sr-only">@lang('sTask::global.details')</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif
            </x-slot:body>
        </x-evo::dashboard>

        <x-evo::modal
            :open="$detailModalOpen"
            :title="$detailModalTitle"
            icon="file-text"
            :meta="$detailModalMeta"
            size="lg"
        >
            <div class="evo-ui-modal__body">
                <div class="evo-ui-modal__grid">
                    <div class="evo-ui-static-field">
                        <strong>ID</strong>
                        <span>{{ $detailModalData['id_label'] ?? '' }}</span>
                    </div>
                    <div class="evo-ui-static-field">
                        <strong>@lang('sTask::global.worker')</strong>
                        <span>{{ $detailModalData['worker_title'] ?? '' }}</span>
                    </div>
                    <div class="evo-ui-static-field">
                        <strong>@lang('sTask::global.action')</strong>
                        <span>{{ $detailModalData['action'] ?? '' }}</span>
                    </div>
                    <div class="evo-ui-static-field">
                        <strong>@lang('sTask::global.status')</strong>
                        <x-evo::badge :value="$detailModalData['status_badge'] ?? []" />
                    </div>
                    <div class="evo-ui-static-field">
                        <strong>@lang('sTask::global.progress')</strong>
                        <span>{{ $detailModalData['progress_label'] ?? '' }}</span>
                    </div>
                    <div class="evo-ui-static-field">
                        <strong>@lang('sTask::global.priority')</strong>
                        <x-evo::badge :value="$detailModalData['priority_badge'] ?? []" />
                    </div>
                    <div class="evo-ui-static-field">
                        <strong>@lang('sTask::global.attempts')</strong>
                        <span>{{ $detailModalData['attempts_label'] ?? '' }}</span>
                    </div>
                    <div class="evo-ui-static-field">
                        <strong>@lang('sTask::global.created')</strong>
                        <span>{{ $detailModalData['created_at_label'] ?? '' }}</span>
                    </div>
                    <div class="evo-ui-static-field">
                        <strong>@lang('sTask::global.start_at')</strong>
                        <span>{{ $detailModalData['start_at_label'] ?? '' }}</span>
                    </div>
                    <div class="evo-ui-static-field">
                        <strong>@lang('sTask::global.finished_at')</strong>
                        <span>{{ $detailModalData['finished_at_label'] ?? '' }}</span>
                    </div>
                </div>

                <div class="evo-ui-modal__grid">
                    <div class="evo-ui-field evo-ui-modal__full">
                        <label class="evo-ui-label">@lang('sTask::global.task_log')</label>
                        <pre class="evo-ui-code-block">{{ $detailModalData['message_code'] ?? __('sTask::global.raw_log_empty') }}</pre>
                    </div>
                    <div class="evo-ui-field evo-ui-modal__full">
                        <label class="evo-ui-label">@lang('sTask::global.meta')</label>
                        <pre class="evo-ui-code-block">{{ $detailModalData['meta_code'] ?? __('sTask::global.raw_log_empty') }}</pre>
                    </div>
                    <div class="evo-ui-field evo-ui-modal__full">
                        <label class="evo-ui-label">@lang('sTask::global.result')</label>
                        <pre class="evo-ui-code-block">{{ $detailModalData['result_code'] ?? __('sTask::global.raw_log_empty') }}</pre>
                    </div>
                </div>
            </div>
            <footer class="evo-ui-modal__footer">
                <span class="evo-ui-modal__footer-spacer" aria-hidden="true"></span>
                <button type="button" class="evo-ui-btn evo-ui-btn--secondary" wire:click="closeModal">
                    @lang('evo::global.action_cancel')
                </button>
            </footer>
        </x-evo::modal>
    </div>

    <div x-show="activeTab === 'tasks'" x-cloak>
        <livewire:evo-ui.module-table
            preset="stask.tasks"
            :context="['module' => 'stask']"
            wire:key="stask-tasks-table"
        />
    </div>

    <div x-show="activeTab === 'workers'" x-cloak>
        <livewire:evo-ui.module-table
            preset="stask.workers"
            :context="['module' => 'stask']"
            wire:key="stask-workers-table"
        />
    </div>

    <div x-show="activeTab === 'logs'" x-cloak>
        <livewire:evo-ui.module-table
            preset="stask.logs"
            :context="['module' => 'stask']"
            wire:key="stask-logs-table"
        />
    </div>

    <div x-show="activeTab === 'performance'" x-cloak>
        <x-evo::dashboard :cards="$performanceCards">
            <x-slot:body>
                <section class="evo-ui-dashboard-section">
                    <div class="evo-ui-card__header">
                        <x-evo::icon name="bell" />
                        <h3>@lang('sTask::global.performance_alerts')</h3>
                    </div>

                    @if(!empty($performanceAlerts))
                        <div class="evo-ui-table-wrap">
                            <table class="evo-ui-table evo-ui-table--module">
                                <thead>
                                    <tr>
                                        <th>@lang('sTask::global.status')</th>
                                        <th>@lang('sTask::global.message')</th>
                                        <th>@lang('sTask::global.value')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($performanceAlerts as $alert)
                                        <tr>
                                            <td>{{ $alert['severity'] ?? 'info' }}</td>
                                            <td>{{ $alert['message'] ?? '' }}</td>
                                            <td>{{ $alert['value'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="evo-ui-empty">@lang('sTask::global.no_performance_alerts')</p>
                    @endif
                </section>

                <section class="evo-ui-dashboard-section">
                    <div class="evo-ui-card__header">
                        <x-evo::icon name="database" />
                        <h3>@lang('sTask::global.worker_cache')</h3>
                    </div>
                    <div class="evo-ui-modal__grid">
                        @foreach($cacheStats as $key => $value)
                            <div class="evo-ui-static-field">
                                <strong>{{ str_replace('_', ' ', (string)$key) }}</strong>
                                <span>{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</span>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="evo-ui-btn evo-ui-btn--secondary" wire:click="clearWorkerCache">
                        <x-evo::icon name="trash" />
                        @lang('sTask::global.clear_cache')
                    </button>
                </section>
            </x-slot:body>
        </x-evo::dashboard>
    </div>
</x-evo::module-tab-shell>

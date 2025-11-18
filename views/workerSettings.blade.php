@extends('sTask::index')
@section('content')
    <style>
        .worker-settings {
            max-width: 800px;
            margin: 0 auto;
        }
        .worker-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .worker-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .worker-icon {
            font-size: 2rem;
            color: white;
        }
        .worker-details h1 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .worker-identifier {
            font-family: 'Courier New', monospace;
            background: rgba(255,255,255,0.2);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .settings-section {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .settings-section h3 {
            margin: 0 0 1rem 0;
            color: #374151;
            font-size: 1.1rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .btn-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-active {
            background: #dcfce7;
            color: #166534;
        }
        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        .worker-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.25rem;
        }
        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
        }
        body.darkness .worker-settings {
            background: #1f2937;
        }
        body.darkness .settings-section {
            background: #374151;
            border-color: #4b5563;
        }
        body.darkness .settings-section h3 {
            color: #f9fafb;
        }
        body.darkness .form-group label {
            color: #f9fafb;
        }
        body.darkness .form-control {
            background: #4b5563;
            border-color: #6b7280;
            color: #f9fafb;
        }
        body.darkness .stat-item {
            background: #4b5563;
        }
        body.darkness .stat-value {
            color: #f9fafb;
        }
        body.darkness .stat-label {
            color: #d1d5db;
        }
        .form-text {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        body.darkness .form-text {
            color: #d1d5db;
        }
        .settings-section h4 {
            margin: 1rem 0 0.75rem 0;
            color: #374151;
            font-size: 1rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        body.darkness .settings-section h4 {
            color: #f9fafb;
        }
        .schedule-config {
            padding: 1rem;
            background: #f9fafb;
            border-radius: 6px;
            margin-top: 0.75rem;
        }
        body.darkness .schedule-config {
            background: #4b5563;
        }
    </style>

    <div class="max-w-11xl mx-auto py-2 px-5">
        <div class="worker-header">
            <div class="worker-info">
                <div class="worker-icon"><i class="fas fa-cog"></i></div>
                <div class="worker-details">
                    <h1>
                        @php
                            $workerInstance = class_exists($worker->class) ? new $worker->class() : null;
                        @endphp
                        {{$workerInstance && method_exists($workerInstance, 'title') ? $workerInstance->title() : ucfirst(str_replace('_', ' ', $worker->identifier))}}
                    </h1>
                    <div class="worker-identifier">{{$worker->identifier}}</div>
                </div>
                <div style="margin-left: auto;">
                <span class="status-badge {{$worker->active ? 'status-active' : 'status-inactive'}}">
                    <i data-lucide="{{$worker->active ? 'check' : 'x'}}" class="w-3 h-3"></i>
                    {{$worker->active ? __('sTask::global.active') : __('sTask::global.inactive')}}
                </span>
                </div>
            </div>
        </div>

        <div class="settings-section">
            <h3>@lang('sTask::global.worker_actions')</h3>
            <div class="btn-group">
                @if($workerInstance && method_exists($workerInstance, 'taskMake'))
                    <button onclick="runWorker('{{$worker->identifier}}')" class="btn btn-primary">
                        <i data-lucide="play" class="w-4 h-4"></i>
                        @lang('sTask::global.run_task')
                    </button>
                @endif

                @if($worker->active)
                    <button onclick="toggleWorker('{{$worker->identifier}}', false)" class="btn btn-danger">
                        <i data-lucide="pause" class="w-4 h-4"></i>
                        @lang('sTask::global.deactivate')
                    </button>
                @else
                    <button onclick="toggleWorker('{{$worker->identifier}}', true)" class="btn btn-success">
                        <i data-lucide="play-circle" class="w-4 h-4"></i>
                        @lang('sTask::global.activate')
                    </button>
                @endif

                <a href="{{route('sTask.workers')}}" class="btn btn-secondary">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    @lang('sTask::global.back_to_workers')
                </a>
            </div>
        </div>

        @if($workerInstance)
            @php
                $schedule = method_exists($workerInstance, 'getSchedule') ? $workerInstance->getSchedule() : ['type' => 'manual', 'enabled' => false];
            @endphp

            <div class="settings-section">
                <h3><i data-lucide="settings" class="w-5 h-5"></i> @lang('sTask::global.worker_settings')</h3>

                <form id="workerConfigForm" method="POST" action="{{route('sTask.worker.settings.save', ['identifier' => $worker->identifier])}}" onsubmit="saveWorkerConfig(event, '{{$worker->identifier}}')">
                    @csrf
                    <!-- Schedule Configuration (only for automated workers with taskMake) -->
                    @if($workerInstance && method_exists($workerInstance, 'taskMake'))
                        <h4><i data-lucide="clock" class="w-4 h-4"></i> @lang('sTask::global.schedule_launch')</h4>

                        <div class="form-group">
                            <label>
                                <input type="checkbox"
                                       name="schedule[enabled]"
                                       id="scheduleEnabled"
                                       value="1"
                                       {{$schedule['enabled'] ?? false ? 'checked' : ''}}
                                       onchange="toggleScheduleOptions()">
                                @lang('sTask::global.enable_auto_run')
                            </label>
                        </div>

                        <div id="scheduleOptions" class="{{$schedule['enabled'] ?? false ? '' : 'hidden'}}">
                            <!-- Schedule Type -->
                            <div class="form-group">
                                <label>@lang('sTask::global.schedule_type')</label>
                                <select class="form-control" name="schedule[type]" id="scheduleType" onchange="toggleScheduleConfig()">
                                    <option value="manual" {{($schedule['type'] ?? 'manual') == 'manual' ? 'selected' : ''}}>@lang('sTask::global.schedule_manual')</option>
                                    <option value="once" {{($schedule['type'] ?? 'manual') == 'once' ? 'selected' : ''}}>@lang('sTask::global.schedule_once')</option>
                                    <option value="periodic" {{($schedule['type'] ?? 'manual') == 'periodic' ? 'selected' : ''}}>@lang('sTask::global.schedule_periodic')</option>
                                    <option value="regular" {{($schedule['type'] ?? 'manual') == 'regular' ? 'selected' : ''}}>@lang('sTask::global.schedule_regular')</option>
                                </select>
                            </div>

                            <!-- Once: specific datetime -->
                            <div class="schedule-config schedule-once {{($schedule['type'] ?? 'manual') == 'once' ? '' : 'hidden'}}">
                                <div class="form-group">
                                    <label>@lang('sTask::global.datetime_launch')</label>
                                    <input type="datetime-local"
                                           class="form-control"
                                           name="schedule[datetime]"
                                           value="{{!empty($schedule['datetime']) ? date('Y-m-d\TH:i', strtotime($schedule['datetime'])) : ''}}">
                                </div>
                            </div>

                            <!-- Periodic: specific time + frequency -->
                            <div class="schedule-config schedule-periodic {{($schedule['type'] ?? 'manual') == 'periodic' ? '' : 'hidden'}}">
                                <!-- Frequency first -->
                                <div class="form-group">
                                    <label>@lang('sTask::global.frequency')</label>
                                    <select class="form-control" name="schedule[frequency]" id="scheduleFrequency" onchange="updateTimeFormat()">
                                        <option value="hourly" {{($schedule['frequency'] ?? 'hourly') == 'hourly' ? 'selected' : ''}}>@lang('sTask::global.frequency_hourly')</option>
                                        <option value="daily" {{($schedule['frequency'] ?? 'hourly') == 'daily' ? 'selected' : ''}}>@lang('sTask::global.frequency_daily')</option>
                                        <option value="weekly" {{($schedule['frequency'] ?? 'hourly') == 'weekly' ? 'selected' : ''}}>@lang('sTask::global.frequency_weekly')</option>
                                    </select>
                                </div>

                                <!-- Time second (format depends on frequency) -->
                                <div class="form-group">
                                    <label id="timeLaunchLabel">@lang('sTask::global.time_launch')</label>
                                    <div id="timeInputContainer">
                                        @php
                                            $frequency = $schedule['frequency'] ?? 'hourly';
                                            $time = $schedule['time'] ?? '14:00';
                                            $timeParts = explode(':', $time);
                                            $minutes = $timeParts[1] ?? '00';
                                        @endphp

                                                <!-- For hourly: only minutes -->
                                        <div id="hourlyTimeInput" class="{{$frequency == 'hourly' ? '' : 'hidden'}}">
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <span style="font-size: 1.5rem; font-weight: bold;">*:</span>
                                                <input type="number"
                                                       class="form-control"
                                                       id="minutesInput"
                                                       name="schedule[minutes]"
                                                       min="0"
                                                       max="59"
                                                       style="width: 80px;"
                                                       value="{{$minutes}}"
                                                       placeholder="35">
                                                <small class="form-text">Запуск кожної години о заданій хвилині (наприклад: 01:35, 02:35, 03:35...)</small>
                                            </div>
                                        </div>

                                        <!-- For daily and weekly: full time -->
                                        <input type="time"
                                               class="form-control {{$frequency == 'hourly' ? 'hidden' : ''}}"
                                               id="fullTimeInput"
                                               name="schedule[time]"
                                               value="{{$time}}">
                                    </div>
                                </div>

                                <!-- Weekly: select days -->
                                <div class="form-group {{($schedule['frequency'] ?? 'hourly') == 'weekly' ? '' : 'hidden'}}" id="weeklyDaysGroup">
                                    <label>Дні тижня</label>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        @php
                                            $selectedDays = $schedule['days'] ?? [];
                                            if (is_string($selectedDays)) {
                                                $selectedDays = json_decode($selectedDays, true) ?: [];
                                            }
                                            $days = [
                                                'monday' => 'Понеділок',
                                                'tuesday' => 'Вівторок',
                                                'wednesday' => 'Середа',
                                                'thursday' => 'Четвер',
                                                'friday' => 'П\'ятниця',
                                                'saturday' => 'Субота',
                                                'sunday' => 'Неділя'
                                            ];
                                        @endphp
                                        @foreach($days as $value => $label)
                                            <label style="font-weight: normal;">
                                                <input type="checkbox"
                                                       name="schedule[days][]"
                                                       value="{{$value}}"
                                                        {{in_array($value, $selectedDays) ? 'checked' : ''}}>
                                                {{$label}}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <!-- Regular: time range + interval -->
                            <div class="schedule-config schedule-regular {{($schedule['type'] ?? 'manual') == 'regular' ? '' : 'hidden'}}">
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="form-group">
                                        <label>@lang('sTask::global.start_time')</label>
                                        <input type="time"
                                               class="form-control"
                                               name="schedule[start_time]"
                                               value="{{$schedule['start_time'] ?? '05:00'}}">
                                    </div>
                                    <div class="form-group">
                                        <label>@lang('sTask::global.end_time')</label>
                                        <input type="time"
                                               class="form-control"
                                               name="schedule[end_time]"
                                               value="{{$schedule['end_time'] ?? '23:00'}}">
                                    </div>
                                    <div class="form-group">
                                        <label>@lang('sTask::global.interval')</label>
                                        <select class="form-control" name="schedule[interval]">
                                            <option value="every_15min" {{($schedule['interval'] ?? 'hourly') == 'every_15min' ? 'selected' : ''}}>@lang('sTask::global.interval_15min')</option>
                                            <option value="every_30min" {{($schedule['interval'] ?? 'hourly') == 'every_30min' ? 'selected' : ''}}>@lang('sTask::global.interval_30min')</option>
                                            <option value="hourly" {{($schedule['interval'] ?? 'hourly') == 'hourly' ? 'selected' : ''}}>@lang('sTask::global.interval_hourly')</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                    @endif

                    <!-- Custom Worker Settings (if worker provides them) -->
                    @if($workerInstance && method_exists($workerInstance, 'renderSettings'))
                        {!! $workerInstance->renderSettings() !!}
                    @endif

                    <!-- Generated Files (if worker provides them) -->
                    @if($workerInstance && method_exists($workerInstance, 'getGeneratedFiles'))
                        @php
                            $generatedFiles = $workerInstance->getGeneratedFiles();
                        @endphp
                        @if(!empty($generatedFiles))
                            <hr><br>
                            <div class="settings-section">
                                <h3>
                                    <i data-lucide="file-text" class="w-4 h-4"></i>
                                    @lang('sTask::global.generated_files') ({{count($generatedFiles)}})
                                </h3>
                                <div style="margin-top: 1rem;">
                                    <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.75rem;">
                                        Останні згенеровані файли фідів:
                                    </p>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        @foreach($generatedFiles as $file)
                                            <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; background: #f9fafb; border-radius: 4px;">
                                                <i data-lucide="file" class="w-4 h-4" style="color: #6b7280;"></i>
                                                <span style="flex: 1; font-family: 'Courier New', monospace; font-size: 0.875rem;">{{$file['filename']}}</span>
                                                @if(!empty($file['url']))
                                                    <a href="{{$file['url']}}" target="_blank" rel="noopener noreferrer"
                                                       style="color: #3b82f6; text-decoration: none; font-size: 0.875rem; display: flex; align-items: center; gap: 0.25rem;">
                                                        <i data-lucide="external-link" class="w-3 h-3"></i>
                                                        Відкрити
                                                    </a>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif

                    <!-- Basic Info (readonly) -->
                    <hr><br>
                    <div class="form-group">
                        <label>@lang('sTask::global.worker_class')</label>
                        <input type="text" class="form-control" value="{{$worker->class}}" readonly>
                    </div>
                    <div class="form-group">
                        <label>@lang('sTask::global.worker_description')</label>
                        <textarea class="form-control" rows="3" readonly>@php
                                if (method_exists($workerInstance, 'description')) {
                                    echo $workerInstance->description();
                                } else {
                                    echo __('sTask::global.worker_description');
                                }
                            @endphp</textarea>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i data-lucide="save" class="w-4 h-4"></i> @lang('sTask::global.save_settings')
                    </button>
                </form>
            </div>
        @endif

        <div class="settings-section">
            <h3>@lang('sTask::global.worker_statistics')</h3>
            <div class="stat-item" style="max-width: 200px;">
                <div class="stat-value">{{$worker->tasks()->count()}}</div>
                <div class="stat-label">@lang('sTask::global.tasks_count')</div>
            </div>
        </div>
    </div>

    <script>
        function toggleScheduleOptions() {
            const enabled = document.getElementById('scheduleEnabled').checked;
            const options = document.getElementById('scheduleOptions');
            if (options) {
                if (enabled) {
                    options.classList.remove('hidden');
                } else {
                    options.classList.add('hidden');
                }
            }
        }

        function toggleScheduleConfig() {
            const type = document.getElementById('scheduleType').value;
            const configs = document.querySelectorAll('.schedule-config');
            configs.forEach(el => el.classList.add('hidden'));

            const selected = document.querySelector('.schedule-' + type);
            if (selected) {
                selected.classList.remove('hidden');
            }
        }

        function updateTimeFormat() {
            const frequency = document.getElementById('scheduleFrequency')?.value;
            const hourlyInput = document.getElementById('hourlyTimeInput');
            const fullTimeInput = document.getElementById('fullTimeInput');
            const weeklyDaysGroup = document.getElementById('weeklyDaysGroup');

            if (!frequency) return;

            // Show/hide time inputs based on frequency
            if (frequency === 'hourly') {
                hourlyInput?.classList.remove('hidden');
                fullTimeInput?.classList.add('hidden');
            } else {
                hourlyInput?.classList.add('hidden');
                fullTimeInput?.classList.remove('hidden');
            }

            // Show/hide weekly days selector
            if (frequency === 'weekly') {
                weeklyDaysGroup?.classList.remove('hidden');
            } else {
                weeklyDaysGroup?.classList.add('hidden');
            }
        }

        /**
         * Universal serialization function placeholder.
         * Workers should implement their own serializeWorkerSettings() function
         * in their renderSettings() method to handle custom data structures.
         */
        function serializeArrayFields(form) {
            // This is a placeholder - workers should implement serializeWorkerSettings() for custom serialization
            // This function is kept for backward compatibility but does nothing by default
            return new Set();
        }

        function saveWorkerConfig(event, identifier) {
            event.preventDefault();

            const form = event.target;

            // Call custom serialize function if it exists (for worker-specific serialization)
            // Each worker should implement serializeWorkerSettings() to handle its own data structure
            try {
                if (window.serializeWorkerSettings) {
                    window.serializeWorkerSettings(form);
                }
            } catch (e) {
                console.error('Error serializing worker settings:', e);
            }

            // Submit the form normally (POST request)
            form.submit();
        }

        function toggleWorker(identifier, activate) {
            const action = activate ? 'activate' : 'deactivate';
            const route = activate ? '{{route('sTask.worker.activate')}}' : '{{route('sTask.worker.deactivate')}}';

            fetch(route, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{csrf_token()}}'
                },
                body: JSON.stringify({ identifier: identifier })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alertify.success(data.message);
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        alertify.error(data.message || '@lang('sTask::global.error')');
                    }
                })
                .catch(error => {
                    alertify.error('@lang('sTask::global.error')');
                    console.error(error);
                });
        }

        function runWorker(identifier) {
            // Create a new task for the worker using the new action controller
            const baseUrl = '{{route('sTask.worker.task.run', ['identifier' => '__IDENTIFIER__', 'action' => 'make'])}}';
            const url = baseUrl.replace('__IDENTIFIER__', identifier);

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{csrf_token()}}'
                },
                body: JSON.stringify({})
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alertify.success(data.message || '@lang('sTask::global.task_created')');
                        setTimeout(() => {
                            window.location.href = '{{route('sTask.index')}}';
                        }, 1000);
                    } else {
                        alertify.error(data.message || '@lang('sTask::global.error')');
                    }
                })
                .catch(error => {
                    alertify.error('@lang('sTask::global.error')');
                    console.error(error);
                });
        }
    </script>
    @include('sTask::scripts.task')
    @include('sTask::scripts.global')
@endsection

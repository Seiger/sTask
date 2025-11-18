@php
    $task = Seiger\sTask\Models\sTaskModel::byIdentifier($identifier ?? '')->incomplete()->orderByDesc('updated_at')->first();
    $workerInstance = app(Seiger\sTask\Services\WorkerService::class)->resolveWorker($identifier ?? '');
    $schedule = method_exists($workerInstance, 'getSchedule') ? $workerInstance->getSchedule() : ['type' => 'manual', 'enabled' => false];
    $hasEndpointSetting = method_exists($workerInstance, 'getConfig') && array_key_exists('endpoint', $workerInstance->settings() ?? []);
    $endpoint = $hasEndpointSetting ? $workerInstance->getConfig('endpoint', '') : null;
@endphp

<div id="{{$identifier ?? ''}}Widget">
    <div style="padding: 0.875rem 1rem; display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
        @if(!is_null($endpoint) && trim($endpoint) !== '')
            @php
                $parsedEndpoint = parse_url($endpoint);
                $displayEndpoint = $parsedEndpoint['path'] ?? '';
                if(isset($parsedEndpoint['query']) && trim($parsedEndpoint['query']) !== '') {
                    $displayEndpoint .= '?' . $parsedEndpoint['query'];
                }
                $displayEndpoint = trim($displayEndpoint) !== '' ? $displayEndpoint : ($parsedEndpoint['host'] ?? $endpoint);
            @endphp
            <span class="badge badge-success" title="{{$endpoint}}">
                <i class="fas fa-link"></i> {{$displayEndpoint}}
            </span>
        @elseif($hasEndpointSetting)
            <span class="badge badge-danger">
                <i class="fas fa-exclamation-triangle"></i> Endpoint не налаштовано
            </span>
        @endif

        @if($schedule['enabled'])
            @if($schedule['type'] == 'once')
                <span class="badge badge-info">
                    <i class="fas fa-calendar-check"></i> Один раз: {{$schedule['datetime']}}
                </span>
            @elseif($schedule['type'] == 'periodic')
                @php
                    $frequency = $schedule['frequency'] ?? 'hourly';
                    $time = $schedule['time'] ?? '00:00';
                    $frequencyLabels = [
                        'hourly' => 'Щогодини',
                        'daily' => 'Щодня',
                        'weekly' => 'Щотижня'
                    ];
                    $frequencyLabel = $frequencyLabels[$frequency] ?? ucfirst($frequency);

                    // Format time display
                    $timeDisplay = $time;

                    // Add days for weekly
                    if ($frequency === 'weekly' && !empty($schedule['days'])) {
                        $dayLabels = [
                            'monday' => 'Пн',
                            'tuesday' => 'Вт',
                            'wednesday' => 'Ср',
                            'thursday' => 'Чт',
                            'friday' => 'Пт',
                            'saturday' => 'Сб',
                            'sunday' => 'Нд'
                        ];
                        $selectedDays = [];
                        foreach ($schedule['days'] as $day) {
                            $selectedDays[] = $dayLabels[$day] ?? $day;
                        }
                        $frequencyLabel .= ' (' . implode(', ', $selectedDays) . ')';
                    }
                @endphp
                <span class="badge badge-info">
                    <i class="fas fa-clock"></i> {{$frequencyLabel}} о {{$timeDisplay}}
                </span>
            @elseif($schedule['type'] == 'regular')
                <span class="badge badge-info">
                    <i class="fas fa-redo"></i> {{ucfirst($schedule['interval'] ?? 'hourly')}}: {{$schedule['start_time']}} - {{$schedule['end_time']}}
                </span>
            @endif
        @else
            <span class="badge badge-secondary">
                <i class="fas fa-hand-paper"></i> Тільки вручну
            </span>
        @endif
    </div>
</div>

<div id="{{$identifier ?? ''}}Progress" class="widget-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
    <span class="widget-progress__bar"></span>
    <span class="widget-progress__cap"></span>
    <span class="widget-progress__meta">
        <b class="widget-progress__pct">0%</b>
        <i class="widget-progress__eta">—</i>
    </span>
</div>

<div id="{{$identifier ?? ''}}Log" class="widget-log"></div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if($task && $task->status == \Seiger\sTask\Models\sTaskModel::TASK_STATUS_RUNNING)
            widgetLogLine(document.getElementById('{{$identifier ?? ''}}Log'), '_Завдання виконується..._');
            widgetWatcher(document.getElementById('{{$identifier ?? ''}}Log'), "{{route('sTask.task.progress', ['id' => $task->id])}}", '{{$identifier ?? ''}}');
        @endif

        // Handle manual sync button
        document.getElementById('{{$identifier ?? ''}}Run')?.addEventListener('click', async function() {
            let root = document.getElementById('{{$identifier ?? ''}}Log');

            widgetClearLog(root);
            widgetLogLine(root, '***Запуск синхронізації...*** _Зачекайте будь ласка_');

            // Disable button immediately when starting
            disableButtons('{{$identifier ?? ''}}', null, '{{$identifier ?? ''}}Run');

            let result = await callApi("{{route('sTask.worker.task.run', ['identifier' => $identifier ?? '', 'action' => 'make'])}}");

            if (result.success == true) {
                // Show progress bar immediately
                widgetProgressBar('{{$identifier ?? ''}}', 0);
                // widgetWatcher will show progress automatically
                widgetWatcher(root, "{{route('sTask.task.progress', ['id' => '__ID__'])}}".replace('__ID__', result?.id||0), '{{$identifier ?? ''}}');
            } else {
                widgetLogLine(root, '**Помилка запуску синхронізації.** _' + (result?.message || '') + '_', 'error');
                enableButtons('{{$identifier ?? ''}}');
            }
        });
    });
</script>

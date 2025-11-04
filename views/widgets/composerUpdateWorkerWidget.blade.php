@php $task = Seiger\sTask\Models\sTaskModel::byIdentifier($identifier ?? '')->incomplete()->orderByDesc('updated_at')->first(); @endphp

<div id="{{$identifier ?? ''}}Widget">
    <div style="padding: 0.875rem 1rem;">
        <span id="{{$identifier ?? ''}}Run" class="btn btn-primary">
            <i class="fas fa-play" style="font-size: 0.75rem;"></i>
            {{__('sTask::global.run_task')}}
        </span>
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

<div id="{{$identifier ?? ''}}Log" class="widget-log" aria-live="polite">
    <div class="line-info">{{$description ?? ''}}</div>
    @if($task && (int)$task->id > 0)
        <div class="line-info">{{__('sTask::global.task_is_running')}}...</div>
    @else
        <div class="line-info">{{__('sTask::global.click_button_to_start')}}</div>
    @endif
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if($task && (int)$task->id > 0)
        let root = document.getElementById('{{$identifier ?? ''}}Log');
        widgetClearLog(root);
        widgetLogLine(root, '_{{__('sTask::global.task_is_running')}}..._');
        widgetWatcher(root, "{{route('sTask.task.progress', ['id' => $task->id])}}", '{{$identifier ?? ''}}');
        @endif

        document.getElementById('{{$identifier ?? ''}}Run')?.addEventListener('click', async function() {
            let root = document.getElementById('{{$identifier ?? ''}}Log');

            widgetClearLog(root);
            widgetLogLine(root, '**{{__('sTask::global.starting_task')}}...** _{{__('sTask::global.please_wait')}}_');

            // Disable button immediately when starting, with this button as active
            disableButtons('{{$identifier ?? ''}}', null, '{{$identifier ?? ''}}Run');

            let result = await callApi("{{route('sTask.worker.task.run', ['identifier' => $identifier ?? '', 'action' => 'make'])}}");

            if (result.success == true) {
                // Show progress bar immediately
                widgetProgressBar('{{$identifier ?? ''}}', 0);
                // widgetWatcher will show progress automatically
                widgetWatcher(root, "{{route('sTask.task.progress', ['id' => '__ID__'])}}".replace('__ID__', result?.id||0), '{{$identifier ?? ''}}');
            } else {
                widgetLogLine(root, '**{{__('sTask::global.error_starting_task')}}.** _' + (result?.message || '') + '_', 'error');
                enableButtons('{{$identifier ?? ''}}');
            }
        });
    });
</script>

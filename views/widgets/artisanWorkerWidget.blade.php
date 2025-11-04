@php $task = Seiger\sTask\Models\sTaskModel::byIdentifier($identifier ?? '')->incomplete()->orderByDesc('updated_at')->first(); @endphp

<div id="{{$identifier ?? ''}}Widget">
    <div style="padding: 0.875rem 1rem;">
        <div class="form-group" style="margin-bottom: 1rem;">
            <label for="{{$identifier ?? ''}}Command" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                {{__('sTask::global.artisan_command')}} ({{__('sTask::global.optional')}}):
            </label>
            <input
                    type="text"
                    id="{{$identifier ?? ''}}Command"
                    class="form-control"
                    placeholder="list"
                    value=""
                    style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;"
            >
            <small style="display: block; margin-top: 0.25rem; color: #666;">
                {{__('sTask::global.artisan_command_hint_empty')}}
            </small>
        </div>

        <div class="form-group" style="margin-bottom: 1rem;">
            <label for="{{$identifier ?? ''}}Arguments" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                {{__('sTask::global.artisan_arguments')}} ({{__('sTask::global.optional')}}):
            </label>
            <input
                    type="text"
                    id="{{$identifier ?? ''}}Arguments"
                    class="form-control"
                    placeholder="--force --no-interaction"
                    style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;"
            >
            <small style="display: block; margin-top: 0.25rem; color: #666;">
                {{__('sTask::global.artisan_arguments_hint')}}
            </small>
        </div>

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

<style>
    #{{$identifier ?? ''}}Log {
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', 'source-code-pro', monospace;
        background: #1e1e1e;
        color: #d4d4d4;
        padding: 1rem;
        border-radius: 4px;
        font-size: 13px;
        line-height: 1.6;
    }

    #{{$identifier ?? ''}}Log .artisan-command {
        color: #4ec9b0;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s;
        display: inline-block;
        padding: 2px 6px;
        border-radius: 3px;
        margin: 2px 0;
    }

    #{{$identifier ?? ''}}Log .artisan-command:hover {
        background: rgba(78, 201, 176, 0.2);
        color: #6dd5bc;
    }

    #{{$identifier ?? ''}}Log .artisan-group {
        color: #dcdcaa;
        font-weight: bold;
        margin-top: 0.5rem;
    }

    #{{$identifier ?? ''}}Log .artisan-description {
        color: #858585;
        font-style: italic;
        margin-left: 1rem;
    }

    #{{$identifier ?? ''}}Log .line-info {
        color: #569cd6;
    }

    #{{$identifier ?? ''}}Log .line-error {
        color: #f48771;
    }

    #{{$identifier ?? ''}}Log .line-success {
        color: #4ec9b0;
    }

    #{{$identifier ?? ''}}Log .line-warning {
        color: #dcdcaa;
    }
</style>

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
        // Function to make artisan commands clickable
        function makeCommandsClickable() {
            let root = document.getElementById('{{$identifier ?? ''}}Log');
            if (!root) return;

            let lines = root.querySelectorAll('div');
            lines.forEach(line => {
                let text = line.textContent || '';

                // Match artisan command patterns (word:word or just word before description)
                let commandMatch = text.match(/^([a-z]+(?::[a-z-]+)?)\s+(.+)$/);

                if (commandMatch) {
                    let command = commandMatch[1];
                    let description = commandMatch[2];

                    line.innerHTML = '<span class="artisan-command" data-command="' + command + '">' +
                        command +
                        '</span> <span class="artisan-description">' + description + '</span>';
                }

                // Match group headers
                if (text.match(/^[a-z]+$/)) {
                    line.classList.add('artisan-group');
                }
            });

            // Add click handlers to commands
            root.querySelectorAll('.artisan-command').forEach(cmd => {
                cmd.addEventListener('click', function(e) {
                    e.preventDefault();
                    let command = this.getAttribute('data-command');
                    let commandInput = document.getElementById('{{$identifier ?? ''}}Command');
                    commandInput.value = command;

                    // Small delay to ensure value is set
                    setTimeout(() => {
                        document.getElementById('{{$identifier ?? ''}}Run').click();
                    }, 100);
                });
            });
        }

        // Watch for new content and make commands clickable
        let observerRoot = document.getElementById('{{$identifier ?? ''}}Log');
        if (observerRoot) {
            let observer = new MutationObserver(function(mutations) {
                makeCommandsClickable();
            });
            observer.observe(observerRoot, { childList: true, subtree: true });
        }

        @if($task && (int)$task->id > 0)
        let root = document.getElementById('{{$identifier ?? ''}}Log');
        widgetClearLog(root);
        widgetLogLine(root, '_{{__('sTask::global.task_is_running')}}..._');
        widgetWatcher(root, "{{route('sTask.task.progress', ['id' => $task->id])}}", '{{$identifier ?? ''}}');
        @endif

        document.getElementById('{{$identifier ?? ''}}Run')?.addEventListener('click', async function() {
            let root = document.getElementById('{{$identifier ?? ''}}Log');
            let commandInput = document.getElementById('{{$identifier ?? ''}}Command');
            let argumentsInput = document.getElementById('{{$identifier ?? ''}}Arguments');

            // Get command value (can be empty to show list of commands)
            let command = commandInput?.value?.trim() || '';

            // Get arguments value
            let args = argumentsInput?.value?.trim() || '';

            widgetClearLog(root);
            widgetLogLine(root, '**{{__('sTask::global.starting_task')}}...** _{{__('sTask::global.please_wait')}}_');

            let commandDisplay = command || '{{__('sTask::global.list_commands')}}';
            widgetLogLine(root, '_Command: artisan ' + commandDisplay + (args ? ' ' + args : '') + '_');

            // Disable button immediately when starting, with this button as active
            disableButtons('{{$identifier ?? ''}}', null, '{{$identifier ?? ''}}Run');

            // Prepare options with command and arguments (command can be empty)
            let options = {};

            if (command) {
                options.command = command;
            }

            if (args) {
                options.arguments = args;
            }

            let result = await callApi("{{route('sTask.worker.task.run', ['identifier' => $identifier ?? '', 'action' => 'run'])}}", {
                options: options
            });

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

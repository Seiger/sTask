@php
    $descriptor = $descriptor ?? [];
    $title = (string)($descriptor['title'] ?? $title ?? $identifier ?? '');
    $description = (string)($descriptor['description'] ?? $description ?? '');
@endphp

<x-evo::card :label="$title" icon="player-play">
    <div
        class="evo-ui-task-runner"
        data-stask-task-runner='@json($descriptor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)'
    >
        @if($description !== '')
            <p class="evo-ui-card__label">{{ $description }}</p>
        @endif

        <div class="evo-ui-modal__grid">
            <div class="evo-ui-static-field">
                <strong>@lang('sTask::global.identifier')</strong>
                <span>{{ $descriptor['identifier'] ?? $identifier ?? '' }}</span>
            </div>
            <div class="evo-ui-static-field">
                <strong>@lang('sTask::global.action')</strong>
                <span>{{ $descriptor['action'] ?? 'make' }}</span>
            </div>
        </div>

        @if(!empty($descriptor['fields']) || !empty($descriptor['options']))
            <div class="evo-ui-modal__grid">
                @foreach(($descriptor['fields'] ?? $descriptor['options'] ?? []) as $field)
                    <div class="evo-ui-static-field">
                        <strong>{{ $field['label'] ?? $field['name'] ?? '' }}</strong>
                        <span>{{ $field['name'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        @if(!empty($descriptor['command_list']))
            <p class="evo-ui-card__label">@lang('sTask::global.artisan_command_list_hint')</p>
        @endif
    </div>
</x-evo::card>

@php
    $files = collect((array) ($field['files'] ?? []))
        ->filter(fn ($file) => is_array($file) && !empty($file['filename']))
        ->values();
    $title = (string) ($field['title'] ?? __('sTask::global.generated_files'));
    $openLabel = (string) ($field['open_label'] ?? __('sTask::global.open_file'));
@endphp

<div id="{{ $fieldId }}" class="stask-worker-files">
    <div class="stask-worker-files__summary">
        <x-evo::icon name="files" />
        <span>{{ $title }} ({{ $files->count() }})</span>
    </div>
    <div class="stask-worker-files__list">
        @foreach($files as $file)
            <div class="stask-worker-files__item">
                <span class="stask-worker-files__name">
                    <x-evo::icon name="file" />
                    <code>{{ (string) $file['filename'] }}</code>
                </span>
                @if(!empty($file['url']))
                    <a href="{{ (string) $file['url'] }}" target="_blank" rel="noopener noreferrer">
                        <x-evo::icon name="external-link" />
                        <span>{{ $openLabel }}</span>
                    </a>
                @endif
            </div>
        @endforeach
    </div>
</div>

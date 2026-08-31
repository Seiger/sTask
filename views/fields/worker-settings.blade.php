<div
    id="{{ $fieldId }}"
    class="stask-worker-settings"
    data-stask-worker-settings
    data-stask-model="{{ $model }}"
    data-stask-serializer="{{ (string) ($field['serializer'] ?? 'serializeWorkerSettings') }}"
    wire:ignore
    wire:key="worker-settings-{{ $controller->preset }}-{{ $controller->modalRecordId ?? 'new' }}"
    x-init="$nextTick(() => window.sTask?.initWorkerSettings($el, $wire))"
>
    {!! (string) ($field['html'] ?? '') !!}
</div>

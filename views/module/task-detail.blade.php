@php
    $manager = app(\EvoUI\Support\ManagerContext::class);
    $theme = $manager->theme();
    $themeMode = $manager->themeMode($theme);
    $themeClasses = $manager->themeClasses($theme);
    $themeBackground = $manager->themeBackground($theme);
    $moduleTitle = __('sTask::global.task') . ' #' . $task->id;
    $status = \Seiger\sTask\Models\sTaskModel::statusText((int)$task->status);
    $statusColor = match ((int)$task->status) {
        \Seiger\sTask\Models\sTaskModel::TASK_STATUS_RUNNING => '#2563EB',
        \Seiger\sTask\Models\sTaskModel::TASK_STATUS_FINISHED => '#16A34A',
        \Seiger\sTask\Models\sTaskModel::TASK_STATUS_FAILED => '#DC2626',
        \Seiger\sTask\Models\sTaskModel::TASK_STATUS_PREPARING => '#D97706',
        default => '#64748B',
    };
@endphp
<!doctype html>
<html
    class="evo-ui-page {{ $themeClasses }}"
    lang="{{ str_replace('_', '-', app()->getLocale() ?: ManagerTheme::getLang()) }}"
    data-theme="{{ $theme }}"
    data-theme-mode="{{ $themeMode }}"
    style="background-color: var(--evo-ui-bg, {{ $themeBackground }})"
>
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="{{ $themeMode === 'dark' ? 'dark light' : 'light dark' }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $moduleTitle }}</title>
    @include('evo::partials.assets')
</head>
<body
    class="evo-ui-page {{ $themeClasses }}"
    data-theme="{{ $theme }}"
    data-theme-mode="{{ $themeMode }}"
    style="background-color: var(--evo-ui-bg, {{ $themeBackground }})"
>
<main
    class="evo-ui {{ $themeClasses }}"
    data-evo-ui-root
    data-theme="{{ $theme }}"
    data-theme-mode="{{ $themeMode }}"
>
    <header class="evo-ui-page-header">
        <div>
            <p class="evo-ui-page-header__eyebrow">sTask</p>
            <h1 class="evo-ui-page-header__title">@lang('sTask::global.task') #{{ $task->id }}</h1>
        </div>
        <x-evo::button
            :href="route('sTask.index', ['get' => 'tasks'])"
            icon="arrow-left"
            :label="__('sTask::global.tasks')"
        />
    </header>

    <section class="evo-ui-dashboard-grid">
        <x-evo::card :label="__('sTask::global.status')" icon="activity">
            <x-evo::badge :label="__('sTask::global.' . $status)" :color="$statusColor" />
        </x-evo::card>
        <x-evo::card :label="__('sTask::global.progress')" icon="gauge">
            <p class="evo-ui-card__value">{{ max(0, min(100, (int)$task->progress)) }}%</p>
        </x-evo::card>
        <x-evo::card :label="__('sTask::global.worker')" icon="cpu">
            <p class="evo-ui-card__value">{{ $task->worker->title ?? $task->identifier }}</p>
            <p class="evo-ui-card__label">{{ $task->identifier }}</p>
        </x-evo::card>
        <x-evo::card :label="__('sTask::global.action')" icon="play">
            <p class="evo-ui-card__value">{{ $task->action }}</p>
        </x-evo::card>
    </section>

    <section class="evo-ui-table-wrap">
        <table class="evo-ui-table evo-ui-table--module">
            <tbody>
                <tr><th>@lang('sTask::global.created')</th><td>{{ $task->created_at?->format('Y-m-d H:i:s') }}</td></tr>
                <tr><th>@lang('sTask::global.start_at')</th><td>{{ $task->start_at?->format('Y-m-d H:i:s') ?: '-' }}</td></tr>
                <tr><th>@lang('sTask::global.finished_at')</th><td>{{ $task->finished_at?->format('Y-m-d H:i:s') ?: '-' }}</td></tr>
                <tr><th>@lang('sTask::global.updated')</th><td>{{ $task->updated_at?->format('Y-m-d H:i:s') ?: '-' }}</td></tr>
                <tr><th>@lang('sTask::global.started_by')</th><td>{{ $task->user->username ?? 'system' }}</td></tr>
                <tr><th>@lang('sTask::global.priority')</th><td>{{ __('sTask::global.priority_' . ($task->priority ?: 'normal')) }}</td></tr>
                <tr><th>@lang('sTask::global.attempts')</th><td>{{ (int)$task->attempts }} / {{ (int)$task->max_attempts }}</td></tr>
                <tr><th>@lang('sTask::global.worker_info')</th><td>{{ $task->worker->class ?? '-' }}</td></tr>
            </tbody>
        </table>
    </section>

    <x-evo::card :label="__('sTask::global.task_log')" icon="file-text">
        <pre class="evo-ui-code-block">{{ trim((string)($task->message ?? '')) !== '' ? $task->message : __('sTask::global.raw_log_empty') }}</pre>
    </x-evo::card>

    <x-evo::card :label="__('sTask::global.meta')" icon="braces">
        <pre class="evo-ui-code-block">{{ $metaPretty ?? __('sTask::global.raw_log_empty') }}</pre>
    </x-evo::card>

    <x-evo::card :label="__('sTask::global.result')" icon="check-circle">
        <pre class="evo-ui-code-block">{{ $resultPretty ?? __('sTask::global.raw_log_empty') }}</pre>
    </x-evo::card>
</main>
</body>
</html>

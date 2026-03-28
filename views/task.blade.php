@extends('sTask::index')
@section('header')
    <a href="{{route('sTask.index')}}" class="s-btn s-btn--secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>@lang('sTask::global.back_to_dashboard')
    </a>
@endsection
@section('content')
    <section class="grid gap-6 p-6 grid-cols-1 xl:grid-cols-3">
        <div class="xl:col-span-1">
            <div class="s-widget">
                <div class="flex items-center gap-2 mb-4">
                    <i data-lucide="info" class="w-5 h-5 text-blue-600 darkness:text-white/80"></i>
                    <h2 class="s-widget-name">@lang('sTask::global.task')</h2>
                </div>
                <div class="space-y-3 text-sm">
                    <div><strong>ID:</strong> #{{$task->id}}</div>
                    <div><strong>@lang('sTask::global.worker'):</strong> {{$task->identifier}}</div>
                    <div><strong>@lang('sTask::global.action'):</strong> {{$task->action}}</div>
                    <div><strong>@lang('sTask::global.status'):</strong> {{\Seiger\sTask\Models\sTaskModel::statusText($task->status)}}</div>
                    <div><strong>@lang('sTask::global.progress'):</strong> {{$task->progress}}%</div>
                    {{--<div><strong>@lang('sTask::global.priority'):</strong> {{$task->priority ?: 'normal'}}</div>--}}
                    {{--<div><strong>@lang('sTask::global.attempts'):</strong> {{$task->attempts}} / {{$task->max_attempts}}</div>--}}
                    <div><strong>@lang('sTask::global.created'):</strong> {{$task->created_at?->format('Y-m-d H:i:s')}}</div>
                    <div><strong>@lang('sTask::global.start_at'):</strong> {{$task->start_at?->format('Y-m-d H:i:s') ?? '—'}}</div>
                    <div><strong>@lang('sTask::global.finished_at'):</strong> {{$task->finished_at?->format('Y-m-d H:i:s') ?? '—'}}</div>
                    <div><strong>@lang('sTask::global.updated'):</strong> {{$task->updated_at?->format('Y-m-d H:i:s') ?? '—'}}</div>
                    <div><strong>@lang('sTask::global.started_by'):</strong> {{$task->user->username ?? 'system'}}</div>
                    <div><strong>@lang('sTask::global.worker_info'):</strong> {{$task->worker->class ?? '—'}}</div>
                </div>
            </div>
        </div>
        <div class="xl:col-span-2 space-y-6">
            <div class="s-widget">
                <div class="flex items-center gap-2 mb-4">
                    <i data-lucide="scroll-text" class="w-5 h-5 text-blue-600 darkness:text-white/80"></i>
                    <h2 class="s-widget-name">@lang('sTask::global.task_log')</h2>
                </div>
                <div class="rounded-xl bg-slate-50 darkness:bg-slate-900/50 border border-slate-200 darkness:border-slate-700 p-4 text-sm whitespace-pre-wrap break-words">{{trim((string)($task->message ?? '')) !== '' ? $task->message : __('sTask::global.raw_log_empty')}}</div>
            </div>
            <div class="s-widget">
                <div class="flex items-center gap-2 mb-4">
                    <i data-lucide="braces" class="w-5 h-5 text-blue-600 darkness:text-white/80"></i>
                    <h2 class="s-widget-name">@lang('sTask::global.meta')</h2>
                </div>
                <pre class="rounded-xl bg-slate-50 darkness:bg-slate-900/50 border border-slate-200 darkness:border-slate-700 p-4 text-xs overflow-x-auto">{{ $metaPretty ?? __('sTask::global.raw_log_empty') }}</pre>
            </div>
            <div class="s-widget">
                <div class="flex items-center gap-2 mb-4">
                    <i data-lucide="file-json" class="w-5 h-5 text-blue-600 darkness:text-white/80"></i>
                    <h2 class="s-widget-name">@lang('sTask::global.result')</h2>
                </div>
                <pre class="rounded-xl bg-slate-50 darkness:bg-slate-900/50 border border-slate-200 darkness:border-slate-700 p-4 text-xs overflow-x-auto">{{ $resultPretty ?? __('sTask::global.raw_log_empty') }}</pre>
            </div>
        </div>
    </section>
@endsection
@extends('sTask::index')
@section('header')
    <button onclick="window.location.reload()" class="s-btn s-btn--primary">
        <i data-lucide="refresh-cw" class="w-4 h-4"></i>@lang('sTask::global.refresh')
    </button>
@endsection
@section('content')
    <section class="grid gap-6 p-6 grid-cols-1 xs:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
        {{-- Pending Tasks --}}
        <div class="s-widget">
            <div class="flex items-center gap-2 mb-3">
                <i data-lucide="clock" class="w-5 h-5 text-blue-600 darkness:text-white/80"></i>
                <h2 class="s-widget-name">@lang('sTask::global.pending_tasks')</h2>
            </div>
            <div class="text-3xl font-semibold text-blue-600 mb-1 darkness:text-white">
                {{number_format($stats['pending'] ?? 0, 0, '.', ' ')}}
            </div>
            <span class="text-xs text-slate-500 darkness:text-white/90">@lang('sTask::global.waiting_execution')</span>
        </div>

        {{-- Running Tasks --}}
        <div class="s-widget">
            <div class="flex items-center gap-2 mb-3">
                <i data-lucide="play-circle" class="w-5 h-5 text-emerald-600 darkness:text-white/80"></i>
                <h2 class="s-widget-name">@lang('sTask::global.running_tasks')</h2>
            </div>
            <div class="text-3xl font-semibold text-emerald-600 mb-1 darkness:text-white">
                {{number_format($stats['running'] ?? 0, 0, '.', ' ')}}
            </div>
            <span class="text-xs text-slate-500 darkness:text-white/90">@lang('sTask::global.in_progress')</span>
        </div>

        {{-- Completed Tasks --}}
        <div class="s-widget">
            <div class="flex items-center gap-2 mb-3">
                <i data-lucide="check-circle" class="w-5 h-5 text-green-600 darkness:text-white/80"></i>
                <h2 class="s-widget-name">@lang('sTask::global.completed_tasks')</h2>
            </div>
            <div class="text-3xl font-semibold text-green-600 mb-1 darkness:text-white">
                {{number_format($stats['completed'] ?? 0, 0, '.', ' ')}}
            </div>
            <span class="text-xs text-slate-500 darkness:text-white/90">@lang('sTask::global.successfully_finished')</span>
        </div>

        {{-- Failed Tasks --}}
        <div class="s-widget">
            <div class="flex items-center gap-2 mb-3">
                <i data-lucide="x-circle" class="w-5 h-5 text-red-600 darkness:text-white/80"></i>
                <h2 class="s-widget-name">@lang('sTask::global.failed_tasks')</h2>
            </div>
            <div class="text-3xl font-semibold text-red-600 mb-1 darkness:text-white">
                {{number_format($stats['failed'] ?? 0, 0, '.', ' ')}}
            </div>
            <span class="text-xs text-slate-500 darkness:text-white/90">@lang('sTask::global.with_errors')</span>
        </div>

        {{-- Total Tasks --}}
        <div class="s-widget">
            <div class="flex items-center gap-2 mb-3">
                <i data-lucide="list" class="w-5 h-5 text-slate-600 darkness:text-white/80"></i>
                <h2 class="s-widget-name">@lang('sTask::global.total_tasks')</h2>
            </div>
            <div class="text-3xl font-semibold text-slate-800 mb-1 darkness:text-white">
                {{number_format($stats['total'] ?? 0, 0, '.', ' ')}}
            </div>
            <span class="text-xs text-slate-500 darkness:text-white/90">@lang('sTask::global.all_time')</span>
        </div>
    </section>

    {{-- Recent Tasks --}}
    <section class="px-6 pb-6">
        <div class="rounded-2xl bg-white/70 ring-1 ring-blue-200 p-6 darkness:bg-[#0f2645] darkness:bg-opacity-60 darkness:ring-[#113c6e]">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2 text-slate-800 font-medium text-lg darkness:text-slate-100">
                    <i data-lucide="activity" class="w-5 h-5 text-blue-500 darkness:text-sky-400"></i>
                    @lang('sTask::global.recent_tasks')
                </div>
                {{--<a href="{{route('sTask.index')}}" class="text-sm text-blue-600 hover:underline darkness:text-sky-400">
                    @lang('sTask::global.view_all')
                </a>--}}
            </div>
            @if(($tasks?->count() ?? 0) > 0)
                <div class="py-3 overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-slate-200 darkness:border-slate-700">
                            <tr class="text-left text-sm text-slate-600 darkness:text-slate-300">
                                <th class="pb-3 font-medium">ID</th>
                                <th class="pb-3 font-medium">@lang('sTask::global.worker')</th>
                                <th class="pb-3 font-medium">@lang('sTask::global.action')</th>
                                <th class="pb-3 font-medium">@lang('sTask::global.status')</th>
                                <th class="pb-3 font-medium">@lang('sTask::global.progress')</th>
                                <th class="pb-3 font-medium">@lang('sTask::global.created')</th>
                                {{--<th class="pb-3 font-medium text-right">@lang('sTask::global.actions')</th>--}}
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 darkness:divide-slate-700">
                            @foreach($tasks as $task)
                                <tr class="text-sm darkness:text-slate-100">
                                    <td class="py-3 font-mono text-xs text-slate-500 darkness:text-slate-400">#{{$task->id}}</td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 rounded bg-blue-100 text-blue-700 text-xs font-medium darkness:bg-blue-900 darkness:text-blue-300">
                                            {{$task->identifier}}
                                        </span>
                                    </td>
                                    <td class="py-3">{{$task->action}}</td>
                                    <td class="py-3">
                                        @if($task->status == \Seiger\sTask\Models\sTaskModel::TASK_STATUS_QUEUED)
                                            <span class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs font-medium darkness:bg-gray-700 darkness:text-gray-300">@lang('sTask::global.pending')</span>
                                        @elseif($task->status == \Seiger\sTask\Models\sTaskModel::TASK_STATUS_PREPARING)
                                            <span class="px-2 py-1 rounded bg-yellow-100 text-yellow-700 text-xs font-medium darkness:bg-yellow-900 darkness:text-yellow-300">@lang('sTask::global.preparing')</span>
                                        @elseif($task->status == \Seiger\sTask\Models\sTaskModel::TASK_STATUS_RUNNING)
                                            <span class="px-2 py-1 rounded bg-blue-100 text-blue-700 text-xs font-medium darkness:bg-blue-900 darkness:text-blue-300">@lang('sTask::global.running')</span>
                                        @elseif($task->status == \Seiger\sTask\Models\sTaskModel::TASK_STATUS_FINISHED)
                                            <span class="px-2 py-1 rounded bg-green-100 text-green-700 text-xs font-medium darkness:bg-green-900 darkness:text-green-300">@lang('sTask::global.completed')</span>
                                        @elseif($task->status == \Seiger\sTask\Models\sTaskModel::TASK_STATUS_FAILED)
                                            <span class="px-2 py-1 rounded bg-red-100 text-red-700 text-xs font-medium darkness:bg-red-900 darkness:text-red-300">@lang('sTask::global.failed')</span>
                                        @else
                                            <span class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs font-medium darkness:bg-gray-700 darkness:text-gray-300">@lang('sTask::global.unknown')</span>
                                        @endif
                                    </td>
                                    <td class="py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-24 bg-slate-200 rounded-full h-2 darkness:bg-slate-700">
                                                <div class="bg-blue-600 h-2 rounded-full darkness:bg-blue-400" style="width: {{$task->progress}}%"></div>
                                            </div>
                                            <span class="text-xs text-slate-500 darkness:text-slate-400">{{$task->progress}}%</span>
                                        </div>
                                    </td>
                                    <td class="py-3 text-xs text-slate-500 darkness:text-slate-400">
                                        {{$task->created_at->diffForHumans()}}
                                    </td>
                                    {{--<td class="py-3 text-right">
                                        <a href="{{route('stask.task.show', $task->id)}}" class="text-blue-600 hover:underline text-xs darkness:text-sky-400">
                                            @lang('sTask::global.details')
                                        </a>
                                    </td>--}}
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-slate-600 text-sm darkness:text-slate-100">@lang('sTask::global.no_tasks_yet')</p>
            @endif
        </div>
    </section>
@endsection


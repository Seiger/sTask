@extends('sTask::index')
@section('content')
    <section class="p-6">
        <div class="rounded-2xl bg-white/70 ring-1 ring-blue-200 darkness:bg-[#0f2645] darkness:bg-opacity-60 darkness:ring-[#113c6e]">
            @if($workers->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-slate-200 darkness:border-slate-700">
                        <tr class="text-left text-sm text-slate-600 darkness:text-slate-300">
                            <th class="p-4 font-medium">ID</th>
                            <th class="p-4 font-medium">@lang('sTask::global.identifier')</th>
                            <th class="p-4 font-medium">@lang('sTask::global.class')</th>
                            <th class="p-4 font-medium">@lang('sTask::global.description')</th>
                            <th class="p-4 font-medium">@lang('sTask::global.position')</th>
                            <th class="p-4 font-medium">@lang('sTask::global.tasks_count')</th>
                            <th class="p-4 font-medium">@lang('sTask::global.status')</th>
                            <th class="p-4 font-medium text-right">@lang('sTask::global.actions')</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 darkness:divide-slate-700">
                        @foreach($workers as $worker)
                            <tr class="text-sm darkness:text-slate-100 hover:bg-slate-50 darkness:hover:bg-slate-800">
                                <td class="p-4 font-mono text-xs text-slate-500 darkness:text-slate-400">{{$worker->id}}</td>
                                <td class="p-4">
                                    <span class="px-2 py-1 rounded bg-blue-100 text-blue-700 text-xs font-medium darkness:bg-blue-900 darkness:text-blue-300">
                                        {{$worker->identifier}}
                                    </span>
                                </td>
                                <td class="p-4 font-mono text-xs text-slate-600 darkness:text-slate-400">
                                    {{$worker->class}}
                                </td>
                                <td class="p-4">{{$worker->description}}</td>
                                <td class="p-4 text-center">{{$worker->position}}</td>
                                <td class="p-4 text-center">
                                        <span class="px-2 py-1 rounded bg-slate-100 text-slate-700 text-xs font-medium darkness:bg-slate-700 darkness:text-slate-300">
                                            {{$worker->tasks()->count()}}
                                        </span>
                                </td>
                                <td class="p-4">
                                    @if($worker->active)
                                        <span class="px-2 py-1 rounded bg-green-100 text-green-700 text-xs font-medium darkness:bg-green-900 darkness:text-green-300">
                                                <i data-lucide="check" class="w-3 h-3 inline"></i> @lang('sTask::global.active')
                                            </span>
                                    @else
                                        <span class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs font-medium darkness:bg-gray-700 darkness:text-gray-300">
                                                <i data-lucide="x" class="w-3 h-3 inline"></i> @lang('sTask::global.inactive')
                                            </span>
                                    @endif
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($worker->active)
                                            <button onclick="toggleWorker('{{$worker->identifier}}', false)" class="text-xs text-red-600 hover:underline darkness:text-red-400">
                                                @lang('sTask::global.deactivate')
                                            </button>
                                        @else
                                            <button onclick="toggleWorker('{{$worker->identifier}}', true)" class="text-xs text-green-600 hover:underline darkness:text-green-400">
                                                @lang('sTask::global.activate')
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-6 text-center">
                    <i data-lucide="inbox" class="w-12 h-12 text-slate-300 mx-auto mb-3 darkness:text-slate-600"></i>
                    <p class="text-slate-600 text-sm darkness:text-slate-100">@lang('sTask::global.no_workers_found')</p>
                </div>
            @endif
        </div>
    </section>
@endsection

@push('scripts.bot')
    <script>
        function toggleWorker(identifier, activate) {
            const action = activate ? 'activate' : 'deactivate';
            const route = activate ? '{{route('sTask.workers.activate')}}' : '{{route('sTask.workers.deactivate')}}';

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
    </script>
@endpush


@extends('sTask::index')
@section('content')

<div class="max-w-11xl mx-auto py-2 px-6">
    @if($workers->count() > 0)
        @foreach($workers as $worker)
            <div class="py-2 col-sm-12 col-md-6 col-lg-4">
                <div class="worker-widget {{$worker->active ? 'active' : ''}}">
                    <div class="worker-header {{$worker->active ? 'active' : ''}}">
                        <div class="worker-info">
                            <div class="worker-icon"><i class="fas fa-cog"></i></div>
                            <div>
                                <h3 class="worker-title">
                                    <span class="status-dot {{$worker->active ? 'active' : 'inactive'}}"></span>
                                    @php
                                        $workerInstance = class_exists($worker->class) ? new $worker->class() : null;
                                    @endphp
                                    {{$workerInstance && method_exists($workerInstance, 'title') ? $workerInstance->title() : ucfirst(str_replace('_', ' ', $worker->identifier))}}
                                </h3>
                            </div>
                        </div>
                        <div class="worker-actions">
                            <i data-lucide="settings" class="settings-icon" onclick="openWorkerSettings('{{$worker->identifier}}')"></i>
                        </div>
                    </div>
                    @if($worker->active){!!$worker->renderWidget()!!}@endif
                </div>
            </div>
        @endforeach
    @else
        <div class="col-12">
            <div class="worker-widget">
                <div class="empty-state">
                    <i data-lucide="cpu" class="w-12 h-12"></i>
                    <h3>@lang('sTask::global.no_workers_found')</h3>
                    <p>@lang('sTask::global.add_worker_or_install_package')</p>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts.bot')
    <script>
        function openWorkerSettings(identifier) {
            const baseUrl = '{{route('sTask.worker.settings', ['identifier' => '__IDENTIFIER__'])}}';
            window.location.href = baseUrl.replace('__IDENTIFIER__', identifier);
        }

        // Initialize Lucide icons
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
    @include('sTask::scripts.task')
    @include('sTask::scripts.global')
@endpush

@extends('sTask::index')
@section('content')
<style>
    .worker-widget {
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        background: #fff;
        transition: all 0.3s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .worker-widget:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateY(-1px);
    }
    .worker-widget.active {
        border-color: #10b981;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
    }
    .worker-widget:not(.active) {
        background: #f9fafb;
        border-color: #d1d5db;
        opacity: 0.7;
    }
    .worker-header {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #e1e5e9;
        display: flex;
        justify-content: between;
        align-items: center;
        background: #f8f9fa;
        border-radius: 8px 8px 0 0;
    }
    .worker-header.active {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }
    .worker-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
    }
    .worker-icon {
        font-size: 1.5rem;
        color: #6b7280;
        min-width: 2rem;
    }
    .worker-header.active .worker-icon {
        color: white;
    }
    .worker-title {
        font-weight: 600;
        font-size: 1.1rem;
        margin: 0;
        color: #374151;
    }
    .worker-header.active .worker-title {
        color: white;
    }
    .worker-identifier {
        font-size: 0.875rem;
        color: #6b7280;
        font-family: 'Courier New', monospace;
    }
    .worker-header.active .worker-identifier {
        color: rgba(255,255,255,0.8);
    }
    .worker-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .settings-icon {
        width: 20px;
        height: 20px;
        color: #6b7280;
        cursor: pointer;
        transition: color 0.2s ease;
    }
    .settings-icon:hover {
        color: #374151;
    }
    .worker-header.active .settings-icon {
        color: rgba(255,255,255,0.8);
    }
    .worker-header.active .settings-icon:hover {
        color: white;
    }
    .worker-body {
        padding: 1.25rem;
    }
    .worker-description {
        color: #6b7280;
        margin-bottom: 1rem;
        line-height: 1.5;
    }
    .worker-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .stat-item {
        text-align: center;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 6px;
    }
    .stat-value {
        font-size: 1.25rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.25rem;
    }
    .stat-label {
        font-size: 0.875rem;
        color: #6b7280;
    }
    .worker-controls {
        display: flex;
        gap: 0.75rem;
        align-items: center;
    }
    .btn-worker {
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .btn-worker-primary {
        background: #3b82f6;
        color: white;
    }
    .btn-worker-primary:hover {
        background: #2563eb;
        transform: translateY(-1px);
    }
    .btn-worker-success {
        background: #10b981;
        color: white;
    }
    .btn-worker-success:hover {
        background: #059669;
        transform: translateY(-1px);
    }
    .btn-worker-danger {
        background: #ef4444;
        color: white;
    }
    .btn-worker-danger:hover {
        background: #dc2626;
        transform: translateY(-1px);
    }
    .btn-worker-secondary {
        background: #6b7280;
        color: white;
    }
    .btn-worker-secondary:hover {
        background: #4b5563;
        transform: translateY(-1px);
    }
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.75rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    .status-active {
        background: #d1fae5;
        color: #065f46;
    }
    .status-inactive {
        background: #f3f4f6;
        color: #374151;
    }
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #6b7280;
    }
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    /* Dark mode */
    body.darkness .worker-widget {
        background: #1f2937;
        border-color: #374151;
    }
    body.darkness .worker-header {
        background: #374151;
        border-color: #4b5563;
    }
    body.darkness .worker-header.active {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    body.darkness .worker-title {
        color: #f9fafb;
    }
    body.darkness .worker-identifier {
        color: #9ca3af;
    }
    body.darkness .worker-description {
        color: #9ca3af;
    }
    body.darkness .stat-item {
        background: #374151;
    }
    body.darkness .stat-value {
        color: #f9fafb;
    }
    body.darkness .stat-label {
        color: #9ca3af;
    }
</style>

<div class="row form-row widgets">
    @if($workers->count() > 0)
        @foreach($workers as $worker)
            <div class="col-sm-12 col-md-6 col-lg-4">
                <div class="worker-widget {{$worker->active ? 'active' : ''}}">
                    <div class="worker-header {{$worker->active ? 'active' : ''}}">
                        <div class="worker-info">
                            <div class="worker-icon"><i class="fas fa-cog"></i></div>
                            <div>
                                <h3 class="worker-title">
                                    @php
                                        $workerInstance = class_exists($worker->class) ? new $worker->class() : null;
                                    @endphp
                                    {{$workerInstance && method_exists($workerInstance, 'title') ? $workerInstance->title() : ucfirst(str_replace('_', ' ', $worker->identifier))}}
                                </h3>
                            </div>
                        </div>
                        <div class="worker-actions">
                            <i data-lucide="settings" class="settings-icon" onclick="openWorkerSettings('{{$worker->identifier}}')"></i>
                            <span class="status-badge {{$worker->active ? 'status-active' : 'status-inactive'}}">
                                <i data-lucide="{{$worker->active ? 'check' : 'x'}}" class="w-3 h-3"></i>
                                {{$worker->active ? __('sTask::global.active') : __('sTask::global.inactive')}}
                            </span>
                        </div>
                    </div>
                    
                    <div class="worker-body">
                        <p class="worker-description">@lang('sTask::global.worker_description')</p>
                        
                        <div class="worker-stats">
                            <div class="stat-item">
                                <div class="stat-value">{{$worker->tasks()->count()}}</div>
                                <div class="stat-label">@lang('sTask::global.tasks_count')</div>
                            </div>
                        </div>
                        
                        <div class="worker-controls">
                            <button onclick="runWorker('{{$worker->identifier}}')" class="btn-worker btn-worker-primary">
                                <i data-lucide="play" class="w-4 h-4"></i>
                                @lang('sTask::global.run_task')
                            </button>
                        </div>
                    </div>
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

    function openWorkerSettings(identifier) {
        const baseUrl = '{{route('sTask.workers.settings', ['identifier' => '__IDENTIFIER__'])}}';
        window.location.href = baseUrl.replace('__IDENTIFIER__', identifier);
    }

    function runWorker(identifier) {
        // Create a new task for the worker
        fetch('{{route('sTask.tasks.store')}}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{csrf_token()}}'
            },
            body: JSON.stringify({ 
                identifier: identifier,
                action: 'make'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alertify.success(data.message || '@lang('sTask::global.task_created')');
                // Optionally redirect to tasks page or show progress
                setTimeout(() => {
                    window.location.href = '{{route('sTask.index')}}';
                }, 1000);
            } else {
                alertify.error(data.message || '@lang('sTask::global.error')');
            }
        })
        .catch(error => {
            alertify.error('@lang('sTask::global.error')');
            console.error(error);
        });
    }

    // Initialize Lucide icons
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>
@endpush


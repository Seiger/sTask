@extends('sTask::index')
@section('content')
    <style>
        .worker-settings {
            max-width: 800px;
            margin: 0 auto;
        }
        .worker-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .worker-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .worker-icon {
            font-size: 2rem;
            color: white;
        }
        .worker-details h1 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .worker-identifier {
            font-family: 'Courier New', monospace;
            background: rgba(255,255,255,0.2);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .settings-section {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .settings-section h3 {
            margin: 0 0 1rem 0;
            color: #374151;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 4px;
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
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .btn-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-active {
            background: #dcfce7;
            color: #166534;
        }
        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        .worker-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.25rem;
        }
        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
        }
        body.darkness .worker-settings {
            background: #1f2937;
        }
        body.darkness .settings-section {
            background: #374151;
            border-color: #4b5563;
        }
        body.darkness .settings-section h3 {
            color: #f9fafb;
        }
        body.darkness .form-group label {
            color: #f9fafb;
        }
        body.darkness .form-control {
            background: #4b5563;
            border-color: #6b7280;
            color: #f9fafb;
        }
        body.darkness .stat-item {
            background: #4b5563;
        }
        body.darkness .stat-value {
            color: #f9fafb;
        }
        body.darkness .stat-label {
            color: #d1d5db;
        }
    </style>

    <div class="max-w-7xl mx-auto py-3 px-6">
        <div class="worker-header">
            <div class="worker-info">
                <div class="worker-icon"><i class="fas fa-cog"></i></div>
                <div class="worker-details">
                    <h1>
                        @php
                            $workerInstance = class_exists($worker->class) ? new $worker->class() : null;
                        @endphp
                        {{$workerInstance && method_exists($workerInstance, 'title') ? $workerInstance->title() : ucfirst(str_replace('_', ' ', $worker->identifier))}}
                    </h1>
                    <div class="worker-identifier">{{$worker->identifier}}</div>
                </div>
                <div style="margin-left: auto;">
                <span class="status-badge {{$worker->active ? 'status-active' : 'status-inactive'}}">
                    <i data-lucide="{{$worker->active ? 'check' : 'x'}}" class="w-3 h-3"></i>
                    {{$worker->active ? __('sTask::global.active') : __('sTask::global.inactive')}}
                </span>
                </div>
            </div>
        </div>

        <div class="settings-section">
            <h3>@lang('sTask::global.worker_statistics')</h3>
            <div class="worker-stats">
                <div class="stat-item">
                    <div class="stat-value">{{$worker->tasks()->count()}}</div>
                    <div class="stat-label">@lang('sTask::global.tasks_count')</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">{{$worker->scope}}</div>
                    <div class="stat-label">@lang('sTask::global.scope')</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">{{$worker->created_at->format('d.m.Y')}}</div>
                    <div class="stat-label">@lang('sTask::global.created')</div>
                </div>
            </div>
        </div>

        <div class="settings-section">
            <h3>@lang('sTask::global.worker_actions')</h3>
            <div class="btn-group">
                @if($workerInstance && method_exists($workerInstance, 'taskMake'))
                    <button onclick="runWorker('{{$worker->identifier}}')" class="btn btn-primary">
                        <i data-lucide="play" class="w-4 h-4"></i>
                        @lang('sTask::global.run_task')
                    </button>
                @endif

                @if($worker->active)
                    <button onclick="toggleWorker('{{$worker->identifier}}', false)" class="btn btn-danger">
                        <i data-lucide="pause" class="w-4 h-4"></i>
                        @lang('sTask::global.deactivate')
                    </button>
                @else
                    <button onclick="toggleWorker('{{$worker->identifier}}', true)" class="btn btn-success">
                        <i data-lucide="play-circle" class="w-4 h-4"></i>
                        @lang('sTask::global.activate')
                    </button>
                @endif

                <a href="{{route('sTask.workers')}}" class="btn btn-secondary">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    @lang('sTask::global.back_to_workers')
                </a>
            </div>
        </div>

        @if($workerInstance && method_exists($workerInstance, 'settings'))
            <div class="settings-section">
                <h3>@lang('sTask::global.worker_settings')</h3>
                <div class="form-group">
                    <label>@lang('sTask::global.worker_class')</label>
                    <input type="text" class="form-control" value="{{$worker->class}}" readonly>
                </div>
                <div class="form-group">
                    <label>@lang('sTask::global.worker_description')</label>
                    <textarea class="form-control" rows="3" readonly>@php
                            if (method_exists($workerInstance, 'description')) {
                                echo $workerInstance->description();
                            } else {
                                echo __('sTask::global.worker_description');
                            }
                        @endphp</textarea>
                </div>
            </div>
        @endif
    </div>

    <script>
        function toggleWorker(identifier, activate) {
            const action = activate ? 'activate' : 'deactivate';
            const route = activate ? '{{route('sTask.worker.activate')}}' : '{{route('sTask.worker.deactivate')}}';

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

        function runWorker(identifier) {
            // Create a new task for the worker using the new action controller
            const baseUrl = '{{route('sTask.worker.task.run', ['identifier' => '__IDENTIFIER__', 'action' => 'make'])}}';
            const url = baseUrl.replace('__IDENTIFIER__', identifier);

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{csrf_token()}}'
                },
                body: JSON.stringify({})
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alertify.success(data.message || '@lang('sTask::global.task_created')');
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
    </script>
    @include('sTask::scripts.task')
    @include('sTask::scripts.global')
@endsection

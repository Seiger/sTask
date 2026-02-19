@extends('sTask::index')
@section('content')
    {{-- TODO: Must be used Tiilwind CSS  --}}
    <style>
        :root {
            --worker-header-bg: linear-gradient(135deg, #24406a 0%, #2960a1 100%);
            --worker-header-border: #2960a1;
            --worker-header-color: #fdfdff;
        }
        body.darkness {
            --worker-header-bg: linear-gradient(135deg, #1c3557 0%, #214c87 100%);
            --worker-header-border: #214c87;
            --worker-header-color: #f6f8ff;
        }
        .worker-widget {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
            overflow: hidden;
        }
        .worker-widget:hover {
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
            transform: translateY(-1px);
        }
        .worker-widget.active {
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }
        .worker-widget:not(.active) {
            background: #f9fafb;
            border-color: #d1d5db;
            opacity: 0.7;
        }
        .worker-header {
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            justify-content: between;
            align-items: center;
            background: var(--worker-header-bg);
            border-color: var(--worker-header-border);
            color: var(--worker-header-color);
        }
        .worker-header.active {
            background: var(--worker-header-bg);
            border-color: var(--worker-header-border);
            color: var(--worker-header-color);
        }
        .worker-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
            flex-wrap: wrap;
        }
        .worker-icon {
            font-size: 1.5rem;
            color: var(--worker-header-color);
            min-width: 2rem;
        }
        .worker-header.active .worker-icon {
            color: var(--worker-header-color);
        }
        .worker-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
            color: var(--worker-header-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .worker-header.active .worker-title {
            color: var(--worker-header-color);
        }
        .worker-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        @media (max-width: 640px) {
            .worker-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.35rem;
            }
            .worker-info .btn {
                width: 100%;
                justify-content: center;
            }
            .worker-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            .worker-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
        .settings-icon {
            width: 20px;
            height: 20px;
            color: rgba(255,255,255,0.8);
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .settings-icon:hover {
            color: var(--worker-header-color);
        }
        .worker-header.active .settings-icon {
            color: rgba(255,255,255,0.8);
        }
        .worker-header.active .settings-icon:hover {
            color: white;
        }
        .status-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .status-dot.active {
            background: #10b981;
        }
        .status-dot.inactive {
            background: #9ca3af;
        }
        .worker-body {
            padding: 1.25rem;
        }
        .btn.disabled {opacity:0.6;cursor:not-allowed;pointer-events:none;}
        .btn.disabled:hover {opacity:0.6;}
        .widget-log {
            display: none;
            height:150px;overflow-y:auto;background:#f1f1f1;border:1px solid #e1e1e1;border-radius:.5rem;
            margin:.1rem .9rem .6rem .9rem;padding:.6rem .9rem .6rem .9rem;white-space:normal;line-height:1.15;
            font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial;font-size:.8rem;
            transition: all 0.3s ease;cursor: pointer;
        }
        .widget-log.widget-drag-zone:hover {border-color:#198754;background-color:#f8fff8;}
        .widget-log.widget-drag-zone.drag-over {
            border-color:#198754;background-color:#e8f5e8;transform:scale(1.01);
            box-shadow:0 4px 12px rgba(25, 135, 84, 0.15);
        }
        .widget-log .line-info {color:inherit;}
        .widget-log .line-success {color:#198754;}
        .widget-log .line-error {color:#dc3545;}
        .widget-log p {margin:0;padding:0;}
        .widget-log .line-info,
        .widget-log .line-success,
        .widget-log .line-error {display:block;margin-bottom:0.25rem;}
        .widget-progress {
            position:relative;display:none;grid-template-columns:1fr auto;align-items:center;
            gap:.5rem;height:14px;margin:.1rem .9rem .1rem .9rem;color:#111827;background:#e9eef3;
            border-radius:999px;overflow:clip;
        }
        .widget-progress .widget-progress__bar {
            grid-column:1/2;height:100%;width:0%;display:block;
            background-image:
                    linear-gradient(90deg, #2563eb 0%, #60a5fa 100%),
                    repeating-linear-gradient(45deg, rgba(255,255,255,.12) 0 8px, rgba(255,255,255,.06) 8px 16px);
            background-size: 100% 100%, 24px 100%;
            border-radius:999px;transition:width .6s linear;will-change:width;
            animation: wgShine 1.2s linear infinite;
        }
        .widget-progress .widget-progress__cap {
            position:absolute;left:0;top:0;height:100%;width:6px;border-radius:999px;
            background:radial-gradient(120% 100% at 100% 50%, rgba(17,24,39,.05) 0 60%, transparent 70%);
            pointer-events:none;transform:translateX(0);transition:transform .16s linear;
        }
        .widget-progress .widget-progress__meta {
            grid-column:2/3;display:inline-flex;align-items:baseline;gap:.4rem;font-size:.75rem;line-height:1;
            user-select:none;margin-right:.5rem;
        }
        .widget-progress .widget-progress__pct {font-variant-numeric:tabular-nums;}
        .widget-progress .widget-progress__eta {opacity:.75;font-style:normal;}
        .widget-progress.is-indeterminate .widget-progress__bar {width:35%;animation:wgIndet 1.1s ease-in-out infinite;}
        @keyframes wgShine {to {background-position: 0 0, 24px 0;} }
        @keyframes wgIndet {0% {transform:translateX(-40%);} 50% {transform: translateX(30%);} 100% {transform: translateX(110%);} }
        @keyframes wgPulse {0%, 100%{filter: saturate(1);} 50% {filter: saturate(1.25);} }
        @media (prefers-reduced-motion: reduce){
            .widget-progress .widget-progress__bar {animation: none !important; transition: none;}
            .widget-progress.is-error .widget-progress__bar {animation: none !important;}
        }
        /* Dark mode */
        body.darkness .worker-widget {
            background: #1f2937;
            border-color: #374151;
        }
        body.darkness .worker-header {
            background: var(--worker-header-bg);
            border-color: var(--worker-header-border);
        }
        body.darkness .worker-header.active {
            background: var(--worker-header-bg);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.15rem 0.5rem;
            font-size: 0.7rem;
            border-radius: 999px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .badge-success {
            background: rgba(16, 185, 129, 0.15);
            color: #076449;
        }
        .badge-secondary {
            background: rgba(107, 114, 128, 0.2);
            color: #374151;
        }
        .badge-info {
            background: rgba(59, 130, 246, 0.15);
            color: #1d4ed8;
        }
        .btn {
            padding: 0.25rem 0.75rem;
            border-radius: 5px;
            font-size: 0.75rem;
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
            transform: translateY(-1px);
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-1px);
        }
    </style>
    <div class="py-1"></div>
    <div class="max-w-11xl mx-auto px-5">
        @if($workers->count() > 0)
            @foreach($workers as $worker)
                <div class="py-1">
                    <div class="worker-widget {{$worker->active ? 'active' : ''}}">
                        <div class="worker-header {{$worker->active ? 'active' : ''}}">
                            <div class="worker-info">
                                <div>
                                    <h3 class="worker-title">
                                        <span class="status-dot {{$worker->active ? 'active' : 'inactive'}}"></span>
                                        @php
                                            $workerInstance = class_exists($worker->class) ? new $worker->class() : null;
                                        @endphp
                                        {{$workerInstance && method_exists($workerInstance, 'title') ? $workerInstance->title() : ucfirst(str_replace('_', ' ', $worker->identifier))}}
                                        @if(trim($worker->description ?? ''))<i data-lucide="help-circle" class="settings-icon" data-tooltip="@lang($worker->description)"></i>@endif
                                    </h3>
                                </div>

                                @if($workerInstance && method_exists($workerInstance, 'taskMake'))
                                    <button type="button" class="btn btn-primary" data-run-worker="{{$worker->identifier}}">
                                        <i data-lucide="play" class="w-5 h-5"></i>
                                        @lang('sTask::global.run_task')
                                    </button>
                                @endif
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

        // Initialize Lucide icons and bind run buttons
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            const progressUrlTemplate = "{{route('sTask.task.progress', ['id' => '__ID__'])}}";

            function startWorkerWatcher(identifier, taskId) {
                const root = document.getElementById(`${identifier}Log`);
                if (!root || !taskId) {
                    return false;
                }

                if (typeof widgetClearLog === 'function') {
                    widgetClearLog(root);
                }
                if (typeof widgetLogLine === 'function') {
                    widgetLogLine(root, '_Завдання запущено. Отримую прогрес..._');
                }
                if (typeof widgetProgressBar === 'function') {
                    widgetProgressBar(identifier, 0, '—');
                }
                if (typeof widgetWatcher === 'function') {
                    widgetWatcher(root, progressUrlTemplate.replace('__ID__', taskId), identifier);
                    try {
                        root.scrollIntoView({behavior: 'smooth', block: 'nearest'});
                    } catch (e) {
                        // noop
                    }
                    return true;
                }

                return false;
            }

            document.querySelectorAll('[data-run-worker]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const identifier = btn.dataset.runWorker;
                    btn.disabled = true;
                    btn.classList.add('disabled');
                    fetch('{{route('sTask.worker.task.run', ['identifier' => '__IDENTIFIER__', 'action' => 'make'])}}'.replace('__IDENTIFIER__', identifier), {
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

                                // Stay on workers page and open progress/log area for this worker.
                                const watcherStarted = startWorkerWatcher(identifier, data?.id || 0);
                                if (!watcherStarted) {
                                    // Fallback: reload workers list (still no redirect to dashboard).
                                    setTimeout(() => {
                                        window.location.href = '{{route('sTask.workers')}}';
                                    }, 300);
                                }
                            } else {
                                alertify.error(data.message || '@lang('sTask::global.error')');
                            }
                        })
                        .catch(error => {
                            alertify.error('@lang('sTask::global.error')');
                            console.error(error);
                        })
                        .finally(() => {
                            btn.disabled = false;
                            btn.classList.remove('disabled');
                        });
                });
            });
        });
    </script>
    @include('sTask::scripts.task')
    @include('sTask::scripts.global')
@endpush

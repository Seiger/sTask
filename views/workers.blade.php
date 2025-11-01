@extends('sTask::index')
@section('content')
    {{-- TODO: Must be used Tiilwind CSS  --}}
    <style>
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
            border-color: #2563eb;
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
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            border-radius: 8px 8px 0 0;
        }
        .worker-header.active {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
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
            color: white;
            min-width: 2rem;
        }
        .worker-header.active .worker-icon {
            color: white;
        }
        .worker-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .worker-header.active .worker-title {
            color: white;
        }
        .worker-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .settings-icon {
            width: 20px;
            height: 20px;
            color: rgba(255,255,255,0.8);
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .settings-icon:hover {
            color: white;
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
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            border-color: #4b5563;
        }
        body.darkness .worker-header.active {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        }
        .btn {
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

@php $task = Seiger\sTask\Models\sTaskModel::byIdentifier($identifier ?? '')->running()->orderByDesc('updated_at')->first(); @endphp
<style>
    .btn.disabled {opacity:0.6;cursor:not-allowed;pointer-events:none;}
    .btn.disabled:hover {opacity:0.6;}
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
</style>

<!-- Body -->
<div style="padding: 1rem;">
    <div id="{{$identifier ?? ''}}Progress" class="widget-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <span class="widget-progress__bar"></span>
        <span class="widget-progress__cap"></span>
        <span class="widget-progress__meta">
        <b class="widget-progress__pct">0%</b>
        <i class="widget-progress__eta">—</i>
    </span>
    </div>

    <!-- Status and Actions -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            @if($task && (int)$task->id > 0)
                <span style="display: inline-flex; align-items: center; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 500; background: #dbeafe; color: #1e40af;">
                        <i class="fa fa-spinner fa-spin" style="margin-right: 0.2rem;"></i>
                        @lang('sTask::global.running')
                    </span>
            @else
                <span style="display: inline-flex; align-items: center; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 500; background: #f3f4f6; color: #374151;">
                        <i class="fa fa-pause" style="margin-right: 0.2rem;"></i>
                        @lang('sTask::global.idle')
                    </span>
            @endif
        </div>

        <button onclick="runWorker('{{$identifier ?? ''}}', 'cache')"
                style="display: inline-flex; align-items: center; padding: 0.5rem 1rem; font-size: 0.8rem; font-weight: 500; border-radius: 0.375rem; background: #2563eb; color: white; border: none; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
                onmouseover="this.style.background='#1d4ed8'; this.style.boxShadow='0 4px 6px rgba(0,0,0,0.1)'"
                onmouseout="this.style.background='#2563eb'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.05)'">
            <i class="fa fa-play" style="margin-right: 0.4rem;"></i>
            @lang('sTask::global.run_task')
        </button>
    </div>

    <!-- Description -->
    <p style="font-size: 0.8rem; color: #6b7280; margin: 0; line-height: 1.4;">
        @lang('sTask::global.default_widget_description')
    </p>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if($task && (int)$task->id > 0)
        widgetWatcher("", "{{route('sTask.task.progress', ['id' => $task->id])}}", '{{$key}}');
        @endif
    });
</script>

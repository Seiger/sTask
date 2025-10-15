<div class="widget worker-widget" data-worker-identifier="{{ $identifier }}">
    <div class="widget-header">
        <div class="widget-icon">
            {!! $icon !!}
        </div>
        <div class="widget-info">
            <h3 class="widget-title">{{ $title }}</h3>
            <p class="widget-description">{{ $description }}</p>
        </div>
    </div>
    
    <div class="widget-body">
        <div class="widget-actions">
            <button type="button" 
                    class="btn btn-primary btn-worker-action" 
                    data-worker="{{ $identifier }}"
                    data-action="make">
                <i class="fa fa-play"></i>
                {{ __('sTask::global.run_task') }}
            </button>
        </div>
        
        @if($settings && count($settings) > 0)
        <div class="widget-settings">
            <h4>{{ __('sTask::global.settings') }}</h4>
            <dl class="settings-list">
                @foreach($settings as $key => $value)
                    <dt>{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                    <dd>{{ is_array($value) ? json_encode($value) : $value }}</dd>
                @endforeach
            </dl>
        </div>
        @endif
    </div>
    
    <div class="widget-footer">
        <small class="text-muted">{{ __('sTask::global.scope') }}: {{ $scope }}</small>
    </div>
</div>

<style>
.worker-widget {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 15px;
    background: #fff;
}

.widget-header {
    display: flex;
    align-items: flex-start;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.widget-icon {
    font-size: 32px;
    margin-right: 15px;
    color: #555;
    min-width: 40px;
}

.widget-info {
    flex: 1;
}

.widget-title {
    margin: 0 0 5px 0;
    font-size: 18px;
    font-weight: 600;
}

.widget-description {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.widget-body {
    margin-bottom: 15px;
}

.widget-actions {
    margin-bottom: 15px;
}

.btn-worker-action {
    margin-right: 10px;
}

.widget-settings {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.widget-settings h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    font-weight: 600;
}

.settings-list {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 5px 15px;
    font-size: 13px;
}

.settings-list dt {
    font-weight: 600;
    color: #555;
}

.settings-list dd {
    margin: 0;
    color: #666;
}

.widget-footer {
    padding-top: 10px;
    border-top: 1px solid #eee;
}
</style>


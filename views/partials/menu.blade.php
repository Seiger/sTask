<aside :class="open ? 'w-60' : 'w-16'" class="s-nav" @mouseenter="handleEnter" @mouseleave="handleLeave">
    <div class="s-nav-header">
        <a href="{{route('sTask.index')}}" class="flex items-center gap-1 text-xl font-bold" x-show="open" x-cloak>sTask</a>
        <img x-show="!open" x-cloak src="{{asset('site/stask.svg')}}" class="w-8 h-8 pointer-events-none filter drop-shadow-[0_0_6px_#3b82f6]" alt="sTask">
    </div>
    <nav class="s-nav-menu">
        <a href="{{route('sTask.index')}}" @class(['s-nav-menu-item', 's-nav-menu-item--active' => 'sTask.index' == Route::currentRouteName()])>
            <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
            <span x-show="open">@lang('sTask::global.dashboard')</span>
        </a>
        <a href="{{route('sTask.workers.index')}}" @class(['s-nav-menu-item', 's-nav-menu-item--active' => 'sTask.workers.index' == Route::currentRouteName()])>
            <i data-lucide="cpu" class="w-5 h-5"></i>
            <span x-show="open">@lang('sTask::global.workers')</span>
        </a>
        <a href="{{route('sTask.stats')}}" @class(['s-nav-menu-item', 's-nav-menu-item--active' => 'sTask.stats' == Route::currentRouteName()])>
            <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
            <span x-show="open">@lang('sTask::global.statistics')</span>
        </a>
    </nav>
    <span @click="togglePin" role="button" tabindex="0" class="s-pin-btn" :class="open ? 'left-24' : 'left-4'" title="Pin sidebar / Unpin sidebar">
        <i :data-lucide="pinned ? 'pin-off' : 'pin'" class="w-4 h-4 pointer-events-none"></i>
    </span>
</aside>

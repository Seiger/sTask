@php
    $manager = app(\EvoUI\Support\ManagerContext::class);
    $theme = $manager->theme();
    $themeMode = $manager->themeMode($theme);
    $themeClasses = $manager->themeClasses($theme);
    $themeBackground = $manager->themeBackground($theme);
@endphp
<!doctype html>
<html
    class="evo-ui-page {{ $themeClasses }}"
    lang="{{ str_replace('_', '-', app()->getLocale() ?: ManagerTheme::getLang()) }}"
    data-theme="{{ $theme }}"
    data-theme-mode="{{ $themeMode }}"
    style="background-color: var(--evo-ui-bg, {{ $themeBackground }})"
>
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="{{ $themeMode === 'dark' ? 'dark light' : 'light dark' }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $moduleTitle }}</title>
    @include('evo::partials.assets')
</head>
<body
    class="evo-ui-page {{ $themeClasses }}"
    data-theme="{{ $theme }}"
    data-theme-mode="{{ $themeMode }}"
    style="background-color: var(--evo-ui-bg, {{ $themeBackground }})"
>
    <div
        class="evo-ui {{ $themeClasses }}"
        data-evo-ui-root
        data-theme="{{ $theme }}"
        data-theme-mode="{{ $themeMode }}"
    >
        <livewire:stask.module-panel
            :tabs="$tabs"
            :active-tab="$activeTab"
            :context="$context"
        />
    </div>
</body>
</html>

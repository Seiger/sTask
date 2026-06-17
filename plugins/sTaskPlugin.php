<?php use Seiger\sTask\sTaskServiceProvider;

/**
 * sTask Plugin
 *
 * Add sTask menu item to Evolution CMS manager
 *
 * @package Seiger\sTask
 * @author Seiger IT Team
 * @since 2.0.0
 */
Event::listen('evolution.OnManagerMenuPrerender', function($params) {
    if (evo()->hasPermission('stask')) {
        $icon = sTaskServiceProvider::MODULE_ICON;
        $iconHtml = '<i class="' . $icon . '"></i>';

        if (strpos($icon, 'tabler-') === 0 && function_exists('svg')) {
            $iconHtml = svg($icon, '', [
                'aria-hidden' => 'true',
                'focusable' => 'false',
                'style' => 'flex:0 0 auto;display:inline-block;',
            ])->toHtml();
        }

        $menu['stask'] = [
            'stask',
            'tools',
            $iconHtml . __('sTask::global.title'),
            route('sTask.index'),
            __('sTask::global.title'),
            "",
            "",
            "main",
            0,
            10,
        ];

        return serialize(array_merge($params['menu'], $menu));
    }
});

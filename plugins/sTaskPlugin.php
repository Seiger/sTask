<?php

/**
 * sTask Plugin
 *
 * Add sTask menu item to Evolution CMS manager
 *
 * @package Seiger\sTask
 * @author Seiger IT Team
 * @since 1.0.0
 */
Event::listen('evolution.OnManagerMenuPrerender', function($params) {
    if (evo()->hasPermission('stask')) {
        $menu['stask'] = [
            'stask',
            'tools',
            '<img src="' . asset('site/stask.svg') . '" width="20" height="20" style="display:inline-block;vertical-align:middle;margin-right:8px;transition:filter 0.2s ease;" class="stask-logo">' .  __('sTask::global.title'),
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

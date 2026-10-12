<?php
// Isolated consumer contract: no CMS bootstrap, task execution or database.
$root = dirname(__DIR__);
$panel = file_get_contents($root . '/views/components/module-panel.blade.php');
$css = file_get_contents($root . '/css/module.css');
$composer = json_decode(file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
if (!str_contains($panel, '<x-slot:navigationAside>') || !str_contains($panel, 'role="status"')
    || str_contains($css, '.stask-runner-heartbeat')
    || $composer['require']['evolution-cms/evo-ui'] !== '^1.3') {
    throw new RuntimeException('Runner navigation dependency/layout contract failed.');
}
echo "Runner navigation dependency/layout contract passed\n";

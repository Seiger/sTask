<?php

$labels = include dirname(__DIR__) . '/en/global.php';

$labels['module_title'] = 'Task Manager';
$labels['module_description'] = 'Background task runner dla Evolution CMS: workery, kolejki, progress logs, performance metrics i cache controls.';
$labels['performance'] = 'Wydajnosc';
$labels['performance_alerts'] = 'Alerty wydajnosci';
$labels['no_performance_alerts'] = 'Brak alertow wydajnosci.';
$labels['last_24_hours'] = 'ostatnie 24 godziny';
$labels['success_rate'] = 'Skutecznosc';
$labels['average_duration'] = 'Sredni czas';
$labels['cache_entries'] = 'Wpisy cache';
$labels['worker_cache'] = 'Cache workerow';
$labels['clear_cache'] = 'Wyczysc cache';
$labels['value'] = 'Wartosc';

return $labels;

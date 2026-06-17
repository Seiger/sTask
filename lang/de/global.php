<?php

$labels = include dirname(__DIR__) . '/en/global.php';

$labels['module_title'] = 'Task Manager';
$labels['module_description'] = 'Background Task Runner fuer Evolution CMS: Worker, Queues, Progress Logs, Performance Metrics und Cache Controls.';
$labels['performance'] = 'Performance';
$labels['performance_alerts'] = 'Performance Alerts';
$labels['no_performance_alerts'] = 'Keine Performance Alerts.';
$labels['last_24_hours'] = 'letzte 24 Stunden';
$labels['success_rate'] = 'Erfolgsrate';
$labels['average_duration'] = 'Durchschnittliche Dauer';
$labels['cache_entries'] = 'Cache Eintraege';
$labels['worker_cache'] = 'Worker Cache';
$labels['clear_cache'] = 'Cache leeren';
$labels['value'] = 'Wert';

return $labels;

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
$labels['alert_severity_info'] = 'Information';
$labels['alert_severity_warning'] = 'Warnung';
$labels['alert_severity_error'] = 'Fehler';
$labels['performance_alert_low_success_rate'] = 'Die Erfolgsrate der Aufgaben liegt unter dem Grenzwert: :value';
$labels['performance_alert_high_execution_time'] = 'Die durchschnittliche Ausführungszeit überschreitet den Grenzwert: :value';
$labels['performance_alert_high_memory_usage'] = 'Der durchschnittliche Speicherverbrauch überschreitet den Grenzwert: :value';
$labels['cache_stat_hits'] = 'Treffer';
$labels['cache_stat_misses'] = 'Fehlschläge';
$labels['cache_stat_evictions'] = 'Verdrängungen';
$labels['cache_stat_hit_rate'] = 'Trefferrate';
$labels['cache_stat_cache_size'] = 'Cache-Größe';
$labels['cache_stat_memory_usage'] = 'Speichernutzung';
$labels['emergency_stop_task'] = 'Aufgabe notfallmäßig stoppen';
$labels['task_emergency_stopped'] = 'Aufgabe wurde manuell notfallmäßig gestoppt.';

return $labels;

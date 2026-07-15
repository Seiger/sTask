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
$labels['alert_severity_info'] = 'Informacja';
$labels['alert_severity_warning'] = 'Ostrzeżenie';
$labels['alert_severity_error'] = 'Błąd';
$labels['performance_alert_low_success_rate'] = 'Skuteczność zadań jest poniżej progu: :value';
$labels['performance_alert_high_execution_time'] = 'Średni czas wykonania przekracza próg: :value';
$labels['performance_alert_high_memory_usage'] = 'Średnie użycie pamięci przekracza próg: :value';
$labels['cache_stat_hits'] = 'Trafienia';
$labels['cache_stat_misses'] = 'Pominięcia';
$labels['cache_stat_evictions'] = 'Usunięcia';
$labels['cache_stat_hit_rate'] = 'Współczynnik trafień';
$labels['cache_stat_cache_size'] = 'Rozmiar pamięci podręcznej';
$labels['cache_stat_memory_usage'] = 'Użycie pamięci';
$labels['emergency_stop_task'] = 'Awaryjnie zatrzymaj zadanie';
$labels['task_emergency_stopped'] = 'Zadanie zostało awaryjnie zatrzymane ręcznie.';

return $labels;

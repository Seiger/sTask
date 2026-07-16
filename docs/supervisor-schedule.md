# Supervisor schedules / Розклади супервізора

## Українська

Тип розкладу `supervisor` є додатковою можливістю звичайного воркера, який керує
окремим довготривалим процесом. Такий воркер зберігає стандартний `taskMake()` і
може працювати вручну або за звичайним розкладом. Варіант Supervisor показується
в UI лише якщо клас додатково реалізує `SupervisorWorkerInterface`. Щохвилинний
прохід sTask не виконує процес у поточному PHP-процесі та не створює звичайне
завдання для незмінного здорового стану. Воркер реалізує
`SupervisorWorkerInterface`: повертає стабільний ключ, `SupervisorStatus`, неблокувальні
`startSupervisor()` і `restartSupervisor()`, а також startup grace. sTask зберігає PID,
heartbeat, uptime, діагностику та переходи в `s_supervisor_states`. Рядки `s_tasks`
створюються лише для запуску, рестарту, recovery, stop/failure/degraded або зміни
діагностичного fingerprint. Ручний запуск воркера залишається звичайним видимим
завданням.

## English

The `supervisor` schedule type is an optional capability of a conventional worker
that supervises a detached long-running process. The worker keeps its normal
`taskMake()` path and can still run manually or on a conventional schedule. The
UI offers Supervisor only when the class also implements `SupervisorWorkerInterface`. The
minute scheduler never runs that process inline and creates no normal task for
an unchanged healthy observation. A worker implements `SupervisorWorkerInterface`
and supplies a stable key, `SupervisorStatus`, non-blocking `startSupervisor()` and
`restartSupervisor()` hooks, and startup grace. sTask stores PID, heartbeat, uptime,
diagnostics, and transitions in `s_supervisor_states`. An `s_tasks` event is created
only for launch, restart, recovery, stop/failure/degraded, or a changed
diagnostic fingerprint. Manual worker runs remain normal visible tasks.

## Polski

Typ harmonogramu `supervisor` jest opcjonalną możliwością zwykłego workera, który
nadzoruje odłączony, długotrwały proces. Worker zachowuje standardowy `taskMake()`;
UI pokazuje opcję nadzorcy tylko dla klas implementujących `SupervisorWorkerInterface`. Minutowy
harmonogram nie uruchamia procesu inline i nie tworzy zadania dla niezmienionego
stanu healthy. Worker implementuje `SupervisorWorkerInterface`, zwraca stabilny
klucz i `SupervisorStatus` oraz udostępnia nieblokujące start/restart i startup grace.
Stan bieżący trafia do `s_supervisor_states`, a `s_tasks` zawiera wyłącznie istotne
zdarzenia cyklu życia. Uruchomienie ręczne pozostaje zwykłym widocznym zadaniem.

## Deutsch

Der Zeitplantyp `supervisor` ist eine optionale Fähigkeit eines normalen Workers,
der einen getrennten Langzeitprozess überwacht. Der Worker behält `taskMake()`;
die UI zeigt Supervisor nur für Klassen mit `SupervisorWorkerInterface`. Der
Minutenlauf führt ihn nicht inline aus und erzeugt bei unverändert gesundem
Zustand keine Aufgabe. Der Worker implementiert `SupervisorWorkerInterface` mit
stabilem Schlüssel, `SupervisorStatus`, nicht blockierenden Start-/Neustart-Hooks
und Startup-Grace. Der Live-Zustand liegt in `s_supervisor_states`; `s_tasks`
enthält nur relevante Lebenszyklusereignisse. Manuelle Starts bleiben sichtbare
normale Aufgaben.

## Français

Le type de planification `supervisor` est une capacité facultative d'un worker
standard qui supervise un processus long détaché. Le worker conserve `taskMake()`;
l'UI propose Superviseur uniquement aux classes qui implémentent
`SupervisorWorkerInterface`. Le
passage chaque minute ne l'exécute jamais en ligne et ne crée aucune tâche pour
un état sain inchangé. Le worker implémente `SupervisorWorkerInterface` avec une clé
stable, `SupervisorStatus`, des hooks start/restart non bloquants et un délai de
démarrage. L'état courant est conservé dans `s_supervisor_states`; `s_tasks` ne
contient que les événements significatifs. Un lancement manuel reste une tâche
normale visible.

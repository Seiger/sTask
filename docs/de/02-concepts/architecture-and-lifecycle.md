# Architektur und Lebenszyklus

## Komponenten

| Komponente | Verantwortung |
| --- | --- |
| `sWorker` | Arbeiterprotokoll, Klasse, aktiv/versteckt, Position, JSON-Einstellungen |
| `TaskInterface` | Minimum Metadaten/UI-Vertragsarbeiter |
| `BaseWorker` | Konfiguration, Zeitpläne, Aufgabenerstellung, Aktionsabwicklung, Fortschritt und Finalisierung |
| `sTaskModel` | Persistierte Aufgabe, Status, Meta/Ergebnis, Zeitstempel und Umfang |
| `TaskWorker` | Erstellen geplanter Aufgaben und sequentielles Ausführen einer fertigen Warteschlange |
| `TaskProgress` | Live-Fortschritt nur mit Anhängen in `storage/stask/{id}.log` |
| `WorkerDiscovery` | Suche nach konkreten `TaskInterface` Klassen in Composer classmap |
| `WorkerService` | Auflösung, Validierung und Cache-Worker-Instanzen |
| `SupervisorService` | Serialisierte Inspektion/Start/Neustart langfristiger Prozesse |
| EvoUI + Livewire | Manager-Tabellen, Filter, Modalfenster und HTTP-Abfragefortschritt |

## Bedingungen

- **Worker** ist eine PHP-Klasse, die weiß, wie man eine oder mehrere Aktionen ausführt.
- **Aufgabe** — anhaltender Versuch, eine Aktion einer bestimmten Arbeiterkennung auszuführen.
- **Aktion** ist eine Saite wie `make` oder `sync_stock`; `BaseWorker` verwandelt es in `taskMake()` oder `taskSyncStock()`.
- **Schedule** ist das JSON im `s_workers.settings.schedule`, das `stask:worker` die nächste Warteschlange unterstützt.
- **Fortschritt** — flüchtiger Schnappschuss/Verlauf in Datei `.log`; Das `s_tasks.progress` Feld wird nicht automatisch von jedem `pushProgress()` synchronisiert.
- **Metadaten (`meta`)** — normalisierte Eingabeparameter der Aufgabe, Cast-Modell `array`.
- **Ergebnis** — die letzte Nutzlast oder den Pfad, der vom Arbeiter aufgezeichnet wird; DB-Typ `LONGTEXT`, Gussmodell `array`.
- **Message** ist ein kurzer persistierter Zustand/Fehler in `s_tasks.message`; Live-Historie wird separat gespeichert.

## Schöpfung

Es gibt zwei Hauptmethoden:

1. `sTask::create($identifier, $action, $data, $priority, $userId)`.
2. `$worker->createTask($action, $options)` in `BaseWorker`.

Beide normalisieren Meta, indem sie assoziative Schlüssel rekursiv sortieren, und suchen nach aktivem Duplikat nach `identifier + action + normalized meta`. Aktiver Status `10`, `30`, `50`.

Dies schützt nur gegen identische aktive Datensätze. Dies ist kein globaler verteilter Lock und kein Ersatz für Geschäftsbetrieb.

## Hinrichtung

`stask:worker` folgt folgenden Schritten:

1. Liest alle aktiven Arbeiter;
2. für aktivierte Zeitpläne werden fehlende Folgeaufgaben erstellt oder ein Supervisor-Pass ausgeführt;
3. Wählt alle Warteschlangen-Aufgaben aus, bei denen `start_at IS NULL OR start_at <= now()`;
4. Für jede Aufgabe finden Sie den aktiven Arbeitereintrag und die Klasse;
5. Prüft `TaskInterface`;
6. Puts-Status `running`, `start_at = now()`, steigt `attempts`;
7. Ursachen `invokeAction()`;
8. Wenn der Arbeiter die Aufgabe nicht abgeschlossen hat, setzt er `finished` automatisch;
9. Mit Ausnahme setzt es `failed` und dokumentiert den Fehlerfortschritt;
10. Wenn keine aktiven Aufgaben vorhanden sind, löscht er Fortschrittsdateien älter als 24 Stunden.

Der aktuelle Befehl lädt alle fertiggestellten Warteschlangenzeilen ohne Batch-Begrenzung und verarbeitet nacheinander. Plane das Volumen so, dass ein Cron-Durchgang nicht unbegrenzt einfriert.

## Status

| Code | Konstante | Text | Bedeutung |
| ---: | --- | --- | --- |
| 10 | `TASK_STATUS_QUEUED` | `pending` | Wartezeit/Arbeiterpass |
| 30 | `TASK_STATUS_PREPARING` | `preparing` | Zwischenzustand für Anwendungscode |
| 50 | `TASK_STATUS_RUNNING` | `running` | Aktion läuft oder gilt als aktiv |
| 80 | `TASK_STATUS_FINISHED` | `completed` | Erfolgreicher Endzustand |
| 100 | `TASK_STATUS_FAILED` | `failed` | Fehler oder Notstopp |

Es gibt keinen separaten Status als dauerhaft storniert. `isFinished()` kehrt `true` nur für `80` und `100` zurück.

## Zeitstempel und Dauer

- `created_at` — wenn ein Datensatz erstellt wurde.
- `start_at` ist die geplante Zeit vor der Hinrichtung, und nach `markAsRunning()` ist der eigentliche Beginn.
- `finished_at` — Finalisierung.
- `updated_at` ist die letzte Änderung, die aufgenommen werden muss.
- Accessor `duration` — Sekunden von `start_at` bis `finished_at`; für eine laufende Aufgabe, von `start_at` bis zur aktuellen Zeit.

Aufgrund des doppelten `start_at` -Werts zeigt die bevorstehende Warteschlange-Aufgabe den geplanten Start, und die abgeschlossene Aufgabe zeigt den tatsächlichen Start, der beim Start gesetzt ist.

## Versuch es erneut

`markAsRunning()` erhöht `attempts`. `canRetry()` gilt für eine fehlgeschlagene Aufgabe, solange `attempts < max_attempts`. Der Standard- `TaskWorker` gibt jedoch eine fehlgeschlagene Aufgabe nicht automatisch in der Warteschlange zurück und besitzt keinen separaten Befehl für den Wiederholen. Die Retry-Policy muss von einem Integrator oder Worker umgesetzt werden. Versprechen Sie den Nutzern keine automatischen Wiederholungen direkt nach `max_attempts = 3`.

## Fortschritt und Live-UI

`pushProgress()` fügt dem `.log` eine einzelne gepfeift-getrennte Saite hinzu. JavaScript Watcher:

- startet in Abständen von 1,2 Sekunden;
- erhöht die Verzögerung auf 25 Sekunden, wenn sich der Schnappschuss nicht ändert;
- überprüft einmal alle 5 Sekunden im Tab Versteckt/Unsichtbar;
- keine parallelen Anfragen stellt;
- stoppt bei `finished`, `failed`, `completed` oder nach fünf Netzwerkausfällen;
- nach dem Endstatus bittet er Livewire, die Oberfläche zu erneuern.

Das ist HTTP-Polling, kein Push-Transport.

# Routen, Fortschrittsdateien und Downloads

## API-Status

Alle untenstehenden Routen befinden sich in der Manager-Middleware-Gruppe `mgr`. Dies ist eine interne Manager-HTTP-Oberfläche, keine öffentliche REST-API für externe Clienten. Nutze Session-Authentifizierung und CSRF für POST.

## Routen

| Methode | Weg | Streckenname | Zweck |
| --- | --- | --- | --- |
| HOL | `/stask` | `sTask.index` | Modulshell |
| HOL | `/stask/stats` | `sTask.stats` | Zählungen |
| POST | `/stask/task` | `sTask.task.create` | Fassaden-Aufgabenerstellung |
| POST | `/stask/task/store` | `sTask.task.store` | alias create |
| HOL | `/stask/task/{id}` | `sTask.task.show` | Vollständige Aufgabendetails |
| POST | `/stask/worker/{identifier}/run/{action}` | `sTask.worker.task.run` | erstellen + Starter |
| HOL | `/stask/task/{id}/progress` | `sTask.task.progress` | Fortschrittsübersicht/Verlauf |
| HOL | `/stask/task/{id}/download` | `sTask.task.download` | Ergebnis herunterladen |
| POST | `/stask/task/{id}/upload` | `sTask.task.upload` | Task-bound Upload |
| POST | `/stask/worker/{identifier}/upload` | `sTask.worker.upload` | Pre-Task Worker hochladen |
| POST | `/stask/clean` | `sTask.clean` | Alte erledigte Aufgaben löschen |
| HOL | `/stask/server-limits` | `sTask.serverLimits` | PHP-Upload-Limits |
| HOL | `/stask/workers` | `sTask.workers` | entdecken und umleiten |
| POST | `/stask/worker/clean-orphaned` | `sTask.worker.clean` | fehlende Klassen entfernen |
| POST | `/stask/worker/activate` | `sTask.worker.activate` | Aktivieren nach Identifikator |
| POST | `/stask/worker/deactivate` | `sTask.worker.deactivate` | Deaktivieren nach Identifikator |
| HOL | `/stask/performance/summary` | `sTask.performance.summary` | Kennzahlenzusammenfassung |
| HOL | `/stask/performance/workers` | `sTask.performance.workers` | Arbeiterstatistiken |
| HOL | `/stask/performance/alerts` | `sTask.performance.alerts` | Warnungen |
| HOL | `/stask/cache/stats` | `sTask.cache.stats` | Worker-Cache-Statistiken |
| POST | `/stask/cache/clear` | `sTask.cache.clear` | Worker-Cache löschen |

## Startaktion

```http
POST /stask/worker/search_index/run/make
Content-Type: application/json
X-CSRF-TOKEN: ...

{"batch_size":100,"force":false}
```

Der Controller nimmt verschachtelte `options` oder den gesamten Körper, entfernt `_token` und `options`, löst den aktiven Arbeiter auf und ruft `createTask()` auf.

Erfolgreiche Antwort:

```json
{"success":true,"id":123,"message":"Task created successfully"}
```

HTTP-Code kann 200 bleiben, selbst wenn `success=false`; Der Client sollte das JSON-Flag überprüfen.

Nachdem der Response-Controller `fastcgi_finish_request()` oder synchronen Rückfall verwendet hat und dann versucht, `stask:worker` auszuführen. Dies garantiert keinen separaten Warteschlangenprozess bei allen SAPIs.

## Fortschritts-Endpunkt

```http
GET /stask/task/123/progress?include_log=0
Accept: application/json
```

Success:

```json
{
  "success": true,
  "code": 200,
  "id": 123,
  "status": "running",
  "progress": 42,
  "processed": 420,
  "total": 1000,
  "eta": "37s",
  "message": "Verarbeite Stapel",
  "log_lines": []
}
```

Ohne `include_log=0` Endpoint addiert die letzten 50 Nachrichten. Ungültige ID ergibt 400; Fehlende Fortschrittsdatei — 404.

## Dateiformat

Weg:

```text
core/storage/stask/{taskId}.log
```

Jede nur anhängige Zeile:

```text
status|progress|processed|total|eta|message
```

`readProgress()` liest die letzte gültige Zeile; `readLog()` extrahiert die Nachricht aus den letzten N Zeilen. Ein absichtlicher Schreibfehler stürzt keine Geschäftsaufgabe ab, daher beweist das Fehlen eines Live-Fortschritts nicht, dass die Aufgabe nicht abgeschlossen ist.

## Aufräumen

Wenn Aufgaben in der Warteschlange/Vorbereitung/Ausführung fehlen, löscht `stask:worker`  `*.json` älter als 24 Stunden und temporäre JSON-Daten älter als 10 Stunden. Die aktuelle `TaskProgress` verwendet tatsächlich `*.log`, daher werden diese Logdateien nicht automatisch durch diese Schleife gelöscht. Richte eine separate Aufbewahrungsrichtlinie für `storage/stask/*.log` ein, nachdem du mit den Prüfungsanforderungen abgestimmt hast.

## Hochladen/Herunterladen

Der Controller hat normale und gesegmentierte Upload-Pfade, einen Serverlimit-Endpunkt und worker-spezifische erlaubte Erweiterungen. Dateien werden unter `storage/stask/uploads` gespeichert.

Integrator-Regeln:

- Verlassen Sie sich nicht nur auf Extension;
- Überprüfung des MIME und des tatsächlichen Formats im Worker;
- Begrenzung von Größe und Anzahl der Chunks;
- serverseitige Dateinamen zu generieren;
- Pfaddurchquerung nicht zulassen;
- temporäre/Ergebnisdateien durch Aufbewahrungspolitik zu löschen;
- Download Path nicht zurückgeben, bis die Datei existiert und zur Aufgabe gehört.

Die genaue Upload-Nutzlast hängt vom Widget-/Worker-Vertrag ab; Denk nicht an Endpoint als universelle Datei-API.

## Berechtigungen

`sTaskController::index()` und `show()` prüfen ausdrücklich die Berechtigung `stask`; Der Teil der Aktionsmethoden beruht nur auf `mgr`. Infrastrukturell wird die Modul-Route-Manager-Sitzung auf die Sitzung beschränkt, und in benutzerdefinierten Controllern wird die Berechtigungsprüfung für destruktive Operationen wiederholt.

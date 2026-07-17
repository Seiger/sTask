# CLI, Status und Routen

## CLI

### `stask:worker`

```bash
php artisan stask:worker
```

Keine Optionen. Erstellt geplante Aufgaben, überwacht Daemon-Worker, verarbeitet alle bereitstehenden Warteschlangen-Aufgaben nacheinander, führt Cleanup-if-idle aus und gibt Exit-Code 0 zurück, selbst wenn einzelne Aufgaben ausfallen (Ausnahmen werden pro Aufgabe erfasst).

Geplanter Anbieter: jede Minute.

### `stask:publish`

```bash
php artisan stask:publish
```

Kopiert Paket-Assets in öffentliche `assets/site`; verwendet `Filesystem`. Nach dem Aktualisieren des Pakets erneut ausführen.

### Verwandte Evolutionsbefehle

```bash
php artisan migrate
php artisan package:discover
php artisan route:list --path=stask
php artisan schedule:list
php artisan cache:clear-full
```

Ihre genaue Verfügbarkeit hängt von der Evolution CMS-Version ab.

## Aufgabenstatus

| Code | PHP-Konstante | API-Text | Aktiv | Abschließend |
| ---: | --- | --- | --- | --- |
| 10 | `TASK_STATUS_QUEUED` | ausstehend | ja | Nein |
| 30 | `TASK_STATUS_PREPARING` | Vorbereitung | ja | Nein |
| 50 | `TASK_STATUS_RUNNING` | Laufen | ja | Nein |
| 80 | `TASK_STATUS_FINISHED` | Abgeschlossen | Nein | ja |
| 100 | `TASK_STATUS_FAILED` | gescheitert | Nein | ja |

Unbekannter Code → `unknown`.

## Aufseher-Zustand

| Wert | Bedeutung | erfordertStart |
| --- | --- | --- |
| `healthy` | Verfügbarer Prozess | Nein |
| `starting` | Start-up in Arbeit | Nein |
| `degraded` | Die Gesundheit hat sich verschlechtert | ja |
| `failed` | Inspektion/Startfehler | ja |
| `stopped` | Prozess gestoppt/nicht gefunden | ja |

## Zeitplanwerte

Typen: `manual`, `once`, `periodic`, `regular`, `supervisor`.

Frequenzen:

- periodisch: `minutely`, `every_5min`, `every_15min`, `every_30min`, `hourly`, `daily`, `weekly`, `monthly`;
- regulär: `every_5min`, `every_15min`, `every_30min`, `hourly`.

## Manager-Routen

Alle haben ein Präfix `/stask`, Routennamen-Präfix `sTask.` und Middleware `mgr`. Vollständige Tabelle mit Methoden und Nutzlastkontext: [Routen, Fortschrittsdateien und Downloads](../04-development/routes-and-progress.md).

Schlüssel:

```text
GET  /stask
POST /stask/worker/{identifier}/run/{action}
GET  /stask/task/{id}/progress
GET  /stask/task/{id}
GET  /stask/performance/summary
POST /stask/cache/clear
```

## Öffentliche PHP-Kurse

| Klasse | Rolle |
| --- | --- |
| `Seiger\sTask\sTask` | Fassadenservice |
| `Seiger\sTask\Facades\sTask` | Laravel-Fassade |
| `Seiger\sTask\Workers\BaseWorker` | Arbeiterbasis |
| `Seiger\sTask\Contracts\TaskInterface` | Arbeitnehmervertrag |
| `Seiger\sTask\Contracts\SupervisorWorkerInterface` | Dämonenfähigkeit |
| `Seiger\sTask\Support\SupervisorStatus` | Immutable Health Snapshot |
| `Seiger\sTask\Enums\SupervisorState` | Daemon sagt |
| `Seiger\sTask\Models\sTaskModel` | Aufgabe Eloquentes Modell |
| `Seiger\sTask\Models\sWorker` | Worker Eloquent-Modell |
| `Seiger\sTask\Models\sSupervisorState` | Live-State-Modell |
| `Seiger\sTask\Services\TaskProgress` | Dateifortschritt |
| `Seiger\sTask\Services\WorkerDiscovery` | Registry-Entdeckung |
| `Seiger\sTask\Services\WorkerService` | Auflösung/Cache |
| `Seiger\sTask\Services\SupervisorService` | Lebenszyklusüberwachung |
| `Seiger\sTask\Services\MetricsService` | Statistik/Cache-Metriken |

## Ausnahmen

- `WorkerNotFoundException`;
- `WorkerClassNotFoundException`;
- `WorkerInvalidInterfaceException`.

Jede Auflösungsausnahme hat eine Kontextnutzlast für das Logging. Manager/API-Schicht kann sie in JSON-Nachrichten umwandeln; Zeigen Sie den Stack Trace Endbenutzer nicht an.

## Formatierungshilfen in der Benutzeroberfläche

- `niceCount(int|float)` — kompakte Zählung;
- `niceSize(bytes)` — lesbare Größe;
- `niceEta(seconds)` — Dauer/ETA/Betriebszeit.

Das sind Evolution/evo-UI-Runtime-Helfer, keine sTask-Fassade.

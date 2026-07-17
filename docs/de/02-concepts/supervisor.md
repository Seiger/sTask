# Prozessleiter

Der Supervisor-Zeitplan ist für losgelöste Langzeitprozesse konzipiert: Listener, Transport-Daemon oder andere von Arbeitern betriebene Laufzeit. sTask weiß nicht, wie man die PID oder den Herzschlag eines bestimmten Prozesses überprüft; Dies wird von Adapter in Worker Class durchgeführt.

## Vertrag

Worker bleibt gleichzeitig ein regelmäßiger `TaskInterface`/`BaseWorker` und fügt hinzu:

```php
use Seiger\sTask\Contracts\SupervisorWorkerInterface;
use Seiger\sTask\Support\SupervisorStatus;

interface SupervisorWorkerInterface
{
    public function supervisorKey(): string;
    public function inspectSupervisor(): SupervisorStatus;
    public function startSupervisor(): SupervisorStatus;
    public function restartSupervisor(): SupervisorStatus;
    public function supervisorStartupGraceSeconds(): int;
}
```

`inspectSupervisor()` sollte die Laufzeit nicht ändern. `startSupervisor()` und `restartSupervisor()` sollten nach dem abgetrennten Start schnell zurückkehren; Sie müssen nicht warten, bis der Daemon fertig ist.

## Aufseherstatus

```php
return new SupervisorStatus(
    state: SupervisorState::Healthy,
    pid: 18422,
    heartbeatAt: now()->subSeconds(5),
    startedAt: now()->subHours(3),
    uptimeSeconds: 10800,
    message: 'Listener is receiving updates',
    fingerprint: 'listener:healthy:v1',
);
```

Felder:

- `state` — `healthy`, `starting`, `degraded`, `failed`, `stopped`;
- `pid` — Informations-PID, nullierbar;
- `heartbeatAt` — die letzte Lebensbestätigung;
- `startedAt` und `uptimeSeconds` — Prozessstart-/Betriebszeit, die die Benutzeroberfläche anzeigt;
- `message` — Diagnosen, die für den Manager sicher sind;
- `fingerprint` ist ein stabiler Adapter-Fingerabdruck. Wenn nicht angegeben, hasht sTask Status + Nachricht.

Fügen Sie keine Nachrichten-/Fingerabdruck-Token, Sitzungsgeheimnisse oder vollständige Kommandozeilen mit Zugangsdaten ein.

## Ein Scheduler-Pass

1. sTask erhält ein nicht-leeres `supervisorKey()`.
2. Formen `key_hash = sha256(worker_id + ':' + supervisor_key)`.
3. Benötigt eine Datei-Cache-Sperre `stask:supervisor:{key_hash}` für 55 Sekunden.
4. Verursacht eine Nur-Lese-Inspektion.
5. Wenn Zustand `stopped`, `degraded` oder `failed` und die Start-Gnade abgelaufen ist, ruft es Start/Neustart auf.
6. Erneuert `s_supervisor_states`.
7. Erstellt eine letzte Aufgabenzeile nur für sinnvolles Ereignis: gestartet, neu gestartet, wiederhergestellt, fehlgeschlagen, gestoppt, Zustand/Diagnoseänderung.

Gesunder Schnappschuss mit demselben Fingerabdruck aktualisiert nur `last_seen_at`, Herzschlag/Verfügbarkeit und `repeat_count`; Eine neue Aufgabe wird nicht jede Minute erstellt.

## Start-Grace

`launch_requested_at` erinnert sich an die letzte Startanfrage. Bis `max(1, supervisorStartupGraceSeconds())` vorbei ist, wird Neustart/Neustart unterdrückt. Nach dem gesunden Status wird das Feld geräumt.

Grace beweist nicht, dass der Prozess begonnen hat. Der Adapter muss korrekt unterscheiden zwischen `starting`, `healthy`, `degraded`, `failed` `stopped` anhand von PID/Herzschlag/Laufzeit.

## Übergänge und Ereignisaufgaben

| Beobachtung | Aktion | Ereignisaufgabe |
| --- | --- | --- |
| Erstes `stopped` | `startSupervisor()` | `started`, oder `failed/stopped` nach Rückgabestatus |
| Bestehende `degraded/failed` | `restartSupervisor()` | `restarted`, oder Scheitern |
| `starting` in Gnade | Start wiederholt sich nicht | nur beim Wechsel von Zustand/Fingerabdruck |
| Ungesunde → `healthy` | Zustand bleibt bestehen | `recovered` |
| Zustand geändert | Zustand bleibt bestehen | Ereignis mit dem neuen Staat |
| Fingerabdruck hat sich verändert | Diagnostische Persistenz | `diagnostic_changed` |
| gesund unverändert | Cursor-Aktualisierung | Keine Aufgabe |

Eine Ereignisaufgabe hat eine Aktion `supervisor`, `started_by = 0`, `max_attempts = 1`, Zeitstempel, `now()`. Fehlgeschlagen/degradiert/gestoppt wurde zum Aufgabenstatus fehlgeschlagen; Andere Ereignisse – erledigt.

## Arbeiter-Tab

Für den Vorgesetztenplan zeigt die Tabelle einen Chip mit `activity-heartbeat`. Wenn der Staat gesund und `uptime_seconds` bekannt ist, zeigt das Abzeichen die formatierte Uptime über `niceEta()` statt des Wortes "Works" an. Modal zeigt Schlüssel, Zustand, PID, Herzschlag, Betriebszeit, letzte Diagnose und Übergangszeit.

## Tabellenzustand und Wachstum

`key_hash` einzigartig, daher wird eine Zeile `worker_id + supervisor_key` für dasselbe Paar aktualisiert. Ein normaler Herzschlag erzeugt keine historischen Reihen. Die Tabelle wächst nur, wenn neue Worker-IDs/Schlüssel erscheinen; Es gibt keinen automatischen Rückschnitt.

Nach der Deinstallation oder Neuinstallation von Workers werden Orphan State Rows nicht mit einem Fremdschlüssel gelöscht, da das Schema kein FK/Cascade erzeugt. Führen Sie eine routinemäßige Reinigung erst ein, nachdem Sie überprüft haben, dass der Arbeiter/Schlüssel nicht mehr existiert.

## Betriebscheckliste

- Start abgetrennt und rasch zurückkehrend;
- Supervisor-Schlüssel ist stabil und nicht leer;
- Herzschlag hat eine klare Auszeit;
- Die PID wird zusammen mit der Prozessidentität überprüft, nicht nur `posix_kill($pid, 0)`;
- Idempotent starten/neu starten;
- Grace ist länger als eine typische Startzeit;
- externe systemd/Supervisor steht nicht in Konflikt mit der Neustartrichtlinie des Adapters;
- Logs und State enthalten keine Geheimnisse.

# Diagnostik

Beginnen Sie mit Nachweisen: Paketreferenz, Routen, Migrationen, Scheduler, Datenbankzeile, Fortschrittsdatei, Anwendungsprotokoll. Schließen Sie nicht nur mit dem UI-Abzeichen ab.

## sTask ist im Dokumentationsmodul nicht sichtbar

1. Überprüfen Sie das physische Artefakt:

   ```bash
   test -f core/vendor/seiger/stask/docs/uk/README.md
   ```

2. Überprüfen Sie das Schloss bzw. die Quelle:

   ```bash
   cd core
   composer show seiger/stask --all
   ```

3. Stelle sicher, dass die dDocs `scan_vendor_packages = 1` haben.
4. Index-/Anwendungscache leeren.
5. Überprüfen Sie die Paketmetadaten in `lang/{locale}/global.php`: `module_title`, `module_description`, `module_icon`.

dDocs scannt automatisch Pakete `seiger/*`. Füge kein Vendor Root zu `extra_docs_roots` hinzu: Die konfigurierte Projektwurzel kann im Viewer beschreibbar werden.

Wenn Git-Repo-Dokumente und `core/vendor` nicht, liegt das Problem beim Composer Lock/Dist/Deploy, nicht beim Markdown-Index.

## dDocs stürzt zur Indexierung ab

Überprüfen Sie die Stack-Trace des Drittanbieter-Dokumentationspakets. Die Locale-Alias-Datei, der Dienstanbieter oder der Cache können abstürzen, bevor der sTask-Quellcode gelesen wird. Dies ist eine separate defekte dDocs/Umgebung; sTask-Dokumentationen können den Bootstrap von jemand anderem nicht reparieren.

## Das sTask-Modul ist nicht sichtbar

- `composer show seiger/stask`;
- der Anbieter ist in der Paketerkennung vorhanden;
- Erlaubnis `stask` zugewiesene Rolle;
- Migrationen sind vorbeigegangen;
- Manager-Cache wird gelöscht;
- Modul-/Plugin-Registrierung erfolgt durch den Evolution CMS-Installer.

Der Anbieter verfügt über eine Manager-Registrierungsmethode, aber der eigentliche Moduleintrag kann auch über einen Evolution-Paket-Installer/Plugin verwaltet werden. Überprüfen Sie die Datenbank-Modul-Eintrags- und Paketerkennung, anstatt die geschützte Methode manuell aufzurufen.

## Modul ohne Stile oder JavaScript

```bash
cd core
php artisan stask:publish
php artisan cache:clear-full
```

Im Browser-Netzwerk prüfen Sie `stask-module.css`, `stask-module.js`, `stask.min.css`. Wenn Deploy das Readonly-Dateisystem verwendet, müssen Assets zum Build-Zeitpunkt veröffentlicht werden.

## `stask:worker` fängt nicht an

```bash
php -v
php artisan list | grep stask
php artisan route:list --path=stask
```

Das Paket benötigt 8,4 PHP. Überprüfen Sie, ob CLI und FPM dieselbe Version, `.env`, Erweiterungen und Berechtigungen verwenden.

## Aufgabe in der Warteschlange und nicht ausgeführt

Abreise:

```sql
SELECT id, identifier, action, status, start_at, created_at
FROM s_tasks
WHERE status IN (10, 30, 50)
ORDER BY id;
```

- Cron tatsächlich hingerichtet wird;
- `start_at` nicht in der Zukunft;
- aktiver Arbeitnehmer;
- Klasse existiert und implementiert `TaskInterface`;
- Anwendungsprotokoll enthält keine Auflösungsausnahme;
- es gibt kein äußeres Schloss, das ständig besetzt ist.

## Der Zeitplan erstellt keine Aufgabe

- `settings.schedule.enabled = true`;
- Typ nicht `manual`;
- Betonarbeiter hat `taskMake()`;
- es gibt keine unvollständige Aufgabe dieser Identifikator;
- Date-Time einmal in der Zukunft;
- wöchentlich hat `days`;
- Reguläres Fenster gültig und nicht über Nacht;
- `stask:worker` vergeht jede Minute.

## Arbeiter erscheint nicht

```bash
composer dump-autoload
php artisan package:discover
```

Dann erneuere die Registrierung. Die Klasse muss konkret sein und in der Composer-Klassenkarte stehen. Die PSR-4-Klasse, die Composer in der Klassenkarte nicht optimiert hat, kann von der aktuellen Discovery-Implementierung für den autoritativen/optimierten Dump nicht gefunden werden.

Prüfen Sie `config/excluded_namespaces.php`: Große Framework-Namensräume werden absichtlich weggelassen.

## `WorkerClassNotFound` / `WorkerInvalidInterface`

- Klassenname in `s_workers.class` korrekt;
- Autoload ist aktuell;
- Klasse nicht abstrakt;
- Klasse implementiert `TaskInterface`;
- Der Konstruktor stürzt nicht ab, weil die Datenbank oder die Einstellungen abhängen.

Nicht auf 'Sauber' verwaist während partieller Bereitstellung klicken: Der Datensatz kann gelöscht werden, solange die Klasse vorübergehend nicht verfügbar ist.

## Live-Fortschritt 404

404 bedeutet, `storage/stask/{id}.log` nicht gefunden. Die Aufgabe kann weiterhin in der Warteschlange sein oder der Schreib kann stillschweigend fehlgeschlagen sein.

```bash
ls -la core/storage/stask
```

Vergleiche Nutzer/Gruppe für FPM und Cron. Siehe Datenbankstatus/-nachricht und Anwendungsprotokoll.

## Fortschritt eingefroren, Aufgabe erledigt

Die Fortschrittsdatei ist nur anhängend und ist keine Wahrheitsquelle für den endgültigen Datenbankstatus. Der Live-Watcher bleibt an der Endlinie auf der letzten Linie stehen. Wenn der benutzerdefinierte Worker die Datenbank finalisiert hat, aber nicht aufgerufen hat `markFinished()` den finalen Snapshot nicht aufgezeichnet hat, wird die Benutzeroberfläche nach der Livewire-Aktualisierung aktualisiert, aber die Datei kann weiterhin laufen.

## Notstopp stoppte den Prozess nicht

Dies ist die erwartete Grenze: Die Aktion setzt nur den DB-Datensatz in 'failed'. Finde den Prozess mit Infrastruktur-Telemetrie, stoppe ihn normal, prüfe die Nebenwirkungen und führe dann eine neue Aufgabe aus.

## Supervisor-Neustartschleife

- Startup Grace ist zu klein;
- Die Inspektion erkennt das Anfahren nicht an;
- Herzschlag-Timeout ist kürzer als die reelle Frequenz;
- Fingerabdruckänderungen bei jedem Durchgang mittels Zeitstempel/zufälligem Text;
- Der Prozess wird außerdem systemtechnisch neu gestartet;
- Start/Neustart ist nicht abgetrennt.

Der Fingerabdruck muss für dieselbe Diagnose stabil sein.

## Supervisor-Staat wächst

```sql
SELECT worker_id, identifier, supervisor_key, COUNT(*)
FROM s_supervisor_states
GROUP BY worker_id, identifier, supervisor_key;
```

Eindeutige `key_hash` verhindert Wachstum desselben Paars, aber eine neue Worker-ID oder ein neuer Variablenschlüssel erzeugt eine neue Zeile. Die Schlüsselstabilität auf die Reinigung reparieren.

## Statistiken zeigen null Dauer/Erinnerung

Die aktuelle Aggregationsimplementierung liefert für diese Metriken Platzhalter-Null. Das bedeutet nicht null Konsum. Verwenden Sie die Aufgabendauer in Logs und externen APM.

## ArtisanWorker blockiert den Befehl

Prüfen Sie `config/artisan_security.php`:

- gefährliche Befehle sind verboten;
- Bestätigungspflicht erfordert `confirm=true`;
- Whitelist, sofern nicht leer, erlaubt sie nur aufgeführte Muster;
- Blacklist blockiert zusätzliche Befehle;
- Erlaubnis `run_artisan` erforderlich.

Deaktivieren Sie keine Sicherheitskontrollen in der Produktion.

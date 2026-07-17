# Produktionsempfehlungen

## Prozessmodell

`stask:worker` – Befehl zum Laufen und Verlassen. Es erstellt geplante Aufgaben, verarbeitet vorgefertigte Warteschlangenzeilen nacheinander und beendet die Reihen. Für die laufende Wartung lassen Sie den Laravel-Scheduler jede Minute laufen.

```cron
* * * * * cd /var/www/example/core && /usr/bin/flock -n /run/lock/example-stask.lock /usr/bin/php artisan schedule:run >> /var/log/example-scheduler.log 2>&1
```

`flock` — Infrastruktur-Guard von Overlap. Wählen Sie den schreibbaren Sperrpfad und prüfen Sie, ob eine lange Aufgabe keine kritischen, nicht zusammenhängenden Scheduler-Jobs blockiert. Eine Alternative ist eine separate System-Einheit/Timer mit `RefuseManualStart`/locking policy.

## Eigentümer und Rechte

Webnutzer und CLI-Nutzer müssen kompatible Rechte haben:

- `core/storage/stask`;
- Laravel-Cache/Speicher;
- Ergebnis-Upload-Verzeichnis;
- Anwendungsressourcen, die der Worker ändert.

Führe Cron nicht unnötig vom Root aus: Root-erstellte Fortschritts-/Cache-Dateien unterbrechen oft den Manager-Prozess.

## Auszeit

Der Manager-Laufpfad kann `set_time_limit(0)` aufrufen, und der CLI-Befehl setzt keinen Timeout pro Aufgabe. Timeouts müssen im Arbeiter sein:

- HTTP-Verbindung/Lese-Timeout;
- DB-Statement-Timeout;
- maximale Items/Chargen pro Aufgabe;
- Deadline in Metadaten;
- elegante Abstornierungskontrollpunkte.

Zerlege lange Aufträge in idempotente Abschnitte. Eine monolithische Aufgabe blockiert den nächsten Zeitplan desselben Arbeiter-Identifikators.

## Wettbewerbsfähigkeit

Die aktuelle CLI wählt Warteschlange ohne atomare Claim Query/`FOR UPDATE SKIP LOCKED` aus. Daher:

- eine `stask:worker` pro Installation zu behalten;
- Verwendung externer Überlappungssperre;
- die Aktion idempotent zu machen;
- Für kritische Integrationen verwenden Sie den Domänenebene-Idempotenzschlüssel;
- Verwechseln Sie keine doppelte Suche mit transaktionaler Garantie.

Wenn Nebenläufigkeit erforderlich ist, entwerfen Sie zunächst einen Claim/Lease-Vertrag; Allein die Erhöhung der Anzahl der Prozesse ist gefährlich.

## Versuch es erneut und zieh dich zurück

`attempts/max_attempts` führen keine Neuversuche alleine durch. Die Produktionspolitik sollte festlegen:

- wiederholbare Ausnahmeklassen/Statuscodes;
- maximale Versuche;
- `start_at` für Backoff;
- Dead-Letter/Handbuch-Überprüfung;
- Duplikat-/Idempotenzverhalten;
- Alarm nach dem letzten Fehler.

## Überwachung

Mindestprüfungen:

- Scheduler Herzschlag/Letzter erfolgreicher Start;
- die Anzahl der in der Warteschlange stehenden Aufgaben und das Alter des ältesten;
- laufende Aufgabenalter;
- Durchfallquote;
- Schreibbarkeit `storage/stask`;
- Fortschritt/Ergebnisse der Festplattennutzung;
- Klassenvermisste/inaktive Arbeiter;
- `s_supervisor_states.last_seen_at` und Herzschlag-Frische;
- Dämonen-PID-Identität.

Der integrierte Statistik-Tab ersetzt APM: duration/memory/common-errors nicht durch teilweise Platzhalter.

## Stämme

Es gibt drei verschiedene Quellen:

1. `s_tasks.message/meta/result` – anhaltende Prüfung/Staat.
2. `storage/stask/{id}.log` – Live-Anpassungsfortschritt.
3. Anwendungsprotokolle über Laravel `Log` – Ausnahmen, Entdeckung, Startwarnungen.

Definieren Sie die Bindung individuell. `cleanOldTasks()` löscht nur alte, fertige Datenbankzeilen; Fortschritt `.log`, fehlgeschlagene Aufgaben und der Status des Vorgesetzten sind nicht freigegeben.

## Retention

Ein Beispiel für eine Richtlinie, die Sie in Ihrer Betriebsschicht implementieren können:

- abgeschlossene Aufgaben: 30–90 Tage;
- fehlgeschlagene Aufgaben: länger oder vor der Vorfallüberprüfung;
- Fortschrittsprotokolle: 7–30 Tage nach der letzten Aufgabe;
- Upload/Ergebnisse: für Geschäfts-/Rechtspolitik;
- Supervisor-Zustand: eine tatsächliche Reihe pro Schlüssel; Waisenreihen – nach der Inventarkontrolle.

Löschen Sie den aktiven Aufgabenfortschritt nicht. Überprüfen Sie vor der Reinigung den Endstatus und den Eigentum der Datei.

## Supervisor/Systemd

sTask Supervisor ist ein lebenszyklus-Adapter auf Anwendungsebene, kein vollständiger Ersatz für systemd/Supervisor. Wenn der Prozess systemd und sTask-Adapter gleichzeitig neu startet, vereinbaren Sie sich auf einen einzelnen Besitzer, sonst sind Neustartschleifen möglich.

Empfohlene Rollen:

- SystemD stellt Start-, Benutzer-, Ressourcenbegrenzungen und Absturz-Neustarts bereit;
- Worker Adapter liest Health/Heartbeat und gibt einen Diagnosezustand zurück;
- nur einer von ihnen einen Neustart durchführt oder beide einen gemeinsamen Backoff/Lock-Vertrag haben.

## Setzen Sie es auf

Sichere Abfolge:

1. die Schaffung neuer Arbeitsplätze zu stoppen oder auf die Fertigstellung kritischer zu warten;
2. `composer install` mit Lock;
3. `php artisan migrate --force` nach Backup- und Migrationsprüfung;
4. `package:discover`/Autoload-Wiederaufbau;
5. `stask:publish`;
6. `cache:clear-full`;
7. Aktualisieren Sie das Arbeiterregister nach einem ganzheitlichen Einsatz;
8. Rauchen `stask:worker`;
9. Überprüfe die Benutzeroberfläche und den Scheduler der Manager.

Führe keine Registrierungsreinigung während der Bereitstellung durch, wenn Klassen vorübergehend fehlen.

## Sicherheit

- Erlaubnis `stask` nur operative Aufgaben;
- `run_artisan` separat für ArtisanWorker;
- Whitelist/Blacklist gefährlicher Befehle;
- CSRF für POST;
- Geheimnisse, die nicht im Meta/Ergebnis/Nachrichten/Fortschritt sind;
- Uploads in nicht-öffentlichen Speicher;
- Arbeiter validieren Eingaben und Autorisierungen, auch wenn sie nur vom Routenmanager genutzt werden;
- Dienstkonto mit minimalen Dateisystem-/Datenbankrechten.

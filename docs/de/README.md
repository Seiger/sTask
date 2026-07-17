# sTask 2.x

sTask ist ein Evolution-CMS-Paket zur Verwaltung von Hintergrundaufgaben. Es speichert die Warteschlange in der Datenbank, findet Worker über Composer, führt sie mit dem Befehl `stask:worker` aus, zeigt Fortschritt und Logs im Manager an und überwacht separat langlebige Supervisor-Prozesse.

Die Dokumentation beschreibt den aktuellen Zweig `2.x`. Sie setzt weder eine externe Queue noch SSE oder WebSocket voraus: Der Live-Fortschritt im Manager wird durch periodische HTTP-Anfragen aus den Dateiprotokollen `storage/stask/{taskId}.log` gelesen.

## Für wen diese Dokumentation gedacht ist

- **Administrator** — Installation, Berechtigungen, Cron, Manager-Tabs, Diagnose und Produktionsbetrieb.
- **Integrator** — Zeitpläne, Worker-Registrierung, Manager-Routen, Migrationen und Aktualisierungen.
- **PHP-Entwickler** — `TaskInterface`, `BaseWorker`, die Fassade `sTask`, Fortschritts-API und Supervisor-Vertrag.

## Dokumentationskarte

1. Einstieg
   - [Anforderungen, Installation und Aktualisierungen](01-getting-started/installation.md)
   - [Schneller Start](01-getting-started/quick-start.md)
2. Konzepte
   - [Architektur und Lebenszyklus](02-concepts/architecture-and-lifecycle.md)
   - [Spielpläne](02-concepts/schedules.md)
   - [Prozessleiter](02-concepts/supervisor.md)
3. Evolution CMS Manager
   - [Panel, Aufgaben, Arbeiter, Logbücher und Statistiken](03-manager/interface.md)
4. Entwicklung
   - [Facade und PHP-API](04-development/public-api.md)
   - [Eigener Arbeiter](04-development/custom-worker.md)
   - [Routen, Fortschrittsdateien und Downloads](04-development/routes-and-progress.md)
5. Betrieb
   - [Produktionsempfehlungen](05-operations/production.md)
   - [Diagnostik](05-operations/troubleshooting.md)
   - [Übergang von 1.x zu 2.x](05-operations/upgrade-1-to-2.md)
6. Verzeichnis
   - [Konfiguration](06-reference/configuration.md)
   - [Datenbanktabellen](06-reference/database.md)
   - [CLI, Status und Routen](06-reference/cli-statuses-routes.md)
   - [FAQ](06-reference/faq.md)

## Haftungsgrenzen

sTask führt den Worker sequentiell im Prozess `stask:worker` aus. Das Paket bietet keine exakt einmalige, verteilte Broker-Garantie, keine automatische Beendigung des OS-Prozesses mit Notstopp-Knopf oder Speicherung aller Fortschrittsmeldungen in der Datenbank. Solche Anforderungen werden vom Anwendungsarbeiter und der Projektinfrastruktur umgesetzt.

## Erste Kontrolle

Nach der Installation führen Sie Folgendes durch:

```bash
cd core
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan stask:worker
```

Erwartetes Ergebnis: Migrationen wurden `s_workers`, `s_tasks` und `s_supervisor_states` erstellt, Batch-Assets wurden veröffentlicht, und das Worker-Team endete mit einer Nachricht über die Anzahl der erstellten und verarbeiteten Aufgaben. Öffne als Nächstes das **sTask**-Modul im Manager.

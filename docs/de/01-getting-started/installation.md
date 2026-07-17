# Anforderungen, Installation und Aktualisierungen

## Anforderungen

Der aktuelle 2.x-Zweig `composer.json` erfordert:

| Komponente | Anforderung |
| --- |----------------------------|
| PHP | <code>&#94;8.4</code> |
| Evolution CMS | <code>&#94;3.5.7</code> |
| evo-ui | <code>&#94;1.0.6</code> |
| Komponist | im Katalog verfügbar `core` |

Die HTML-Entität für caret wird absichtlich verwendet: dDocs wandelt sie nicht in Superscript um und zeigt die genaue Composer-Einschränkung an.

Die automatische Warteschlangenbearbeitung erfordert einen Systemcron oder einen anderen Scheduler, der den Laravel-Scheduler jede Minute ausführt. Um die Aufgabe sofort aus der Benutzeroberfläche auszuführen, muss der PHP-Prozess auch Zugriff auf `exec()` oder `shell_exec()` haben; wenn sie nicht erlaubt sind, wird der Eintrag in der Warteschlange dennoch erstellt und vom nächsten Cron-Pass verarbeitet.

## Installation über Komponist

Befehle werden aus dem `core` Evolution CMS-Verzeichnis ausgeführt:

```bash
cd /var/www/example/core
composer require seiger/stask:"2.x-dev"
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

Was passieren sollte:

1. Laravel-Paketentdeckung verbindet `Seiger\sTask\sTaskServiceProvider` und Alias `sTask`.
2. `migrate` löst Batch-Migrationen aus, die der Anbieter über `loadMigrationsFrom()` hinzugefügt hat.
3. `package:discover` aktualisiert das Manifest zur Entdeckung des Laravel-Pakets; Composer Classmap wird während der Installation/Aktualisierung oder `composer dump-autoload` erstellt.
4. `stask:publish` kopiert CSS, JavaScript und SVG in `assets/site`.
5. Eine vollständige Cache-Bereinigung entfernt die alten Manager-Ansichten, Routen und Paketmetadaten.

Dateien in `core/vendor/seiger/stask` nicht bearbeiten: Composer wird sie während des Updates ersetzen.

## Dienstanbieter

Anbieter automatisch:

- registriert Singleton `Seiger\sTask\sTask` und Alias `sTask`;
- Register `WorkerService`, `MetricsService`, `SupervisorService`;
- lädt Migrationen, Übersetzungen, Blade-Ansichten, Manager-Routen und Livewire-Komponenten;
- Tabellenpräsets `stask.tasks`, `stask.workers`, `stask.logs` verbindet;
- erstellt `storage/stask`, wenn es noch kein Verzeichnis gibt;
- registriert `stask:worker` und `stask:publish` in der CLI;
- fügt dem Laravel-Scheduler mit einer Frequenz von einmal pro Minute `stask:worker` hinzu.

Das Manager-Menü fügt dem `evolution.OnManagerMenuPrerender`-Event nur dann ein Batch-Plugin hinzu, `plugins/sTaskPlugin.php` wenn der Benutzer die Berechtigung `stask` hat. Sie führt auf benannter Route `sTask.index` und nutzt `sTaskServiceProvider::MODULE_ICON`. Die `module/sTaskModule.php` -Datei ist ein geschützter Wrapper für den Evolution-Modul-Eintrag und rendert denselben Controller. Die geschützte Methode `registerManagerModule()` im Provider für den Installations-/Modulfluss vorhanden, ruft sie aber nicht direkt `boot()` auf; Verlassen Sie sich nicht darauf, diese Methode manuell aufzurufen.

## Migrationen und Erlaubnis

Das Paket erzeugt drei Tabellen: `s_workers`, `s_tasks`, `s_supervisor_states`. Eine separate idempotente Migration erstellt eine Berechtigungsgruppe `sTask`, Berechtigungsschlüssel `stask` und fügt ihre Rolle hinzu, `1` ob die entsprechenden Systemtabellen existieren.

Check:

```bash
php artisan migrate:status
php artisan route:list --path=stask
```

Im Manager benötigt der Benutzer die Berechtigung `stask`. Manager-Routen werden zusätzlich durch die Middleware-Gruppe `mgr`; geschützt; es handelt sich nicht um eine öffentliche HTTP-API.

## Poste Assets

Team:

```bash
php artisan stask:publish
```

veröffentlicht insbesondere:

- `assets/site/stask.svg`;
- `assets/site/stask.min.css`;
- `assets/site/stask-module.css`;
- `assets/site/stask-module.js`;
- `assets/site/stask.js`;
- `assets/site/seigerit.tooltip.js`.

Wenn das Modul ohne Styles öffnet oder der Live-Fortschritt nicht aktualisiert wird, wiederhole zuerst Publish und `cache:clear-full`, dann prüfe HTTP 200 für diese Assets.

## Cron

Empfohlene Produktionsaufnahme:

```cron
* * * * * cd /var/www/example/core && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Überprüfen Sie die absoluten Wege:

```bash
command -v php
cd /var/www/example/core && php artisan schedule:list
cd /var/www/example/core && php artisan stask:worker
```

Führen Sie nicht mehrere unkontrollierte Cron-Einträge für dieselbe Installation durch. Reguläre Aufgaben verfügen nicht über eine globale Claim-Lock, sodass parallele `stask:worker` ein Risiko einer wettbewerbsfähigen Ausführung schaffen kann.

## Update 2.x

```bash
cd /var/www/example/core
composer update seiger/stask --with-dependencies
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

Nach der Aktualisierung überprüfen Sie die tatsächliche Quelle:

```bash
composer show seiger/stask --all
```

Wenn die Batch-Dokumentation nicht in dDocs erscheint, überprüfe nicht nur `docs` im Git-Repository, sondern auch im physischen Verzeichnis `core/vendor/seiger/stask/docs/uk`. Composer Lock/Dist kann auf dem alten Commit bleiben.

## Rollback

Vor dem Upgrade machen Sie eine Sicherungskopie der Datenbank und `core/composer.lock`. Rolle den Code mit Composer auf eine verifizierte Referenz zurück. Rollt das Datenbankschema nur nach einem separaten Plan zurück, nachdem die tatsächlichen Migrationen in der installierten Version des Pakets und die Konsequenzen für Produktionsdaten überprüft wurden.

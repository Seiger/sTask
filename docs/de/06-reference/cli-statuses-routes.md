# Referenz

Diese Seite nennt stabile Paketsignale fuer dDocs, Integrationen und
Migration-Agents.

## Service Provider

`Seiger\sTask\sTaskServiceProvider` registriert das Evolution Manager-Modul,
Routen, Migrationen, Views, Uebersetzungen, die Livewire-Komponente, den
Console-Befehl und EvoUI Tabellen-Presets.

| Signal | Zweck |
| --- | --- |
| `cms.settings` | Manager-Einstellungen aus `config/sTaskCheck.php`. |
| `stask.tasks.table` | EvoUI Task-Tabellenpreset aus `config/tasks/table.php`. |
| `stask.workers.table` | EvoUI Worker-Tabellenpreset aus `config/workers/table.php`. |
| `stask.logs.table` | EvoUI Log-Tabellenpreset aus `config/logs/table.php`. |
| `tasks.table` | Publishable Task-Tabellenkonfiguration. |
| `workers.table` | Publishable Worker-Tabellenkonfiguration. |
| `logs.table` | Publishable Log-Tabellenkonfiguration. |
| `sTaskAlias` | Alias der package plugin bridge. |
| `sTaskCheck` | Quelle der Modulregistrierungs-Einstellungen. |

## Runtime Classes

| Class | Responsibility |
| --- | --- |
| `DashboardData` | Dashboard-Karten, letzte Tasks und letzte Fehler. |
| `MetricsService` | Runtime-Metriken und Statistikdaten. |
| `PublishAssets` | Publiziert Manager-Assets des Pakets und haelt EvoUI-facing Dateien aktuell. |
| `ModulePanel` | Livewire EvoUI Manager-Panel. |
| `TasksTableData` | Datenquelle fuer Task-Tabelle/Liste. |
| `WorkersTableData` | Datenquelle fuer Worker-Tabelle/Liste. |
| `LogsTableData` | Datenquelle fuer Logs und Task-Details. |
| `TaskProgress` | Normalisiert Fortschritt und Nachrichten. |
| `WorkerDiscovery` | Findet Worker-Klassen und beachtet namespace exclusions. |
| `WorkerService` | Erstellt und steuert Worker-Tasks. |
| `BaseWorker` | Basisklasse fuer custom worker. |
| `ArtisanWorker` | Built-in worker fuer erlaubte Artisan commands. |
| `ComposerUpdateWorker` | Built-in worker fuer Composer maintenance. |
| `TaskWorker` | Console worker fuer die Task queue. |

## Exceptions

| Exception | Meaning |
| --- | --- |
| `WorkerNotFoundException` | Worker identifier wurde nicht gefunden. |
| `WorkerClassNotFoundException` | Worker class kann nicht geladen werden. |
| `WorkerInvalidInterfaceException` | Class erfuellt den worker contract nicht. |

## Worker Contract

Worker deklarieren `identifier()`, `scope()`, `title()`, `description()` und
`settings()`. Die Map `handles` beschreibt Aktionen. Discovery kann Namespaces
ueber `excluded_namespaces` auslassen.

## Console Commands

```console
php artisan stask:worker
php artisan stask:publish
php artisan stask:publish --no-prune
```

Die volle Publish-Signatur ist
`stask:publish {--no-prune : Do not delete existing files before publish}`.

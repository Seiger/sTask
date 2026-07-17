# Konfiguration

## `config/sTaskCheck.php`

Verschmilzt in `cms.settings`:

| Schlüssel | Standard | Bedeutung |
| --- | --- | --- |
| `check_sTask` | `true` | Paketpräsenz/Kontrollflagge |
| `sTaskVer` | `2.9999999.9999999.9999999-dev` | Entwicklungszweig Versionsmarker |

Das ist kein Worker-Runtime-Tuning.

## Tabellenvoreinstellungen

| Datei | Konfigurationsschlüssel | Oberfläche |
| --- | --- | --- |
| `config/tasks/table.php` | `stask.tasks.table` | Aufgaben |
| `config/workers/table.php` | `stask.workers.table` | Arbeiter |
| `config/logs/table.php` | `stask.logs.table` | Logs |

Presets definieren Provider, Drahtmethoden, Paginierung, Ansichten, Filter, Spalten, Modal und Aktionen. Für die Projektüberschreibung verwenden Sie den benutzerdefinierten Evolution-Konfigurationsmechanismus oder den Publish/Extension Point, falls dieser von Ihrer Version unterstützt wird; Bearbeite keine Händlerdateien.

## `config/excluded_namespaces.php`

Liste der Namensraumpräfixe, die `WorkerDiscovery` nicht berücksichtigt. Standardmäßig sind Framework-/Anbieterbereiche wie `Illuminate\`, `Symfony\`, `EvolutionCMS\Legacy\`, `PHPUnit\` usw. ausgeschlossen.

Wenn dein Worker unter dem ausgeschlossenen Präfix steht, verschiebe ihn in den Package-/Projektnamensraum. Verkürze die Liste nicht ohne Analyse: Discovery kann Tausende von Drittanbieter-Klassen instanziieren.

## `config/artisan_security.php`

Verwendet `ArtisanWorker`:

| Schlüssel | Standard |
| --- | --- |
| `dangerous_commands` | `migrate:fresh`, `migrate:reset`, `db:wipe` |
| `confirmation_required` | `migrate`, `migrate:refresh`, `migrate:rollback`, `db:seed`, `cache:clear-full` |
| `whitelist` | leer: alle außer blockiert |
| `blacklist` | leer |
| `enabled` | `true` |
| `log_executions` | `true` |
| `required_permission` | `run_artisan` |

Whitelist-Muster unterstützen `*` für Vertragskommentare. In der Produktion sollte die Sicherheit aktiviert bleiben und eine explizite Whitelist für operative Anforderungen erstellt werden.

## Arbeitereinstellungen

Gespeichert in `s_workers.settings` JSON:

```json
{
  "schedule": {
    "enabled": true,
    "type": "periodic",
    "datetime": "",
    "frequency": "hourly",
    "time": "*:10",
    "start_time": "",
    "end_time": ""
  },
  "http": {
    "timeout": 15
  }
}
```

`BaseWorker` API:

```php
$worker->settings();
$worker->getConfig('http.timeout', 10);
$worker->setConfig('http.timeout', 20);
$worker->updateConfig(['endpoint' => 'https://example.test']);
$worker->getSchedule();
$worker->shouldRunNow();
```

`TaskWorker` berechnet unabhängig den nächsten Durchlauf; `shouldRunNow()` ist ein Mitarbeiter auf der Arbeitsseite und nicht die Hauptentscheidung des Schedulers in der CLI.

## Servicecontainer

Einzelpersonen:

```php
app(Seiger\sTask\sTask::class);
app(Seiger\sTask\Services\WorkerService::class);
app(Seiger\sTask\Services\MetricsService::class);
app(Seiger\sTask\Services\SupervisorService::class);
```

Fassadenaccessoire: `sTask`.

## Lagerung

| Weg | Daten |
| --- | --- |
| `core/storage/stask/{id}.log` | Nur anhänglicher Fortschritt |
| `core/storage/stask/uploads` | Upload/Ergebnisdateien Controller/Worker |
| Laravel-Cache | Mitarbeiterinstanzen, Metriken, Vorgesetztensperren |

Der Anbieter erstellt nur Root- `storage/stask`. Unterverzeichnisse werden durch den entsprechenden Codepfad erstellt.

## Paket-Metadaten für dDocs

dDocs lautet:

- Komponistenname `seiger/stask`;
- lokalisierte `lang/{locale}/global.php` Schlüssel `module_title`, `module_description`, `module_icon`;
- `docs/{locale}`.

Erwartete Metadaten:

```php
'module_title' => 'sTask',
'module_icon' => 'tabler-progress-check',
```

`docs/docs.json` ist ein tragbares Inventarmanifest. Die aktuelle dDocs-Laufzeit kann Manifest nicht direkt verwenden; Discoverability bietet einen Composer-Paket-Scan und einen physisch lokalisierten Dokumentationsbaum.

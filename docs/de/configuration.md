# Konfiguration

Diese Seite beschreibt Installation, Manager-Registrierung, Tabellen-Presets,
Befehle und Worker Discovery fuer sTask.

## Installation und Publish

Fuehre Befehle im Evolution CMS `core` Verzeichnis aus.

```console
php artisan package:installrequire seiger/stask "*"
php artisan vendor:publish --tag=stask
php artisan vendor:publish --tag=evo-ui --force
php artisan migrate
```

Nach Permission-Migrationen im Manager abmelden und neu anmelden.

## Manager-Registrierung

`Seiger\sTask\sTaskServiceProvider` merged `config/sTaskCheck.php` nach
`cms.settings`. Pruefe Modultitel, Icon, Reihenfolge und package alias, bevor du
Runtime-Code aenderst.

## Table Presets

| Config key | Datei | Bereich |
| --- | --- | --- |
| `stask.tasks.table` | `config/tasks/table.php` | Task-Tabelle/Liste. |
| `stask.workers.table` | `config/workers/table.php` | Worker-Tabelle/Liste. |
| `stask.logs.table` | `config/logs/table.php` | Logs und Details. |

Task-Filter sollen standard EvoUI multi-select filters bleiben, wenn mehrere
Werte moeglich sind. Nicht die single-select article filters kopieren.

## Worker Discovery

`WorkerDiscovery` findet Worker-Klassen und beachtet `excluded_namespaces`.
Worker brauchen stabile identity values, settings und eine `handles` map.

## Command Security

`config/artisan_security.php` steuert allowed, forbidden, dangerous und
confirmation-required commands fuer `ArtisanWorker`.

## Commands

```console
php artisan stask:worker
php artisan stask:publish
php artisan stask:publish --no-prune
```


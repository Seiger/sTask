# Configuration

Cette page decrit l'installation sTask, l'enregistrement manager, les presets de
tables, les commandes et la discovery des workers.

## Installation et publish

Execute les commandes depuis le dossier Evolution CMS `core`.

```console
php artisan package:installrequire seiger/stask "*"
php artisan vendor:publish --tag=stask
php artisan vendor:publish --tag=evo-ui --force
php artisan migrate
```

Apres les migrations de permissions, deconnecte-toi du manager puis reconnecte-toi.

## Enregistrement manager

`Seiger\sTask\sTaskServiceProvider` merge `config/sTaskCheck.php` dans
`cms.settings`. Verifie titre, icone, ordre et package alias avant de changer le
runtime.

## Table presets

| Config key | Fichier | Surface |
| --- | --- | --- |
| `stask.tasks.table` | `config/tasks/table.php` | Table/liste des taches. |
| `stask.workers.table` | `config/workers/table.php` | Table/liste des workers. |
| `stask.logs.table` | `config/logs/table.php` | Logs et details. |

Les filtres de taches doivent rester des EvoUI multi-select filters quand
plusieurs valeurs sont possibles. Ne copie pas les single-select article filters.

## Worker discovery

`WorkerDiscovery` trouve les worker classes et respecte `excluded_namespaces`.
Un worker garde une identity stable, des settings et une map `handles`.

## Command security

`config/artisan_security.php` controle allowed, forbidden, dangerous et
confirmation-required commands pour `ArtisanWorker`.

## Commands

```console
php artisan stask:worker
php artisan stask:publish
php artisan stask:publish --no-prune
```


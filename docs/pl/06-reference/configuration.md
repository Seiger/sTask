# Konfiguracja

Ta strona opisuje instalacje sTask, rejestracje modulu managera, presety tabel,
komendy i wykrywanie workerow.

## Instalacja i publish

Komendy uruchamiaj z katalogu Evolution CMS `core`.

```console
php artisan package:installrequire seiger/stask "*"
php artisan vendor:publish --tag=stask
php artisan vendor:publish --tag=evo-ui --force
php artisan migrate
```

Po migracjach uprawnien wyloguj sie z managera i zaloguj ponownie.

## Rejestracja modulu

`Seiger\sTask\sTaskServiceProvider` laczy `config/sTaskCheck.php` z
`cms.settings`. Sprawdz tytul modulu, ikone, kolejnosc i package alias zanim
zmienisz kod runtime.

## Table presets

| Config key | Plik | Powierzchnia |
| --- | --- | --- |
| `stask.tasks.table` | `config/tasks/table.php` | Tabela/lista zadan. |
| `stask.workers.table` | `config/workers/table.php` | Tabela/lista workerow. |
| `stask.logs.table` | `config/logs/table.php` | Logi i szczegoly. |

Filtry zadan powinny pozostac standardowymi EvoUI multi-select filters tam,
gdzie mozna wybrac wiele wartosci. Nie kopiuj single-select filters z articles.

## Worker discovery

`WorkerDiscovery` znajduje klasy workerow i respektuje `excluded_namespaces`.
Worker powinien miec stabilne identity, settings i mape `handles`.

## Command security

`config/artisan_security.php` kontroluje allowed, forbidden, dangerous i
confirmation-required commands dla `ArtisanWorker`.

## Commands

```console
php artisan stask:worker
php artisan stask:publish
php artisan stask:publish --no-prune
```


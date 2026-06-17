# Reference

Ta strona opisuje stabilne sygnaly pakietu uzywane przez dDocs, integracje i
agentow migracyjnych.

## Service Provider

`Seiger\sTask\sTaskServiceProvider` rejestruje modul managera Evolution, routy,
migracje, widoki, tlumaczenia, komponent Livewire, komende konsolowa i presety
tabel EvoUI.

| Sygnal | Cel |
| --- | --- |
| `cms.settings` | Ustawienia managera z `config/sTaskCheck.php`. |
| `stask.tasks.table` | Preset tabeli zadan z `config/tasks/table.php`. |
| `stask.workers.table` | Preset tabeli workerow z `config/workers/table.php`. |
| `stask.logs.table` | Preset tabeli logow z `config/logs/table.php`. |
| `tasks.table` | Nazwa publikowanej konfiguracji tabeli zadan. |
| `workers.table` | Nazwa publikowanej konfiguracji tabeli workerow. |
| `logs.table` | Nazwa publikowanej konfiguracji tabeli logow. |
| `sTaskAlias` | Alias package plugin bridge. |
| `sTaskCheck` | Zrodlo konfiguracji rejestracji modulu. |

## Runtime Classes

| Class | Responsibility |
| --- | --- |
| `DashboardData` | Karty panelu, ostatnie zadania i ostatnie bledy. |
| `MetricsService` | Metryki runtime i statystyki. |
| `PublishAssets` | Publikuje manager assets pakietu i utrzymuje pliki EvoUI-facing. |
| `ModulePanel` | Panel managera Livewire + EvoUI. |
| `TasksTableData` | Dane tabeli/listy zadan. |
| `WorkersTableData` | Dane tabeli/listy workerow. |
| `LogsTableData` | Dane logow i szczegolow zadania. |
| `TaskProgress` | Normalizacja progresu i komunikatow zadania. |
| `WorkerDiscovery` | Wykrywanie workerow i namespace exclusions. |
| `WorkerService` | Tworzenie i sterowanie wykonaniem zadan workerow. |
| `BaseWorker` | Klasa bazowa dla custom workerow. |
| `ArtisanWorker` | Worker dla dozwolonych komend Artisan. |
| `ComposerUpdateWorker` | Worker dla maintenance przez Composer. |
| `TaskWorker` | Komenda obslugujaca kolejke zadan. |

## Exceptions

| Exception | Meaning |
| --- | --- |
| `WorkerNotFoundException` | Worker identifier nie istnieje. |
| `WorkerClassNotFoundException` | Klasa workera nie moze byc zaladowana. |
| `WorkerInvalidInterfaceException` | Klasa nie spelnia worker contract. |

## Worker Contract

Worker deklaruje `identifier()`, `scope()`, `title()`, `description()` i
`settings()`. Mapa `handles` opisuje akcje zadan. Discovery moze pomijac
namespace przez `excluded_namespaces`.

## Console Commands

```console
php artisan stask:worker
php artisan stask:publish
php artisan stask:publish --no-prune
```

Pelna sygnatura publish command:
`stask:publish {--no-prune : Do not delete existing files before publish}`.

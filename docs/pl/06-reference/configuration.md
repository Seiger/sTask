# Konfiguracja

## `config/sTaskCheck.php`

Łączy się w `cms.settings`:

| Klucz | Domyślne | Znaczenie |
| --- | --- | --- |
| `check_sTask` | `true` | Obecność pakietu/Flaga kontrolna |
| `sTaskVer` | `2.9999999.9999999.9999999-dev` | Znacznik wersji gałęzi rozwoju |

To nie jest strojenie w czasie pracy w czasie rzeczywistym.

## Presety tabeli

| Plik | Klucz konfiguracyjny | Powierzchnia |
| --- | --- | --- |
| `config/tasks/table.php` | `stask.tasks.table` | Zadania |
| `config/workers/table.php` | `stask.workers.table` | Pracownicy |
| `config/logs/table.php` | `stask.logs.table` | Logi |

Presety definiują dostawcę, metody przewodów, paginację, widoki, filtry, kolumny, modal oraz akcje. Do nadpisania projektu użyj niestandardowego mechanizmu konfiguracji Evolution lub punktu publish/extension, jeśli jest to obsługiwane przez Twoją wersję; Nie edytuj plików dostawców.

## `config/excluded_namespaces.php`

Lista prefiksów przestrzeni nazw, których `WorkerDiscovery` nie uwzględnia. Domyślnie przestrzenie framework/vendorów, takie jak `Illuminate\`, `Symfony\`, `EvolutionCMS\Legacy\`, `PHPUnit\` itd., są wykluczone.

Jeśli twój pracownik znajduje się pod prefiksem wykluczonym, przenieś go do przestrzeni nazw pakiet/projekt. Nie skracaj listy bez analizy: odkrywanie może zacząć tworzyć tysiące klas firm trzecich.

## `config/artisan_security.php`

Używane `ArtisanWorker`:

| Klucz | Domyślne |
| --- | --- |
| `dangerous_commands` | `migrate:fresh`, `migrate:reset`, `db:wipe` |
| `confirmation_required` | `migrate`, `migrate:refresh`, `migrate:rollback`, `db:seed`, `cache:clear-full` |
| `whitelist` | empty: wszystkie oprócz blocked |
| `blacklist` | pusty |
| `enabled` | `true` |
| `log_executions` | `true` |
| `required_permission` | `run_artisan` |

Wzorce na białej liście wspierają `*` komentarzy kontraktowych. W produkcji należy włączyć zabezpieczenia i tworzyć wyraźną białą listę dla potrzeb operacyjnych.

## Ustawienia pracowników

Zapisane w `s_workers.settings` JSON:

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

`TaskWorker` niezależnie oblicza następny przebieg; `shouldRunNow()` jest pomocnikiem po stronie pracownika i nie jest głównym planerem w CLI.

## Kontener serwisowy

Pojedyncze osoby:

```php
app(Seiger\sTask\sTask::class);
app(Seiger\sTask\Services\WorkerService::class);
app(Seiger\sTask\Services\MetricsService::class);
app(Seiger\sTask\Services\SupervisorService::class);
```

Dodatek elewacyjny: `sTask`.

## Przechowywanie

| Ścieżka | Dane |
| --- | --- |
| `core/storage/stask/{id}.log` | postęp tylko do dodawania |
| `core/storage/stask/uploads` | Pliki upload/Result Controller/Worker |
| Laravel cache | instancje pracowników, metryki, blokady nadzorców |

Provider tworzy tylko root `storage/stask`. Podkatalogi są tworzone przez odpowiadającą ścieżkę kodową.

## Metadane pakietu dla dDocs

dDocs brzmi:

- Nazwisko kompozytora `seiger/stask`;
- zlokalizowane klucze `lang/{locale}/global.php`  `module_title`, `module_description`, `module_icon`;
- `docs/{locale}`.

Oczekiwane metadane:

```php
'module_title' => 'sTask',
'module_icon' => 'tabler-progress-check',
```

`docs/docs.json` jest przenośnym manifestem ekwipunku. Obecne środowisko uruchomieniowe dDocs może nie używać manifestu bezpośrednio; wykrywalność zapewnia skanowanie pakietu Composer oraz fizyczne zlokalizowane drzewo docs.

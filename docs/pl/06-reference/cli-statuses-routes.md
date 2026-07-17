# CLI, statusy i trasy

## CLI

### `stask:worker`

```bash
php artisan stask:worker
```

Brak opcji. Tworzy zaplanowane zadania, nadzoruje robotników daemonów, przetwarza wszystkie gotowe zadania w kolejce kolejkowo po kolei, wykonuje cleanup-if-idle i zwraca kod wyjścia 0 nawet jeśli poszczególne zadania zawiodą (wyjątki są wykrywane dla każdego zadania).

Umówiony lekarz: co minutę.

### `stask:publish`

```bash
php artisan stask:publish
```

Kopiuje materiały pakietowe do publicznych `assets/site`; używa `Filesystem`. Po aktualizacji pakietu uruchom ponownie.

### Pokrewne polecenia Evolution

```bash
php artisan migrate
php artisan package:discover
php artisan route:list --path=stask
php artisan schedule:list
php artisan cache:clear-full
```

Ich dokładna dostępność zależy od wersji Evolution CMS.

## Status zadania

| Kod | Stała PHP | Tekst API | Aktywny | Final |
| ---: | --- | --- | --- | --- |
| 10 | `TASK_STATUS_QUEUED` | w oczekiwaniu | tak | nie |
| 30 | `TASK_STATUS_PREPARING` | przygotowanie | tak | nie |
| 50 | `TASK_STATUS_RUNNING` | running | tak | nie |
| 80 | `TASK_STATUS_FINISHED` | ukończone | nie | tak |
| 100 | `TASK_STATUS_FAILED` | nieudane | nie | tak |

Nieznany kod → `unknown`.

## Stan nadzorcy

| Wartość | Znaczenie | wymagaUruchomienie |
| --- | --- | --- |
| `healthy` | dostępny proces | nie |
| `starting` | Startup w toku | nie |
| `degraded` | zdrowie się pogorszyło | tak |
| `failed` | inspekcja/niepowodzenie startu | tak |
| `stopped` | Proces zatrzymany/nie znaleziony | tak |

## Wartości harmonogramu

Typy: `manual`, `once`, `periodic`, `regular`, `supervisor`.

Częstotliwości:

- okresowe: `minutely`, `every_5min`, `every_15min`, `every_30min`, `hourly`, `daily`, `weekly`, `monthly`;
- regularne: `every_5min`, `every_15min`, `every_30min`, `hourly`.

## Trasy menedżerskie

Wszystkie mają prefiks `/stask`, prefiks nazwy trasy `sTask.` oraz middleware `mgr`. Pełna tabela z metodami i kontekstem ładunku: [Trasy, pliki postępów i pliki do pobrania](../04-development/routes-and-progress.md).

Klucz:

```text
GET  /stask
POST /stask/worker/{identifier}/run/{action}
GET  /stask/task/{id}/progress
GET  /stask/task/{id}
GET  /stask/performance/summary
POST /stask/cache/clear
```

## Publiczne zajęcia PHP

| Klasa | Rola |
| --- | --- |
| `Seiger\sTask\sTask` | Obsługa fasady |
| `Seiger\sTask\Facades\sTask` | Fasada Laravel |
| `Seiger\sTask\Workers\BaseWorker` | Baza Pracownicza |
| `Seiger\sTask\Contracts\TaskInterface` | umowa pracownicza |
| `Seiger\sTask\Contracts\SupervisorWorkerInterface` | Zdolności demonów |
| `Seiger\sTask\Support\SupervisorStatus` | Niezmienny migawka zdrowia |
| `Seiger\sTask\Enums\SupervisorState` | Daemon stwierdza |
| `Seiger\sTask\Models\sTaskModel` | task Eloquent model |
| `Seiger\sTask\Models\sWorker` | worker Eloquent model |
| `Seiger\sTask\Models\sSupervisorState` | Model stanu na żywo |
| `Seiger\sTask\Services\TaskProgress` | Postęp pliku |
| `Seiger\sTask\Services\WorkerDiscovery` | Odkrywanie rejestru |
| `Seiger\sTask\Services\WorkerService` | rozdzielczość/cache |
| `Seiger\sTask\Services\SupervisorService` | Nadzór cyklu życia |
| `Seiger\sTask\Services\MetricsService` | Statystyki/Metryki pamięci podręcznej |

## Wyjątki

- `WorkerNotFoundException`;
- `WorkerClassNotFoundException`;
- `WorkerInvalidInterfaceException`.

Każdy wyjątek od rozstrzygnięcia ma kontekstowy ładunek do logowania. Warstwa menedżer/API może je przekształcić w komunikaty JSON; Nie pokazuj użytkownika końcowego Stack Trace.

## Pomocniki formatowania w UI

- `niceCount(int|float)` — zwarte liczenie;
- `niceSize(bytes)` — czytelny rozmiar;
- `niceEta(seconds)` — czas trwania/ETA/dostępność.

To są pomocniki runtime Evolution/evo-UI, a nie fasada sTask.

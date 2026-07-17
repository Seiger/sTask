# Fasada i API PHP

Kanoniczna klasa służbowa to `Seiger\sTask\sTask`; Fasada — `Seiger\sTask\Facades\sTask`. Alias kompozytora również `sTask` jest logowany, ale import jawny jest łatwiej czytelny i wygodniejszy do analizy statycznej.

## Tworzenie zadania

```php
public function create(
    string $identifier,
    string $action,
    array $data = [],
    string $priority = 'normal',
    ?int $userId = null,
): sTaskModel
```

```php
use Seiger\sTask\Facades\sTask;

$task = sTask::create(
    'catalog_sync',
    'sync_stock',
    ['shop_id' => 7, 'dry_run' => false],
    'normal',
    evo()->getLoginUserID() ?: null,
);
```

Metoda normalizuje meta asocjacyjny poprzez sortowanie rekurencyjne i zwraca aktywny duplikat, jeśli identyfikator/akcja/meta już się zgadza. Nowy rekord: kolejka, postęp 0, próby 0, max_attempts 3.

Priorytet istnieje w PHP/schema dla kompatybilności i kolejki w `getPendingTasks()`, ale nie jest wyświetlany w aktualnych kolumnach/filtrach tabeli menedżera.

## Wykonuję jedno zadanie

```php
public function execute(sTaskModel $task): bool
```

Metoda zapisuje metryki startowe, uruchamia działania, rozwiązuje pracownika przez `WorkerService`, wywołuje akcję i finalizuje zadanie, jeśli pracownik tego nie zrobił. Wyjątek zatwierdza zadanie do niepowodzenia i zwraca `false`.

Wywołaj `execute()` tylko w kontrolowanym kontekście CLI/kolejki. Manager flow i scheduler używają `stask:worker`.

## Kolejka

```php
public function getPendingTasks(int $limit = 10): Collection
public function processPendingTasks(?int $batchSize = null): int
```

`getPendingTasks()` odczytuje status `10`, sortuje priorytet wysoki → normalny → niski, a potem `created_at`. W przeciwieństwie do CLI `TaskWorker`, ta metoda nie filtruje przyszłych `start_at`; Nie używaj go do semantyki planistów bez dodatkowego warunku.

`processPendingTasks()` kolejno wywołuje `execute()` i zwraca liczbę udanych zadań.

## Statystyki i metryki

```php
public function getStats(): array
public function getPerformanceMetrics(int $hours = 24): array
public function getWorkerStats(?string $identifier = null, int $hours = 24): array
public function getPerformanceAlerts(): array
```

`getStats()` zwroty liczą oczekujące/bieżące/ukończone/nieudane/suma oraz pracowników. API wydajności częściowo zastępcze: agregacja czasu trwania/pamięci z rekordów zadań nie została jeszcze zaimplementowana.

## Rejestr pracowników

```php
public function discoverWorkers(): array
public function registerWorker(string $className): ?sWorker
public function cleanOrphanedWorkers(): int
public function getWorkers(bool $activeOnly = false): Collection
public function getWorker(string $identifier): ?sWorker
public function activateWorker(string $identifier): bool
public function deactivateWorker(string $identifier): bool
```

Discovery działa na mapie klasy Composer. Po dodaniu klasy:

```bash
composer dump-autoload
php artisan package:discover
```

lub kliknij odśwież rejestr w interfejsie. Pamiętaj: nowe rekordy są tworzone nieaktywnie.

## Cache Worker

```php
public function getCacheStats(): array
public function clearWorkerCache(?string $identifier = null): void
```

`WorkerService` posiada wpisy pamięci podręcznej w pamięci oraz Laravel z prefiksem `stask_worker_`. Wyczyść konkretny identyfikator po zmianie ustawień/klasy lub całą pamięć podręczną po odświeżeniu/wdrożeniu rejestru.

## Historia oczyszczania

```php
public function cleanOldTasks(int $days = 30): int
```

Usuwa tylko ukończone zadania (`status = 80`) z `finished_at` najwyższym progiem. Pliki "nieudane", kolejkowe, działające, stan nadzorcy oraz postępy nie są czyszczone tą metodą.

## sTaskModel

Przydatne zakresy i metody:

```php
sTaskModel::queued();
sTaskModel::preparing();
sTaskModel::running();
sTaskModel::finished();
sTaskModel::failed();
sTaskModel::incomplete();
sTaskModel::byIdentifier('catalog_sync');
sTaskModel::byAction('make');

$task->markAsRunning();
$task->markAsFinished('Done');
$task->markAsFailed('Reason');
$task->updateProgress(50, 'Half complete');
$task->canRetry();
$task->isFinished();
$task->isRunning();
$task->isPending();
```

`markAsRunning()` nadpisuje `start_at = now()` i zwiększa liczbę prób. `markAsFinished()` postaw postęp na 100. `markAsFailed()` nie wpisuj postępu 100.

## Meta i Rezultat

Model odrzuca `meta` i `result` jako tablice, a `start_at`/`finished_at` jako daty czasowe. Przekazuj wartości zgodne z JSON. Nie umieszczaj elokwentnych modeli, zasobów, zamknięcia ani sekretów.

Dla wyników do pobrania `BaseWorker::markFinished()` można uzyskać ścieżkę ciągu ciągów, ale model cast `result => array` i logika pobierania HTTP mają własne oczekiwania. Sprawdź kontrakt i test końcowy pracownika betonowego; Nie uważaj dowolnej ścieżki za automatycznie dostępną.

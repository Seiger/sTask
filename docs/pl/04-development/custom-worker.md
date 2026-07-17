# Własny pracownik

Najprostsze jest podążanie za `BaseWorker`. Następnie klasa otrzymuje tworzenie zadań, ustawianie harmonogramu, wysyłanie akcji, postęp i finalizację.

## Pełny przykład

```php
<?php

namespace EvolutionCMS\Custom\Workers;

use EvolutionCMS\Models\SiteContent;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Workers\BaseWorker;
use Throwable;

final class DocumentAuditWorker extends BaseWorker
{
    public function identifier(): string
    {
        return 'document_audit';
    }

    public function scope(): string
    {
        return 'custom';
    }

    public function icon(): string
    {
        return '<i data-lucide="database-search"></i>';
    }

    public function title(): string
    {
        return 'Audyt dokumentów';
    }

    public function description(): string
    {
        return 'Zlicza opublikowane i usunięte dokumenty partiami.';
    }

    public function taskMake(sTaskModel $task, array $options = []): void
    {
        $batchSize = max(1, min(500, (int)($options['batch_size'] ?? 100)));
        $total = SiteContent::query()->count();
        $processed = 0;
        $published = 0;
        $deleted = 0;
        $startedAt = microtime(true);

        try {
            SiteContent::query()
                ->select(['id', 'published', 'deleted'])
                ->orderBy('id')
                ->chunkById($batchSize, function ($documents) use (
                    $task,
                    $total,
                    $startedAt,
                    &$processed,
                    &$published,
                    &$deleted,
                ): void {
                    foreach ($documents as $document) {
                        $processed++;
                        $published += (int)$document->published;
                        $deleted += (int)$document->deleted;
                    }

                    $progress = $total > 0 ? (int)floor($processed * 100 / $total) : 100;
                    $etaSeconds = $processed > 0
                        ? (int)round((microtime(true) - $startedAt) / $processed * ($total - $processed))
                        : 0;

                    $this->pushProgress($task, [
                        'status' => 'running',
                        'progress' => $progress,
                        'processed' => $processed,
                        'total' => $total,
                        'eta' => niceEta((float)$etaSeconds),
                        'message' => "Sprawdzono **{$processed}** z **{$total}**",
                    ]);
                });

            $task->update(['progress' => 100]);
            $this->markFinished($task, null, "Audyt zakończony: {$processed} dokumentów");
            $task->update(['result' => [
                'processed' => $processed,
                'published' => $published,
                'deleted' => $deleted,
            ]]);
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($task, 'Nie udało się wykonać audytu: ' . $exception->getMessage());
        }
    }
}
```

Przykład nie ma zależności aplikacji: odczytuje standardowy model CMS Evolution i nie modyfikuje dokumentów. Aktualny `markFinished()` zawsze zapisuje swój argument ciągu ciągu do `result`, podczas gdy model odlewa pole jako tablicę. Dlatego przykład ze strukturalnym wynikiem zapisuje się osobno **po** `markFinished()`; To jest rzeczywista kolejność, która zapobiega usuwaniu wartości tablicy przez pomocnika. Podstawowa sygnatura działania jest ściśle rzeczywista: `taskMake(sTaskModel $task, array $options = []): void`.

## Wymagane metody

`TaskInterface` wymaga:

- `identifier(): string`;
- `scope(): string`;
- `icon(): string`;
- `title(): string`;
- `description(): string`;
- `renderWidget(): string`;
- `settings(): array`.

`BaseWorker` już wdraża `renderWidget()` i `settings()`. Klasa Concrete dodaje metadane i metody działań.

## Akcje nazewnictwa

`invokeAction()` odlewa akcję na małe litery, zastępuje `-`/`_` słowami i dodaje przedrostek `task`:

| Akcja | Metoda |
| --- | --- |
| `make` | `taskMake` |
| `sync_stock` | `taskSyncStock` |
| `import-csv` | `taskImportCsv` |

Jeśli metoda jest nieobecna, rzucany jest `BadMethodCallException` i pracownik CLI oznacza niepowodzenie zadania.

## Rejestracja

1. Dodaj klasę do pakietu/projektu przestrzeni nazw PSR-4.
2. Zaktualizuj mapę klas Composer.
3. Odkrywanie podczas startu.
4. Aktywuj pracownika.

```bash
cd core
composer dump-autoload
php artisan package:discover
```

Albo programowo:

```php
use Seiger\sTask\Facades\sTask;

sTask::registerWorker(DocumentAuditWorker::class);
sTask::activateWorker('document_audit');
```

Identyfikator musi być stabilny i unikalny. Ponowne skanowanie może zmienić identyfikator zachowany rekord, ale istniejący `s_tasks.identifier` nie migruje automatycznie.

## Tworzenie zadania od pracownika

```php
$task = $worker->createTask('make', [
    'batch_size' => 100,
    'manual' => true,
]);
```

Jeśli opcje nie zostaną przyjęte, `createTask()` wymaga `options` żądania i bezpośredniego `filename`. W przypadku CLI/kodu zaplanowanego zawsze przekazuj opcje wyraźnie.

## Postęp

```php
$this->pushProgress($task, [
    'status' => 'running',
    'progress' => 42,
    'processed' => 420,
    'total' => 1000,
    'eta' => niceEta(37),
    'message' => 'Przetwarzam **partię 5**',
]);
```

To zapisuje dziennik plików, ale nie aktualizuje pola `s_tasks.progress`. Jeśli do końcowych tabel/odzyskania wymagany jest postęp, zaktualizuj model na punktach kontrolnych, a nie na każdym elemencie.

Wiadomość musi mieć jedną linię; `TaskProgress` zastępuje przerwy na `<br>` i `|` rur na `¦`.

## Uzupełnienia i błędy

```php
$this->markFinished($task, null, 'Gotowe');
$task->update(['result' => ['count' => 420]]);
```

```php
$this->markFailed($task, 'Zewnętrzny interfejs API zwrócił 503');
```

Jeśli akcja wygeneruje wyjątek, `TaskWorker` również ustawi niepowodzenie i zapisze nazwę pliku/linię/błąd do dziennika postępów. Wykrywaj wyjątki tylko wtedy, gdy możesz dodać kontekst lub sprzątać; w przeciwnym razie pozwól runnerowi popełnić awarię centralnie.

## Strategia ponownej próby

Pakiet liczy próby, ale nie automatycznie ponownie ustawia kolejek po nieudanych wierszach. Do ponownej próby:

- uczynić działalność biznesową bezwartościową;
- Identyfikacja powtarzalnych typów błędów;
- utworzenie nowego zadania lub celowe zwrócenie nieudanych rekordów w kolejce;
- zastosowanie wykładniczego cofnięcia po `start_at`;
- Nie powtarzaj błędów walidacji/uwierzytelniania.

## Ustawienia pracowników

```php
$timeout = (int)$this->getConfig('http.timeout', 10);
$this->setConfig('http.timeout', 20);
$this->updateConfig(['endpoint' => 'https://example.test']);
```

`updateSettings()` tworzy płytkie `array_merge`; Zagnieżdżone struktury można całkowicie zastąpić po aktualizacji.

## Własny widget

Przepisywanie `renderWidget()` tylko wtedy, gdy domyślny task runner EvoUI jest niewystarczający. Przywróć renderowany widok Blade, uchwyć dane użytkownika i użyj tras menedżerskich z CSRF. Nie osadzaj sekretów w descriptor/HTML.

## Przedłużenie przełożone

Jeśli pracownik posiada odłączonego demona, dodaj `SupervisorWorkerInterface`, ale nie usuwaj zwykłego `TaskInterface`. Pełny kontrakt: [Nadzorca Procesu](../02-concepts/supervisor.md).

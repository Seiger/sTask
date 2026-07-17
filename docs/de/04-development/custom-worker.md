# Eigener Arbeiter

Der einfachste Weg ist, `BaseWorker` zu folgen. Die Klasse erhält dann Aufgabenerstellung, Zeitplaneinstellungen, Aktionsabwicklung, Fortschritt und Finalisierung.

## Vollständiges Beispiel

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
        return 'Dokumentenprüfung';
    }

    public function description(): string
    {
        return 'Zählt veröffentlichte und gelöschte Dokumente stapelweise.';
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
                        'message' => "Geprüft: **{$processed}** von **{$total}**",
                    ]);
                });

            $task->update(['progress' => 100]);
            $this->markFinished($task, null, "Prüfung abgeschlossen: {$processed} Dokumente");
            $task->update(['result' => [
                'processed' => $processed,
                'published' => $published,
                'deleted' => $deleted,
            ]]);
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($task, 'Prüfung fehlgeschlagen: ' . $exception->getMessage());
        }
    }
}
```

Das Beispiel weist keine Anwendungsabhängigkeiten auf: Es liest das Standard-Evolution-CMS-Modell und ändert keine Dokumente. Das aktuelle `markFinished()` schreibt immer sein nullables String-Argument auf `result`, während das Modell das Feld als Array beschreibt. Daher schreibt das strukturierte Ergebnisbeispiel separat **nach** `markFinished()`; Dies ist die reale Ordnung, die verhindert, dass der Helfer den Array-Wert löscht. Die Grundsignatur der Wirkung ist streng real: `taskMake(sTaskModel $task, array $options = []): void`.

## Erforderliche Methoden

`TaskInterface` erfordert:

- `identifier(): string`;
- `scope(): string`;
- `icon(): string`;
- `title(): string`;
- `description(): string`;
- `renderWidget(): string`;
- `settings(): array`.

`BaseWorker` setzt bereits `renderWidget()` und `settings()` um. Die Concrete-Klasse fügt Metadaten und Aktionsmethoden hinzu.

## Namensaktionen

`invokeAction()` setzt die Aktion in Kleinbuchstaben, ersetzt `-`/`_` durch Wörter und fügt ein Präfix `task` hinzu:

| Aktion | Methode |
| --- | --- |
| `make` | `taskMake` |
| `sync_stock` | `taskSyncStock` |
| `import-csv` | `taskImportCsv` |

Wenn die Methode fehlt, wird ein `BadMethodCallException` geworfen und der CLI-Worker markiert die Aufgabe als fehlgeschlagen.

## Registrierung

1. Klasse zum PSR-4-Namespace-Paket/Projekt hinzufügen.
2. Aktualisieren Sie die Composer-Klassenkarte.
3. Launch Discovery.
4. Aktiviere den Arbeiter.

```bash
cd core
composer dump-autoload
php artisan package:discover
```

Oder programmatisch:

```php
use Seiger\sTask\Facades\sTask;

sTask::registerWorker(DocumentAuditWorker::class);
sTask::activateWorker('document_audit');
```

Die Kennung muss stabil und eindeutig sein. Rescan kann den gespeicherten Datensatz der Identifikator ändern, aber bestehende `s_tasks.identifier` migriert nicht automatisch.

## Eine Aufgabe aus einem Arbeiter erschaffen

```php
$task = $worker->createTask('make', [
    'batch_size' => 100,
    'manual' => true,
]);
```

Wenn die Optionen nicht angenommen werden, nimmt `createTask()` Anfrage `options` und direkte `filename`. Für CLI/geplanten Code solltest du Optionen immer explizit weitergeben.

## Fortschritt

```php
$this->pushProgress($task, [
    'status' => 'running',
    'progress' => 42,
    'processed' => 420,
    'total' => 1000,
    'eta' => niceEta(37),
    'message' => 'Verarbeite **Stapel 5**',
]);
```

Dies schreibt ein Dateiprotokoll, aktualisiert jedoch das `s_tasks.progress` Feld nicht. Wenn dauerhafter Fortschritt für die finalen Tabellen/Wiederherstellung erforderlich ist, aktualisiere das Modell an Checkpoints und nicht bei jedem Item.

Die Nachricht muss einzeilig sein; `TaskProgress` ersetzt Leitungsbrüche durch `<br>` und Rohrleitungen `|` durch `¦`.

## Vervollständigungen und Fehler

```php
$this->markFinished($task, null, 'Fertig');
$task->update(['result' => ['count' => 420]]);
```

```php
$this->markFailed($task, 'Externe API gab 503 zurück');
```

Wenn Action eine Ausnahme auslöst, setzt `TaskWorker` auch failed und schreibt Dateiname/Zeile/Fehler in das Fortschrittsprotokoll. Fange Ausnahmen nur, wenn du Kontext hinzufügen oder bereinigen kannst; Andernfalls sollte der Läufer zentral versagen.

## Wiederholungsstrategie

Das Paket zählt Versuche, legt aber nicht automatisch fehlgeschlagene Zeilen wieder in die Warteschlange. Zum erneuten Versuch:

- den Geschäftsbetrieb idempotent zu machen;
- Identifiziere wiederholbare Fehlertypen;
- eine neue Aufgabe erstellen oder absichtlich einen fehlgeschlagenen Datensatz in der Warteschlange zurückgeben;
- Exponentiellen Rückschritt nach `start_at` anwenden;
- Wiederholen Sie keine Validierungs-/Authentifizierungsfehler.

## Arbeitereinstellungen

```php
$timeout = (int)$this->getConfig('http.timeout', 10);
$this->setConfig('http.timeout', 20);
$this->updateConfig(['endpoint' => 'https://example.test']);
```

`updateSettings()` macht flache `array_merge`; Verschachtelte Strukturen können bei Aktualisierung vollständig ersetzt werden.

## Benutzerdefiniertes Widget

Override `renderWidget()` nur, wenn der Standard-EvoUI-Taskrunner nicht ausreicht. Die gerenderte Blade-Ansicht zurückgeben, Benutzerdaten entweichen und Manager-Routen mit CSRF verwenden. Stecke keine Geheimnisse in Descriptor/HTML ein.

## Supervisor-Erweiterung

Wenn der Arbeiter einen abgetrennten Dämon besitzt, füge `SupervisorWorkerInterface` hinzu, aber entferne nicht den regulären `TaskInterface`. Vollvertrag: [Prozessleiter](../02-concepts/supervisor.md).

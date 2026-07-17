# Facade und PHP-API

Die kanonische Dienstklasse ist `Seiger\sTask\sTask`; Fassade — `Seiger\sTask\Facades\sTask`. Composer-Alias wird `sTask` ebenfalls protokolliert, aber expliziter Import ist besser lesbar und bequemer für statische Analysen.

## Eine Aufgabe schaffen

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

Die Methode normalisiert assoziative Meta durch rekursive Sortierung und gibt aktive Duplikate zurück, wenn der Identifikator/die Aktion/das Meta bereits übereinstimmt. Neuer Rekord: Warteschlange, Fortschritt 0, Versuche 0, max_attempts 3.

Priorität existiert in PHP/Schema für Kompatibilität und Warteschlangenreihenfolge in `getPendingTasks()`, wird aber in den aktuellen Manager-Tabellenspalten/Filtern nicht angezeigt.

## Eine einzige Aufgabe ausführen

```php
public function execute(sTaskModel $task): bool
```

Die Methode schreibt Startmetriken, setzt sie in Betrieb, löst den Worker durch `WorkerService` auf, ruft Action aus und finalisiert die Aufgabe, falls der Worker dies noch nicht getan hat. Eine Ausnahme verpflichtet eine Aufgabe auf fehlgeschlagen und gibt `false` zurück.

Rufen Sie `execute()` nur in einem kontrollierten CLI-/Warteschlangenkontext auf. Manager Flow und Scheduler nutzen die `stask:worker`.

## Warteschlange

```php
public function getPendingTasks(int $limit = 10): Collection
public function processPendingTasks(?int $batchSize = null): int
```

`getPendingTasks()` liest Status `10`, sortiert Priorität hoch → normal → niedrig, dann `created_at`. Im Gegensatz zu CLI- `TaskWorker` filtert diese Methode keine zukünftigen `start_at`; Verwenden Sie es nicht für Scheduler-Semantik ohne zusätzliche Bedingung.

`processPendingTasks()` ruft `execute()` nacheinander auf und gibt die Anzahl der erfolgreichen Aufgaben zurück.

## Statistiken und Metriken

```php
public function getStats(): array
public function getPerformanceMetrics(int $hours = 24): array
public function getWorkerStats(?string $identifier = null, int $hours = 24): array
public function getPerformanceAlerts(): array
```

`getStats()` Rückgaben zählen ausstehend/laufend/abgeschlossen/fehlgeschlagen/gesamt und Arbeiter. Performance API teilweise Platzhalter: Dauer-/Speicheraggregation aus Aufgabeneinträgen ist noch nicht implementiert.

## Arbeiterverzeichnis

```php
public function discoverWorkers(): array
public function registerWorker(string $className): ?sWorker
public function cleanOrphanedWorkers(): int
public function getWorkers(bool $activeOnly = false): Collection
public function getWorker(string $identifier): ?sWorker
public function activateWorker(string $identifier): bool
public function deactivateWorker(string $identifier): bool
```

Discovery arbeitet auf der Composer-Klassenkarte. Nach dem Hinzufügen einer Klasse:

```bash
composer dump-autoload
php artisan package:discover
```

oder klicke in der Benutzeroberfläche auf 'Registry aktualisieren'. Denken Sie daran: Neue Datensätze werden inaktiv erstellt.

## Arbeiter-Cache

```php
public function getCacheStats(): array
public function clearWorkerCache(?string $identifier = null): void
```

`WorkerService` hat In-Memory-Cache- und Laravel-Cache-Einträge mit dem Präfix `stask_worker_`. Lösche eine bestimmte Kennung nach der Änderung der Einstellungen/Klasse oder den gesamten Cache nach der Aktualisierung des Registrys.

## Clearing-Geschichte

```php
public function cleanOldTasks(int $days = 30): int
```

Löscht nur abgeschlossene Aufgaben (`status = 80`) mit `finished_at` höchsten Grenzwert. Fehlgeschlagene, Warteschlange, laufende, Supervisor-Status und Fortschrittsdateien werden mit dieser Methode nicht gelöscht.

## sTaskModel

Nützliche Anwendungsbereiche und Methoden:

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

`markAsRunning()` überschreibt `start_at = now()` und erhöht die Versuche. `markAsFinished()` setze Fortschritt auf 100. `markAsFailed()` setze Fortschritt nicht auf 100.

## Meta und Ergebnis

Das Modell gießt `meta` und `result` als Arrays und `start_at`/`finished_at` als Datumszeiten. Übergebe JSON-kompatible Werte. Fügen Sie keine eloquenten Modelle, Ressourcen, Abschlüsse oder Geheimnisse hinzu.

Für herunterladbare Ergebnisse kann `BaseWorker::markFinished()` einen Stringpfad erhalten, aber Model Cast `result => array` und HTTP-Download-Logik haben ihre eigenen Erwartungen. Überprüfen Sie den Betonarbeitervertrag und den Endpunkttest; Betrachten Sie keinen beliebigen Pfad als automatisch zugänglich.

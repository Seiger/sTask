# Migracja custom workerow

Ten przewodnik pokazuje, jak przenosic stare worker widgets do runtime sTask na
EvoUI bez budowania osobnego UI.

## Model docelowy

- worker posiada logike biznesowa;
- EvoUI posiada buttons, tables, filters, modals i badges;
- sTask posiada task creation, progress, logs, metadata, result i files.

Stary `renderWidget()` moze zostac jako compatibility boundary, ale nowe funkcje
powinny isc przez task actions.

## Worker identity

Dziedzicz po `BaseWorker` i utrzymuj stabilne identity.

```php
final class TelegramWorker extends BaseWorker
{
    public function identifier(): string
    {
        return 'dgramm_telegram';
    }

    public function scope(): string
    {
        return 'dgramm';
    }

    public function handles(): array
    {
        return ['make' => 'taskMake'];
    }
}
```

`handles` definiuje akcje dostepne w managerze.

## Task action

```php
public function taskMake(\Seiger\sTask\Models\sTaskModel $task, array $options = []): void
{
    $this->pushProgress($task, ['progress' => 30, 'message' => 'Preparing input']);
    $this->markFinished($task, ['processed' => 12], 'Done');
}
```

`TaskProgress` normalizuje progress dla tabel i modali. `LogsTableData` pokazuje
meta, result i logi bez package-specific modal.

## Files and UI boundary

Uploads/downloads ida przez sTask action controller i task metadata. Nie tworz
wlasnych table styles, status badges, filters ani details buttons w workerze.


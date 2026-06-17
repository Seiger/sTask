# Custom Worker Migration

Dieser Leitfaden beschreibt, wie alte worker widgets in die sTask EvoUI runtime
verschoben werden.

## Zielmodell

- worker besitzt business logic;
- EvoUI besitzt buttons, tables, filters, modals und badges;
- sTask besitzt task creation, progress, logs, metadata, result und files.

Altes `renderWidget()` kann compatibility boundary bleiben, neue Features sollen
ueber task actions laufen.

## Worker Identity

Von `BaseWorker` erben und stabile Werte behalten.

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

`handles` definiert die im Manager sichtbaren Aktionen.

## Task Action

```php
public function taskMake(\Seiger\sTask\Models\sTaskModel $task, array $options = []): void
{
    $this->pushProgress($task, ['progress' => 30, 'message' => 'Preparing input']);
    $this->markFinished($task, ['processed' => 12], 'Done');
}
```

`TaskProgress` normalisiert Fortschritt. `LogsTableData` zeigt meta, result und
logs ohne package-specific modal.

## Files und UI Boundary

Uploads/downloads laufen ueber den sTask action controller und task metadata.
Keine eigenen table styles, status badges, filters oder details buttons im
Worker bauen.


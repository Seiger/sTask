# Migration des custom workers

Ce guide explique comment migrer les anciens worker widgets vers le runtime
sTask sur EvoUI.

## Modele cible

- le worker garde la logique metier;
- EvoUI garde buttons, tables, filters, modals et badges;
- sTask garde task creation, progress, logs, metadata, result et files.

L'ancien `renderWidget()` peut rester une compatibility boundary, mais les
nouvelles fonctions doivent passer par des task actions.

## Worker identity

Etends `BaseWorker` et garde des valeurs stables.

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

`handles` definit les actions visibles dans le manager.

## Task action

```php
public function taskMake(\Seiger\sTask\Models\sTaskModel $task, array $options = []): void
{
    $this->pushProgress($task, ['progress' => 30, 'message' => 'Preparing input']);
    $this->markFinished($task, ['processed' => 12], 'Done');
}
```

`TaskProgress` normalise la progression. `LogsTableData` affiche meta, result et
logs sans package-specific modal.

## Files et UI boundary

Uploads/downloads passent par le sTask action controller et task metadata. Ne
cree pas de table styles, status badges, filters ou details buttons propres au
worker.


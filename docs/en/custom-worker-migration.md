# Custom Worker Migration

This guide explains how to move old custom worker widgets into the EvoUI sTask
runtime without recreating bespoke manager UI.

## Migration Target

The target pattern is:

- worker owns business logic;
- EvoUI owns buttons, tables, filters, modals, and badges;
- sTask owns task creation, progress, logs, metadata, result data, and files.

Legacy `renderWidget()` output can remain as a compatibility boundary, but new
work should move toward task actions and shared detail modals.

## Worker Identity

Extend `BaseWorker` and keep identity values stable.

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

The `handles` map is what the manager uses to show runnable actions.

## Task Action

Keep long-running work inside `task<Action>` methods.

```php
public function taskMake(\Seiger\sTask\Models\sTaskModel $task, array $options = []): void
{
    $this->pushProgress($task, [
        'progress' => 30,
        'message' => 'Preparing input',
    ]);

    $this->markFinished($task, ['processed' => 12], 'Done');
}
```

`TaskProgress` normalizes progress data for tables and detail modals.

## Metadata And Result

Use `meta` for input and execution context. Use `result` for structured output.
`LogsTableData` and the task detail modal should be able to show both without a
package-specific modal.

## Files

Uploads, chunked uploads, and downloads should use the sTask action controller
and task metadata. Avoid direct public paths in widget markup.

## UI Boundary

Do not rebuild table styling, status badges, filters, or details buttons inside
custom workers. Use the standard worker settings page, task table, log table,
and shared details modal.


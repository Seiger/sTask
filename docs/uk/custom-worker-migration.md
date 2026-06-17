# Міграція кастомних воркерів

Цей гайд пояснює, як переносити старі custom worker widgets у EvoUI runtime
sTask без нової кастомної адмінки.

## Цільова модель

Правильний поділ:

- worker містить бізнес-логіку;
- EvoUI відповідає за buttons, tables, filters, modals і badges;
- sTask відповідає за task creation, progress, logs, metadata, result і files.

Старий `renderWidget()` може лишатися compatibility boundary, але новий
функціонал треба вести до task actions і спільної detail modal.

## Ідентичність воркера

Наслідуй `BaseWorker` і тримай identity values стабільними.

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

Мапа `handles` визначає runnable actions у менеджері.

## Task action

Довгі операції тримай у методах `task<Action>`.

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

`TaskProgress` нормалізує прогрес для таблиць і detail modals.

## Meta і result

`meta` використовуй для input/context, `result` - для structured output.
`LogsTableData` і modal деталей мають показувати ці дані без package-specific
modal.

## Файли

Uploads, chunked uploads і downloads мають проходити через sTask action
controller і task metadata. Не вставляй прямі public paths у widget markup.

## UI boundary

Не збирай у воркері власні table styles, status badges, filters або details
buttons. Використовуй стандартні worker settings, task table, log table і
спільну details modal.


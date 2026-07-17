# Quick Start

This script runs from a clean install to the first completed task without any fictional APIs.

## 1. Check the package

```bash
cd core
php artisan route:list --path=stask
php artisan stask:worker
```

The second command may output `created 0 scheduled task(s), processed 0 task(s)` - this is a normal result for an empty queue.

## 2. Update the worker registry

Open **sTask → Workers** and click the button with the `database-cog` icon (**Update Worker Registry**). Discovery reads `vendor/composer/autoload_classmap.php`, discards excluded namespaces, and registers concrete classes that implement `TaskInterface`.

A new worker is created inactive. Turn it on with the power button or in the modal edit window.

## 3. Run manually

For an active worker with the `taskMake()` method, press `player-play`. sTask:

1. create `s_tasks` with status `10`;
2. write the first line in `storage/stask/{id}.log`;
3. will try to run `php core/artisan stask:worker` in the background;
4. will show live progress in the table row via adaptive HTTP polling.

If `exec`/`shell_exec` are disabled, run the command manually:

```bash
php artisan stask:worker
```

## 4. Check the result

On the **Tasks** tab, find the entry by ID, worker name or action. Expected status sequence:

```text
10 queued → 50 running → 80 finished
```

Status `30 preparing` defined by the model and can be used by application code, but the standard `TaskWorker` goes from queued directly to running.

Double-clicking on a line opens a readonly modal with message, meta, and result. A separate ID link is on the **Logs** tab and leads to the full task details page.

## 5. Create a task from PHP

The façade returns an existing active duplicate or a new model:

```php
<?php

use Seiger\sTask\Facades\sTask;

$task = sTask::create(
    identifier: 'inventory_sync',
    action: 'make',
    data: ['warehouse' => 12, 'force' => false],
    priority: 'normal',
    userId: evo()->getLoginUserID() ?: null,
);

echo $task->id;
```

Important: `create()` only queues the entry. Execution requires `stask:worker` or call `sTask::execute($task)` in a controlled process.

## 6. Set up automatic start

In the worker modal, enable Auto Start and select schedule. For example, every hour at the 15th minute:

```json
{
  "schedule": {
    "enabled": true,
    "type": "periodic",
    "frequency": "hourly",
    "time": "*:15",
    "datetime": "",
    "start_time": "",
    "end_time": ""
  }
}
```

The following `stask:worker` will create a future queued task with `start_at`. Until the time has come, task is visible in **Task**, but is not executed.

## Checklist

- package source reference matches the expected branch 2.x;
- migrations are successful;
- permission `stask` assigned the desired manager role;
- `storage/stask` a web user and CLI user are available for writing;
- cron runs scheduler every minute;
- worker is active, class exists, identifier is unique;
- task goes to final status and has `finished_at`.

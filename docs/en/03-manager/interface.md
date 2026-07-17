# User Guide

## Opening sTask

Open sTask from the Evolution CMS manager module registered by the package. The
new manager panel uses EvoUI assets loaded from the local package publish path,
so missing CSS or JavaScript usually means the package assets or evo-ui assets
need to be published again.

## Dashboard

The dashboard shows operational totals:

- pending tasks;
- running tasks;
- completed tasks;
- failed tasks;
- all tasks;
- active workers.

The recent tasks table shows the newest jobs. Use the eye action or double-click
a row to open the task detail modal. If there are no failed tasks, the recent
errors block is hidden instead of showing an empty panel.

## Tasks

The Tasks tab supports table and list views. Use table view when you need all
columns and list view when you need a compact scan.

Available filters:

- worker;
- action;
- status;
- priority;
- attempts;
- created period.

Visible task signals include ID, worker title, worker identifier, action, status,
priority, progress, attempts, started user, message excerpt, created time, start
time, finish time, and updated time.

Open details with the eye action or by double-clicking a row. Details show the
task identity, worker, status, priority, attempts, timestamps, log message,
metadata, and result payload.

## Workers

The Workers tab lists registered workers. Each worker row shows title,
description, identifier, scope, active state, class availability, visibility,
task count, last task status, position, and updated time.

Common actions:

- edit worker settings;
- run the worker default task;
- activate or deactivate the worker.

The toolbar duplicates the same selected-row actions. Select one worker first,
then use the toolbar buttons. Avoid running a worker from a production manager
unless the action and options are understood.

## Worker Settings

Worker settings contain the worker class, description, active state, schedule
configuration, and worker-specific options. Scheduled workers expose `taskMake`
and can be maintained by the background worker command.

## Logs

The Logs tab is a task audit panel. It uses the same detail modal as Tasks, so
task rows and log rows can be inspected the same way. Filter logs by worker,
status, and created date.

## Built-In Workers

sTask ships with built-in worker widgets:

- default task runner;
- Composer update worker;
- Artisan command worker.

These legacy widgets are being migrated to the shared task-runner contract. The
current manager should still keep task creation, progress, logs, and result
downloads working during that migration.

## Safe Operations

- Re-publish assets after package or evo-ui upgrades.
- Log out and back in after new manager permissions are installed.
- Treat run, Composer update, Artisan commands, and deactivate actions as
operational actions that can change system state.

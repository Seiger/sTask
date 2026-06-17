# Frontend Guide

This page defines the manager UI boundary for sTask after the EvoUI migration.

## Shell

The manager shell renders `views/module/shell.blade.php`, mounts
`<livewire:stask.module-panel>`, and loads shared assets through
`evo::partials.assets`.

Do not add package-local style or script blocks to the module panel. The shell
must not load legacy `stask.min.css`, `stask.js`, CDN bundles, or the old
manager `media/script/main.js`.

## Tabs

`ModulePanel` owns the active tab state:

- Dashboard;
- Tasks;
- Workers;
- Logs;
- Statistics.

Tabs should use the shared EvoUI tab shell so sTask stays visually aligned with
sArticles and other migrated packages.

## Tables And Lists

Tasks, workers, and logs use `livewire:evo-ui.module-table` with package presets:

```text
stask.tasks
stask.workers
stask.logs
```

The table/list switch, search, pagination, sorting, filters, status badges, row
selection, and row double-click behavior belong to EvoUI primitives.

## Actions

Use icons for compact row actions:

- eye for details;
- edit for worker settings;
- play for run task;
- power or pause for activation state.

Task details should open in the shared EvoUI modal. Dashboard recent tasks,
task rows, and log rows should all use the same detail pattern.


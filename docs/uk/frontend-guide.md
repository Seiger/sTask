# Frontend Guide

Ця сторінка фіксує UI boundary sTask після переходу на EvoUI.

## Shell

Manager shell рендерить `views/module/shell.blade.php`, монтує
`<livewire:stask.module-panel>` і підключає assets через `evo::partials.assets`.

Не додавай локальні `<style>` або `<script>` у module panel. Shell не має
вантажити legacy `stask.min.css`, `stask.js`, CDN bundles або старий
`media/script/main.js`.

## Tabs

`ModulePanel` керує активною вкладкою:

- Dashboard;
- Tasks;
- Workers;
- Logs;
- Statistics.

Вкладки мають використовувати спільний EvoUI tab shell, щоб sTask був
візуально узгоджений з sArticles та іншими перенесеними пакетами.

## Tables and lists

Tasks, workers і logs використовують `livewire:evo-ui.module-table` з package
presets:

```text
stask.tasks
stask.workers
stask.logs
```

Table/list switch, search, pagination, sorting, filters, badges, row selection і
row double-click належать EvoUI primitives.

## Actions

Для компактних row actions використовуй іконки:

- eye для details;
- edit для worker settings;
- play для run task;
- power або pause для activation state.

Деталі задачі мають відкриватися в shared EvoUI modal. Dashboard recent tasks,
task rows і log rows мають використовувати один pattern.


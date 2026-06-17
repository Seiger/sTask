# Frontend Guide

Diese Seite definiert die UI-Grenze von sTask nach der EvoUI Migration.

## Shell

Der Manager Shell rendert `views/module/shell.blade.php`, mountet
`<livewire:stask.module-panel>` und laedt Assets ueber `evo::partials.assets`.

Keine lokalen `<style>` oder `<script>` Bloecke im Panel ergaenzen. Der Shell
soll weder `stask.min.css`, `stask.js`, CDN bundles noch altes
`media/script/main.js` laden.

## Tabs

`ModulePanel` verwaltet den aktiven Tab:

- Dashboard;
- Tasks;
- Workers;
- Logs;
- Statistics.

Tabs sollen den gemeinsamen EvoUI tab shell nutzen, damit sTask zu sArticles und
anderen migrierten Paketen passt.

## Tables and lists

Tasks, workers und logs nutzen `livewire:evo-ui.module-table` mit Presets:

```text
stask.tasks
stask.workers
stask.logs
```

Table/list switch, search, pagination, sorting, filters, badges, row selection
und row double-click gehoeren zu EvoUI primitives.

## Actions

Kompakte row actions nutzen Icons:

- eye fuer details;
- edit fuer worker settings;
- play fuer run task;
- power oder pause fuer activation state.

Task details sollen im gemeinsamen EvoUI modal oeffnen.


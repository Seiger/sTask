# Frontend Guide

Ta strona opisuje granice UI sTask po migracji na EvoUI.

## Shell

Manager shell renderuje `views/module/shell.blade.php`, montuje
`<livewire:stask.module-panel>` i laduje assets przez `evo::partials.assets`.

Nie dodawaj lokalnych blokow `<style>` ani `<script>` do panelu. Shell nie
powinien ladowac `stask.min.css`, `stask.js`, CDN bundles ani starego
`media/script/main.js`.

## Tabs

`ModulePanel` zarzadza aktywna zakladka:

- Dashboard;
- Tasks;
- Workers;
- Logs;
- Statistics.

Zakladki powinny uzywac wspolnego EvoUI tab shell, aby sTask byl spojny z
sArticles i innymi przeniesionymi pakietami.

## Tables and lists

Tasks, workers i logs uzywaja `livewire:evo-ui.module-table` z presetami:

```text
stask.tasks
stask.workers
stask.logs
```

Table/list switch, search, pagination, sorting, filters, badges, row selection i
row double-click naleza do EvoUI primitives.

## Actions

Uzywaj ikon dla kompaktowych row actions:

- eye dla details;
- edit dla worker settings;
- play dla run task;
- power lub pause dla activation state.

Szczegoly zadania otwieraj przez wspolny EvoUI modal.


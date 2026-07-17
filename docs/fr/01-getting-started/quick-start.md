# Guide frontend

Cette page definit la limite UI de sTask apres migration EvoUI.

## Shell

Le manager shell rend `views/module/shell.blade.php`, monte
`<livewire:stask.module-panel>` et charge les assets via `evo::partials.assets`.

N'ajoute pas de blocs locaux `<style>` ou `<script>` dans le panel. Le shell ne
doit pas charger `stask.min.css`, `stask.js`, CDN bundles ni l'ancien
`media/script/main.js`.

## Tabs

`ModulePanel` gere l'onglet actif:

- Dashboard;
- Tasks;
- Workers;
- Logs;
- Statistics.

Les tabs doivent utiliser le EvoUI tab shell commun pour rester coherents avec
sArticles et les autres paquets migres.

## Tables and lists

Tasks, workers et logs utilisent `livewire:evo-ui.module-table` avec les presets:

```text
stask.tasks
stask.workers
stask.logs
```

Table/list switch, search, pagination, sorting, filters, badges, row selection et
row double-click appartiennent aux EvoUI primitives.

## Actions

Utilise des icones pour les row actions:

- eye pour details;
- edit pour worker settings;
- play pour run task;
- power ou pause pour activation state.

Les details de tache doivent ouvrir le meme EvoUI modal.


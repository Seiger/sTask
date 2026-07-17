# Guide developpeur

## Installation

```console
php artisan package:installrequire seiger/stask "*"
php artisan vendor:publish --tag=stask
php artisan vendor:publish --tag=evo-ui --force
php artisan migrate
```

Apres les migrations de permissions, deconnectez-vous puis reconnectez-vous au
manager.

## Architecture

sTask possede le runtime: workers, cycle de vie des taches, progression,
uploads/downloads, permissions et execution de commandes. EvoUI possede l'UI
manager commune: layout, tabs, tables, filters, badges, modals, bascule
table/liste et assets locaux.

Fichiers importants:

```text
src/Livewire/ModulePanel.php
src/Tables/TasksTableData.php
src/Tables/WorkersTableData.php
src/Tables/LogsTableData.php
src/Console/TaskWorker.php
src/Workers/BaseWorker.php
config/tasks/table.php
config/workers/table.php
config/logs/table.php
config/artisan_security.php
```

## Assets

Le shell actif doit charger `evo::partials.assets`. N'ajoutez pas de CDN ni de
fallback legacy `stask.min.css` ou `stask.js`.

## Worker API

Un worker implemente `TaskInterface` ou etend `BaseWorker`. L'action `make`
correspond a `taskMake`.

```php
public function taskMake(\Seiger\sTask\Models\sTaskModel $task, array $options = []): void
{
    $this->pushProgress($task, ['progress' => 50, 'message' => 'Working']);
    $this->markFinished($task, null, 'Done');
}
```

`meta` stocke l'entree, `result` la sortie et `message` le texte de log.

## Migration des widgets

`renderWidget()` reste une couche de compatibilite. Les nouveaux widgets doivent
suivre le contrat task-runner: entrees declaratives, creation de tache,
progression via details/logs et aucun CSS/JS local pour les elements UI communs.

## Verification

```console
composer test
git diff --check
```

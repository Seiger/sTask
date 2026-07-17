# Entwicklerhandbuch

## Installation

```console
php artisan package:installrequire seiger/stask "*"
php artisan vendor:publish --tag=stask
php artisan vendor:publish --tag=evo-ui --force
php artisan migrate
```

Nach Permission-Migrationen im Manager abmelden und neu anmelden.

## Architektur

sTask besitzt Runtime-Logik: Worker, Task-Lebenszyklus, Fortschritt,
Uploads/Downloads, Permissions und Kommandos. EvoUI besitzt die gemeinsame
Manager-Oberflaeche: Layout, Tabs, Tabellen, Filter, Badges, Modals,
Table/List-Umschaltung und lokale Assets.

Wichtige Dateien:

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

Der aktive Manager-Shell muss `evo::partials.assets` laden. Legacy
`stask.min.css`, `stask.js`, CDN Bundles oder alte Manager-Skripte duerfen nicht
als Fallback hinzugefuegt werden.

## Worker API

Worker implementieren `TaskInterface` oder erweitern `BaseWorker`. Die Aktion
`make` wird als `taskMake` umgesetzt.

```php
public function taskMake(\Seiger\sTask\Models\sTaskModel $task, array $options = []): void
{
    $this->pushProgress($task, ['progress' => 50, 'message' => 'Working']);
    $this->markFinished($task, null, 'Done');
}
```

`meta` enthaelt Eingabedaten, `result` Ausgaben und `message` den Logtext.

## Widget-Migration

`renderWidget()` bleibt eine Kompatibilitaetsschicht. Neue Widgets sollen den
Task-Runner-Vertrag verwenden: deklarative Eingaben, Task-Erstellung, Fortschritt
ueber Detailmodal/Logs und kein lokales CSS/JS fuer gemeinsame UI-Elemente.

## Pruefung

```console
composer test
git diff --check
```

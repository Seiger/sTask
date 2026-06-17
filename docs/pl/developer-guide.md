# Przewodnik dewelopera

## Instalacja

```console
php artisan package:installrequire seiger/stask "*"
php artisan vendor:publish --tag=stask
php artisan vendor:publish --tag=evo-ui --force
php artisan migrate
```

Po migracji permissions wyloguj sie i zaloguj ponownie do managera.

## Architektura

sTask posiada runtime: workery, cykl zycia zadan, progress, upload/download,
permissions i wykonywanie komend. EvoUI posiada wspolny manager UI: layout,
tabs, tables, filters, badges, modals, przelacznik table/list i lokalne assets.

Wazne pliki:

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

Aktywny shell musi ladowac `evo::partials.assets`. Nie dodawaj CDN ani legacy
`stask.min.css`/`stask.js` jako fallbackow.

## Worker

Worker implementuje `TaskInterface` albo rozszerza `BaseWorker`. Akcja `make`
mapuje sie na `taskMake`.

```php
public function taskMake(\Seiger\sTask\Models\sTaskModel $task, array $options = []): void
{
    $this->pushProgress($task, ['progress' => 50, 'message' => 'Working']);
    $this->markFinished($task, null, 'Done');
}
```

`meta` przechowuje dane wejsciowe, `result` wynik, a `message` log.

## Migracja widgetow

`renderWidget()` jest kompatybilnoscia. Nowe widgety powinny uzywac kontraktu
task-runner: deklaratywne inputy, stworzenie zadania, progress przez modal
szczegolow/logi i brak lokalnego CSS/JS dla wspolnych elementow UI.

## Testy

```console
composer test
git diff --check
```

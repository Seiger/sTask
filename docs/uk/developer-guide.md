# Гайд розробника

## Перевірка встановлення

Виконуйте з директорії Evolution CMS `core`:

```console
php artisan package:installrequire seiger/stask "*"
php artisan vendor:publish --tag=stask
php artisan vendor:publish --tag=evo-ui --force
php artisan migrate
```

Після міграцій permissions потрібно вийти і зайти в менеджер знову.

## Архітектура

sTask володіє runtime-логікою, EvoUI - візуальною оболонкою менеджера.

- sTask: воркери, життєвий цикл задач, прогрес, uploads/downloads, permissions і командне виконання.
- EvoUI: layout, tabs, tables, filters, badges, modals, table/list switching і локальні assets.
- Legacy widgets лишаються сумісністю до перенесення на task-runner primitive.

Важливі файли:

```text
module/sTaskModule.php
src/sTaskServiceProvider.php
src/Livewire/ModulePanel.php
src/Tables/TasksTableData.php
src/Tables/WorkersTableData.php
src/Tables/LogsTableData.php
src/Console/TaskWorker.php
src/Workers/BaseWorker.php
src/Workers/ArtisanWorker.php
src/Workers/ComposerUpdateWorker.php
config/tasks/table.php
config/workers/table.php
config/logs/table.php
config/artisan_security.php
```

## Модуль менеджера і assets

Активна EvoUI оболонка має підключати `evo::partials.assets` і не має вантажити
legacy `stask.min.css`, `stask.js`, CDN bundles або старий manager main script.
Якщо UI без стилів, перевірте assets, а не додавайте fallback.

## Контракт воркера

Воркери реалізують `TaskInterface` або наслідують `BaseWorker`.

```php
public function identifier(): string;
public function scope(): string;
public function title(): string;
public function description(): string;
public function settings(): array;
```

Дія `make` відповідає методу:

```php
public function taskMake(\Seiger\sTask\Models\sTaskModel $task, array $options = []): void
{
    $this->pushProgress($task, [
        'progress' => 25,
        'message' => 'Preparing data',
    ]);

    $this->markFinished($task, null, 'Done');
}
```

## Дані задачі

Задача зберігає `identifier`, `action`, `status`, `message`, `started_by`,
`meta`, `result`, часові поля, `attempts`, `max_attempts`, `priority` і
`progress`. `meta` використовуйте для вхідних структурованих даних, `result` -
для результату.

## Прогрес, uploads і downloads

Для довгих задач викликайте `pushProgress()`. Контролер дій підтримує upload до
задачі, upload до воркера, chunked upload і download результату завершеної
задачі.

## Artisan security

`config/artisan_security.php` визначає небезпечні, заборонені, дозволені і ті,
що потребують підтвердження, команди. Не обходьте цей шар у custom widgets.

## Міграція widgets

`renderWidget()` - це compatibility surface. Нові віджети мають рухатись до
task-runner contract: декларативні inputs, створення task action, прогрес через
деталі/логи, без inline CSS/JS для спільних елементів UI.

## Перевірки

```console
composer test
php -l src/Tables/TasksTableData.php
php -l src/Tables/WorkersTableData.php
php -l src/Tables/LogsTableData.php
```

Smoke: відкрийте модуль, перемкніть Dashboard/Tasks/Workers/Logs/Statistics,
перевірте table/list, відкрийте деталі задачі і переконайтесь, що CSS/JS assets
віддаються як assets, а не як HTML error page.

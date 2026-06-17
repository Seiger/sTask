# Конфігурація

Ця сторінка потрібна для встановлення sTask і перевірки модуля менеджера,
table presets, команд і пошуку воркерів.

## Встановлення і publish

Команди запускаються з директорії Evolution CMS `core`.

```console
php artisan package:installrequire seiger/stask "*"
php artisan vendor:publish --tag=stask
php artisan vendor:publish --tag=evo-ui --force
php artisan migrate
```

Після міграцій прав треба вийти з менеджера і зайти знову. Права Evolution
можуть залишатися в кеші до нового session.

## Реєстрація модуля

`Seiger\sTask\sTaskServiceProvider` додає `config/sTaskCheck.php` у
`cms.settings`. Там важливі назва модуля, іконка, порядок і package alias.

Якщо пакет встановлений, але модуля не видно, спершу перевіряй `cms.settings`,
запис модуля Evolution і права менеджера.

## Table presets

sTask має три EvoUI presets:

| Config key | Файл | Поверхня |
| --- | --- | --- |
| `stask.tasks.table` | `config/tasks/table.php` | Таблиця/список задач. |
| `stask.workers.table` | `config/workers/table.php` | Таблиця/список воркерів. |
| `stask.logs.table` | `config/logs/table.php` | Логи і деталі. |

Фільтри задач мають бути стандартними EvoUI multi-select там, де можна вибрати
декілька значень. Не копіюй single-select фільтри з articles, бо там інша модель
типів.

## Worker discovery

`WorkerDiscovery` шукає worker класи і враховує `excluded_namespaces`. Кожен
custom worker має стабільно віддавати identifier, scope, title, description,
settings і мапу `handles`.

## Безпека команд

`config/artisan_security.php` керує allowed, forbidden, dangerous і
confirmation-required commands для `ArtisanWorker`. UI має запускати Artisan
через цей config, а не через кастомні кнопки в обхід.

## Команди

```console
php artisan stask:worker
php artisan stask:publish
php artisan stask:publish --no-prune
```

`stask:publish --no-prune` корисний для діагностики published files, коли не
треба видаляти локальні файли перевірки.


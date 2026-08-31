# Требования, установка и обновления

## Требования

Текущая ветка 2.x `composer.json` требует:

| Компонент | Требование |
| --- |----------------------------|
| PHP | <code>&#94;8.4</code> |
| Evolution CMS | <code>&#94;3.5.7</code> |
| evo-ui | <code>&#94;1.2</code> |
| Композитор | доступно в каталоге `core` |

HTML-сущность для caret используется намеренно: dDocs не преобразует её в верхний индекс и показывает точное ограничение Composer.

Автоматическая обработка очередей требует системного cron или другого планировщика, который запускает планировщик Laravel каждую минуту. Чтобы выполнить задачу непосредственно из интерфейса, процесс PHP должен также иметь доступ к `exec()` или `shell_exec()`; Если они не разрешены, запись в очереди всё равно будет создана и обработана следующим проходом CRON.

## Инсталляция через Composer

Команды выполняются из каталога CMS `core` Evolution:

```bash
cd /var/www/example/core
composer require seiger/stask:"2.x-dev"
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

Что должно произойти:

1. Обнаружение посылок Laravel соединяет `Seiger\sTask\sTaskServiceProvider` и псевдоним `sTask`.
2. `migrate` запускает пакетные миграции, которые провайдер добавил через `loadMigrationsFrom()`.
3. `package:discover` обновляет манифест обнаружения посылок Laravel; Composer classmap создаётся во время установки/обновления или `composer dump-autoload`.
4. `stask:publish` копирует CSS, JavaScript и SVG в `assets/site`.
5. Полная очистка кэша удаляет старые метаданные Менеджера, Маршрутов и Пакетов.

Не редактировать файлы в `core/vendor/seiger/stask`: Composer заменит их во время обновления.

## Поставщик услуг

Провайдер автоматически:

- регистры Singleton `Seiger\sTask\sTask` и псевдоним `sTask`;
- регистры `WorkerService`, `MetricsService`, `SupervisorService`;
- загружает миграции, трансляции, просмотры Blade, маршруты менеджера и компоненты Livewire;
- соединяет таблицные пресеты `stask.tasks`, `stask.workers`, `stask.logs`;
- создаёт `storage/stask`, если каталога ещё нет;
- регистрирует `stask:worker` и `stask:publish` в CLI;
- добавляет `stask:worker` в планировщик Laravel с частотой один раз в минуту.

Меню Manager добавляет пакетный плагин `plugins/sTaskPlugin.php` к событию `evolution.OnManagerMenuPrerender` только тогда, когда у пользователя есть разрешение `stask`. Он ведёт по названному маршруту `sTask.index` и использует `sTaskServiceProvider::MODULE_ICON`. Файл `module/sTaskModule.php` — это защищённая обёртка для записи модуля Evolution и отображает тот же контроллер. Защищённый метод `registerManagerModule()` присутствует в провайдере для потока установщика/модуля, но не вызывает `boot()` него напрямую; Не полагайтесь на ручной вызов этого метода.

## Миграции и разрешения

Пакет создаёт три таблицы: `s_workers`, `s_tasks`, `s_supervisor_states`. Отдельная идемпотентная миграция создаёт группу разрешений `sTask`, ключ разрешения `stask` и добавляет её роль `1`, если существуют соответствующие системные таблицы.

Проверка:

```bash
php artisan migrate:status
php artisan route:list --path=stask
```

В менеджере пользователю требуется разрешение `stask`. Маршруты менеджера дополнительно защищены группой промежуточного программного обеспечения `mgr`; это не публичный HTTP-API.

## Пост-активы

Команда:

```bash
php artisan stask:publish
```

в частности, публикует:

- `assets/site/stask.svg`;
- `assets/site/stask.min.css`;
- `assets/site/stask-module.css`;
- `assets/site/stask-module.js`;
- `assets/site/stask.js`;
- `assets/site/seigerit.tooltip.js`.

Если модуль открывается без стилей или live progress не обновляется, сначала повторите publish и `cache:clear-full`, затем проверьте HTTP 200 для этих ассетов.

## Крон

Рекомендуемая запись для продакшена:

```cron
* * * * * cd /var/www/example/core && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Проверьте абсолютные пути:

```bash
command -v php
cd /var/www/example/core && php artisan schedule:list
cd /var/www/example/core && php artisan stask:worker
```

Не запускайте несколько неконтролируемых записей cron для одной установки. Обычные задачи не имеют глобального блокировки претензий, поэтому параллельные `stask:worker` могут создавать риск конкурентного исполнения.

## Обновление 2.x

```bash
cd /var/www/example/core
composer update seiger/stask --with-dependencies
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

После обновления проверьте фактический источник:

```bash
composer show seiger/stask --all
```

Если пакетная документация не отображается в dDocs, проверьте не только `docs` в репозитории Git, но и физическую директорию `core/vendor/seiger/stask/docs/uk`. Composer lock/dist может оставаться на старом коммите.

## Откат назад

Перед обновлением сделайте резервную копию базы данных и `core/composer.lock`. Откатите код с помощью Composer до проверенной ссылки. Откатить схему базы данных можно только по отдельному плану после проверки фактического набора миграций в установленной версии пакета и последствий для производственных данных.

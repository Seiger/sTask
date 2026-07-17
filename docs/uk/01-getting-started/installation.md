# Вимоги, встановлення та оновлення

## Вимоги

Поточний `composer.json` гілки 2.x вимагає:

| Компонент | Вимога                     |
| --- |----------------------------|
| PHP | <code>&#94;8.4</code> |
| Evolution CMS | <code>&#94;3.5.7</code> |
| evo-ui | <code>&#94;1.0.6</code> |
| Composer | доступний у каталозі `core` |

HTML entity для caret використано навмисно: dDocs не перетворює її на superscript і показує точний Composer constraint.

Для автоматичної обробки черги потрібен системний cron або інший планувальник, який щохвилини запускає Laravel scheduler. Для запуску завдання одразу з UI PHP-процес також повинен мати доступ до `exec()` або `shell_exec()`; якщо вони заборонені, запис у черзі все одно створиться і буде оброблений наступним cron-проходом.

## Встановлення через Composer

Команди виконуються з каталогу `core` Evolution CMS:

```bash
cd /var/www/example/core
composer require seiger/stask:"2.x-dev"
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

Що має відбутися:

1. Laravel package discovery підключає `Seiger\sTask\sTaskServiceProvider` і alias `sTask`.
2. `migrate` запускає пакетні міграції, які provider додав через `loadMigrationsFrom()`.
3. `package:discover` оновлює Laravel package discovery manifest; Composer classmap будується під час install/update або `composer dump-autoload`.
4. `stask:publish` копіює CSS, JavaScript і SVG у `assets/site`.
5. повне очищення cache прибирає старі manager views, routes та package metadata.

Не редагуйте файли в `core/vendor/seiger/stask`: Composer замінить їх під час оновлення.

## Service provider

Provider автоматично:

- реєструє singleton `Seiger\sTask\sTask` і alias `sTask`;
- реєструє `WorkerService`, `MetricsService`, `SupervisorService`;
- завантажує міграції, переклади, Blade views, manager routes і Livewire-компонент;
- підключає table presets `stask.tasks`, `stask.workers`, `stask.logs`;
- створює `storage/stask`, якщо каталогу ще немає;
- реєструє `stask:worker` і `stask:publish` у CLI;
- додає `stask:worker` у Laravel scheduler з частотою once per minute.

Manager menu додає пакетний plugin `plugins/sTaskPlugin.php` на події `evolution.OnManagerMenuPrerender`, лише коли користувач має permission `stask`. Він веде на named route `sTask.index` і використовує `sTaskServiceProvider::MODULE_ICON`. Файл `module/sTaskModule.php` є guarded wrapper для Evolution module entry і render-ить той самий controller. Protected method `registerManagerModule()` присутній у provider для installer/module flow, але `boot()` його напряму не викликає; не покладайтеся на ручний виклик цього method.

## Міграції і permission

Пакет створює три таблиці: `s_workers`, `s_tasks`, `s_supervisor_states`. Окрема ідемпотентна міграція створює permission group `sTask`, permission key `stask` і додає його ролі `1`, якщо відповідні системні таблиці існують.

Перевірка:

```bash
php artisan migrate:status
php artisan route:list --path=stask
```

У менеджері користувачеві потрібен permission `stask`. Manager routes додатково захищені middleware-групою `mgr`; це не публічний HTTP API.

## Публікація assets

Команда:

```bash
php artisan stask:publish
```

публікує зокрема:

- `assets/site/stask.svg`;
- `assets/site/stask.min.css`;
- `assets/site/stask-module.css`;
- `assets/site/stask-module.js`;
- `assets/site/stask.js`;
- `assets/site/seigerit.tooltip.js`.

Якщо модуль відкривається без стилів або live progress не оновлюється, спочатку повторіть publish і `cache:clear-full`, потім перевірте HTTP 200 для цих assets.

## Cron

Рекомендований production-запис:

```cron
* * * * * cd /var/www/example/core && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Перевіряйте абсолютні шляхи:

```bash
command -v php
cd /var/www/example/core && php artisan schedule:list
cd /var/www/example/core && php artisan stask:worker
```

Не запускайте кілька неконтрольованих cron-записів для тієї самої інсталяції. Звичайні tasks не мають глобального claim-lock, тому паралельні `stask:worker` можуть створити ризик конкурентного виконання.

## Оновлення 2.x

```bash
cd /var/www/example/core
composer update seiger/stask --with-dependencies
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

Після оновлення звірте фактичний source reference:

```bash
composer show seiger/stask --all
```

Якщо пакетна документація не з’явилася в dDocs, перевірте не лише наявність `docs` в Git-репозиторії, а фізичний каталог `core/vendor/seiger/stask/docs/uk`. Composer lock/dist може залишатися на старому commit.

## Відкат

Перед оновленням зробіть резервну копію БД і `core/composer.lock`. Відкат коду виконуйте Composer-ом до перевіреного reference. Схему БД відкочуйте лише за окремим планом після перевірки фактичного набору міграцій у встановленій версії пакета та наслідків для робочих даних.

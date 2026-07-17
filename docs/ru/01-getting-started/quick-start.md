# Быстрый старт

## Требования

- PHP <code>&#94;8.4</code>;
- Evolution CMS <code>&#94;3.5.7</code>;
- `evolution-cms/evo-ui` <code>&#94;1.0.6</code>;
- cron или другой планировщик для ежеминутного запуска Laravel scheduler.

## Установка

Выполняйте команды в каталоге `core`:

```bash
composer require seiger/stask
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

Выйдите из менеджера Evolution CMS и войдите снова, чтобы обновился кэш прав. Пользователю требуется permission `stask`.

## Первый запуск

```bash
php artisan stask:worker
```

Команда создаёт задания по расписаниям, выполняет готовые queued-записи и проверяет supervisor-воркеры. Для постоянной работы запускайте Laravel scheduler каждую минуту:

```cron
* * * * * cd /path/to/site/core && php artisan schedule:run >> /dev/null 2>&1
```

## Проверка результата

1. Откройте модуль **sTask** в менеджере.
2. Убедитесь, что доступны вкладки Панель, Задания, Воркеры, Логи и Статистика.
3. Во вкладке Воркеры обновите реестр после установки новых пакетов.
4. Проверьте, что новые задания переходят из queued в completed или failed.

Если задание остаётся в очереди, сначала проверьте cron, `php artisan schedule:list`, активность воркера и его Composer autoload.

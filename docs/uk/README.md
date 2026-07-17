# sTask 2.x

sTask — пакет Evolution CMS для керування фоновими завданнями. Він зберігає чергу в базі даних, знаходить воркери через Composer, запускає їх командою `stask:worker`, показує прогрес і журнал у менеджері та окремо наглядає за довготривалими supervisor-процесами.

Документація описує фактичну гілку `2.x`. Вона не припускає наявності зовнішньої черги, SSE або WebSocket: live progress у менеджері читається періодичними HTTP-запитами з файлових журналів `storage/stask/{taskId}.log`.

## Кому читати

- **Адміністратору** — встановлення, права, cron, вкладки менеджера, діагностика і production-експлуатація.
- **Інтегратору** — розклади, реєстрація воркерів, маршрути менеджера, міграції та оновлення.
- **PHP-розробнику** — `TaskInterface`, `BaseWorker`, фасад `sTask`, progress API і supervisor contract.

## Карта документації

1. Початок роботи
   - [Вимоги, встановлення та оновлення](01-getting-started/installation.md)
   - [Швидкий старт](01-getting-started/quick-start.md)
2. Концепції
   - [Архітектура і життєвий цикл](02-concepts/architecture-and-lifecycle.md)
   - [Розклади](02-concepts/schedules.md)
   - [Supervisor-процеси](02-concepts/supervisor.md)
3. Менеджер Evolution CMS
   - [Панель, Завдання, Воркери, Логи та Статистика](03-manager/interface.md)
4. Розробка
   - [Фасад і PHP API](04-development/public-api.md)
   - [Власний воркер](04-development/custom-worker.md)
   - [Маршрути, progress-файли і завантаження](04-development/routes-and-progress.md)
5. Експлуатація
   - [Production-рекомендації](05-operations/production.md)
   - [Діагностика](05-operations/troubleshooting.md)
   - [Перехід з 1.x на 2.x](05-operations/upgrade-1-to-2.md)
6. Довідник
   - [Конфігурація](06-reference/configuration.md)
   - [Таблиці бази даних](06-reference/database.md)
   - [CLI, статуси й маршрути](06-reference/cli-statuses-routes.md)
   - [FAQ](06-reference/faq.md)

## Межі відповідальності

sTask виконує воркер послідовно в процесі `stask:worker`. Пакет не надає гарантії exactly-once, розподіленого брокера, автоматичного завершення OS-процесу кнопкою emergency stop або зберігання всіх progress-повідомлень у БД. Такі вимоги реалізує прикладний воркер та інфраструктура проєкту.

## Перша перевірка

Після встановлення виконайте:

```bash
cd core
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan stask:worker
```

Очікуваний результат: міграції створили `s_workers`, `s_tasks` і `s_supervisor_states`, пакетні assets опубліковані, а команда воркера завершилася з повідомленням про кількість створених і оброблених завдань. Далі відкрийте модуль **sTask** у менеджері.

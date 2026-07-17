# Переход с sTask 1.x на 2.x

Автоматической универсальной миграции custom workers нет. Выполняйте переход как аудит фактических классов, схемы и UI.

## Контрольный список

1. Сделайте резервную копию БД и `core/composer.lock`.
2. Зафиксируйте текущие worker identifiers, actions и расписания.
3. Проверьте namespaces, Composer autoload и наследование `BaseWorker`/`TaskInterface`.
4. Перенесите длительную работу в методы `task<Action>`.
5. Используйте `meta` для входа, `result` для выхода и `pushProgress()` для контрольных точек.
6. Не рассчитывайте на отдельный cancelled status или автоматический retry.
7. Перепроверьте permission `stask`, cron и опубликованные assets.
8. Если воркер управляет daemon, добавьте `SupervisorWorkerInterface` и уникальный `supervisorKey()`.

## UI

В 2.x общие таблицы, фильтры, badges, modals и режимы таблица/список предоставляет EvoUI. Не переносите legacy inline CSS/JavaScript в новый manager shell. `renderWidget()` остаётся compatibility surface только для специфичного UI.

## Проверка после обновления

```bash
composer dump-autoload
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
php artisan stask:worker
```

Проверьте создание нового задания, progress, final result, фильтры, двойной клик, emergency stop и один полный cron-проход.

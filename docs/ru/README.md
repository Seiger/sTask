# Документация sTask 2.x

sTask — пакет Evolution CMS для фоновых заданий. Он обнаруживает классы воркеров через Composer, хранит очередь в базе данных, запускает задания командой `stask:worker`, показывает прогресс и журнал в менеджере и контролирует состояние длительных supervisor-процессов.

Эта локаль описывает фактический контракт ветки `2.x`. Каноническая и наиболее полная версия документации находится в каталоге `docs/uk`.

## Разделы

- [Быстрый старт](01-getting-started/quick-start.md)
- [Архитектура и жизненный цикл](02-concepts/architecture-and-lifecycle.md)
- [Интерфейс менеджера](03-manager/interface.md)
- [PHP API и разработка воркеров](04-development/public-api.md)
- [Диагностика](05-operations/troubleshooting.md)
- [Переход с 1.x на 2.x](05-operations/upgrade-1-to-2.md)
- [CLI, статусы и маршруты](06-reference/cli-statuses-routes.md)
- [Конфигурация](06-reference/configuration.md)

## Важные ограничения

- очередь обрабатывается последовательно одним процессом `stask:worker`;
- exactly-once и распределённый брокер пакет не предоставляет;
- live progress работает через HTTP polling файлов `storage/stask/{taskId}.log`, а не через SSE или WebSocket;
- emergency stop меняет состояние записи задания, но не завершает PHP/OS-процесс;
- не храните пароли, токены и другие секреты в `meta`, `result`, message или progress log.

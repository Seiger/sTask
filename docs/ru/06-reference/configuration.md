# Конфигурация

## Регистрация пакета

Composer автоматически регистрирует `Seiger\sTask\sTaskServiceProvider` и facade alias `sTask`. Provider загружает migrations, translations, views, routes, команды, scheduler callback и EvoUI presets.

## Основные файлы

| Файл | Назначение |
| --- | --- |
| `config/sTaskCheck.php` | manager registration и базовые настройки |
| `config/tasks/table.php` | таблица Задания |
| `config/workers/table.php` | таблица Воркеры и modal настроек |
| `config/logs/table.php` | таблица Логи и readonly details |
| `config/artisan_security.php` | правила выполнения Artisan commands |

## Расписания

Schedule хранится в `s_workers.settings.schedule`. Поддерживаются manual, once, periodic, regular и supervisor. Supervisor доступен только совместимым классам.

## Worker settings

`BaseWorker::getConfig()`, `setConfig()` и `updateConfig()` читают и сохраняют package-specific settings. `updateSettings()` использует shallow merge: вложенный массив может быть заменён целиком.

Не храните credentials в данных, которые показываются в manager modal. Секреты размещайте в environment/config и передавайте воркеру по защищённому каналу.

## Production

- запускайте scheduler каждую минуту;
- не допускайте параллельных долгих проходов без внешней блокировки;
- настройте process manager для daemon workers;
- контролируйте рост `s_tasks`, `s_supervisor_states` и `storage/stask`;
- после обновления публикуйте assets и очищайте cache.

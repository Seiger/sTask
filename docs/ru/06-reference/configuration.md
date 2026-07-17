# Конфигурация

## `config/sTaskCheck.php`

Слияется в `cms.settings`:

| Ключ | По умолчанию | Значение |
| --- | --- | --- |
| `check_sTask` | `true` | Флаг присутствия/проверки посылки |
| `sTaskVer` | `2.9999999.9999999.9999999-dev` | Маркер версии ветки разработки |

Это не настройка рабочего времени.

## Пресеты таблиц

| Файл | Ключ конфигурации | Поверхность |
| --- | --- | --- |
| `config/tasks/table.php` | `stask.tasks.table` | Задачи |
| `config/workers/table.php` | `stask.workers.table` | Рабочие |
| `config/logs/table.php` | `stask.logs.table` | Логи |

Пресеты определяют провайдера, методы провода, пагинацию, представления, фильтры, столбцы, модаль и действия. Для переопределения проекта используйте пользовательский механизм конфигурации Evolution или публикую/расширенную точку, если это поддерживается вашей версией; Не редактируйте файлы поставщиков.

## `config/excluded_namespaces.php`

Список префиксов пространства имён, которые `WorkerDiscovery` не учитывает. По умолчанию пространства фреймворков/поставщиков, такие как `Illuminate\`, `Symfony\`, `EvolutionCMS\Legacy\`, `PHPUnit\` и др., исключаются.

Если ваш работник находится под исключённым префиксом, переместите его в пространство имён package/project. Не сокращайте список без анализа: Discovery может начать создавать тысячи сторонних классов.

## `config/artisan_security.php`

Использовались `ArtisanWorker`:

| Ключ | По умолчанию |
| --- | --- |
| `dangerous_commands` | `migrate:fresh`, `migrate:reset`, `db:wipe` |
| `confirmation_required` | `migrate`, `migrate:refresh`, `migrate:rollback`, `db:seed`, `cache:clear-full` |
| `whitelist` | пусто: все, кроме заблокированного |
| `blacklist` | пусто |
| `enabled` | `true` |
| `log_executions` | `true` |
| `required_permission` | `run_artisan` |

Шаблоны из белого списка поддерживают `*` комментариев по контракту. В производстве оставьте безопасность включённой и создайте явный белый список для операционных нужд.

## Рабочие настройки

Хранится в `s_workers.settings` JSON:

```json
{
  "schedule": {
    "enabled": true,
    "type": "periodic",
    "datetime": "",
    "frequency": "hourly",
    "time": "*:10",
    "start_time": "",
    "end_time": ""
  },
  "http": {
    "timeout": 15
  }
}
```

`BaseWorker` API:

```php
$worker->settings();
$worker->getConfig('http.timeout', 10);
$worker->setConfig('http.timeout', 20);
$worker->updateConfig(['endpoint' => 'https://example.test']);
$worker->getSchedule();
$worker->shouldRunNow();
```

`TaskWorker` самостоятельно рассчитывает следующий забег; `shouldRunNow()` является помощником на стороне работника и не является основным решением планировщика в CLI.

## Сервисный контейнер

Синглтоны:

```php
app(Seiger\sTask\sTask::class);
app(Seiger\sTask\Services\WorkerService::class);
app(Seiger\sTask\Services\MetricsService::class);
app(Seiger\sTask\Services\SupervisorService::class);
```

Аксессуар фасада: `sTask`.

## Хранилище

| Путь | Данные |
| --- | --- |
| `core/storage/stask/{id}.log` | Прогресс только в приложениях |
| `core/storage/stask/uploads` | Контроллер/рабочий файлы загрузки/результатов |
| Cache Laravel | Экземпляры работников, метрики, блокировки супервайзера |

Провайдер создаёт только корневую `storage/stask`. Подкаталоги создаются по соответствующему пути кода.

## Метаданные пакета для dDocs

dDocs гласит:

- Имя композитора `seiger/stask`;
- локализованные `lang/{locale}/global.php` тональности `module_title`, `module_description`, `module_icon`;
- `docs/{locale}`.

Ожидаемые метаданные:

```php
'module_title' => 'sTask',
'module_icon' => 'tabler-progress-check',
```

`docs/docs.json` — переносной инвентарный манифест. Текущее время выполнения dDocs может не использовать манифест напрямую; Обнаружение обеспечивает сканирование пакета Composer и физическое локализованное дерево документации.

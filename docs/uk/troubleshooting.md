# Діагностика

Ця сторінка допомагає швидко знайти типові проблеми sTask manager і міграції
воркерів.

## Модуль не видно

Перевір, що `Seiger\sTask\sTaskServiceProvider` є в `composer.json`, пакет
встановлений у demo site, а запис модуля Evolution існує. Якщо щойно були
міграції прав, вийди з менеджера і зайди знову.

## Менеджер без стилів

Зазвичай це означає, що CSS/JS не опублікувались або замість asset вантажиться
HTML error response. Перепублікуй локальні assets:

```console
php artisan vendor:publish --tag=evo-ui --force
php artisan vendor:publish --tag=stask --force
```

EvoUI shell має підключати `evo::partials.assets`. Він не має вантажити
`stask.min.css`, CDN bundles або старі manager scripts.

## Access denied

Спершу онови manager session: logout/login. Потім перевір права модуля і
реєстрацію `cms.settings` з `sTaskCheck`.

## Worker class not found

`WorkerClassNotFoundException` означає, що клас не autoloadиться. Перевір
Composer autoload, namespace і `excluded_namespaces`.

## Worker contract invalid

`WorkerInvalidInterfaceException` означає, що клас не відповідає worker
contract. Найкраще наслідувати `BaseWorker` і явно описувати identity methods та
`handles`.

## Деталі задачі не відкриваються

Задачі і логи мають відкривати деталі через спільну EvoUI modal. Перевір row
action для details і що `LogsTableData` може завантажити вибрану задачу.

## Artisan команда заблокована

Перевір `config/artisan_security.php`. Dangerous або forbidden commands не можна
обходити через custom widgets.


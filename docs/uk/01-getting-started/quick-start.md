# Швидкий старт

Цей сценарій проводить від чистого встановлення до першого виконаного завдання без вигаданих API.

## 1. Перевірте пакет

```bash
cd core
php artisan route:list --path=stask
php artisan stask:worker
```

Друга команда може вивести `created 0 scheduled task(s), processed 0 task(s)` — для порожньої черги це нормальний результат.

## 2. Оновіть реєстр воркерів

Відкрийте **sTask → Воркери** і натисніть кнопку з іконкою `database-cog` (**Оновити реєстр воркерів**). Discovery читає `vendor/composer/autoload_classmap.php`, відкидає виключені namespaces і реєструє concrete-класи, що реалізують `TaskInterface`.

Новий воркер створюється неактивним. Увімкніть його кнопкою живлення або в модальному вікні редагування.

## 3. Запустіть вручну

Для активного воркера з методом `taskMake()` натисніть `player-play`. sTask:

1. створить `s_tasks` зі status `10`;
2. запише перший рядок у `storage/stask/{id}.log`;
3. спробує запустити `php core/artisan stask:worker` у фоні;
4. покаже live progress у рядку таблиці через адаптивний HTTP polling.

Якщо `exec`/`shell_exec` вимкнені, виконайте команду вручну:

```bash
php artisan stask:worker
```

## 4. Перевірте результат

На вкладці **Завдання** знайдіть запис за ID, назвою воркера або action. Очікувана послідовність status:

```text
10 queued → 50 running → 80 finished
```

Status `30 preparing` визначений моделлю і може використовуватися прикладним кодом, але стандартний `TaskWorker` переходить із queued безпосередньо в running.

Подвійний клік по рядку відкриває readonly modal з message, meta і result. Окреме посилання ID є на вкладці **Логи** та веде на повну сторінку task details.

## 5. Створіть task з PHP

Фасад повертає існуючий активний duplicate або нову модель:

```php
<?php

use Seiger\sTask\Facades\sTask;

$task = sTask::create(
    identifier: 'inventory_sync',
    action: 'make',
    data: ['warehouse' => 12, 'force' => false],
    priority: 'normal',
    userId: evo()->getLoginUserID() ?: null,
);

echo $task->id;
```

Важливо: `create()` лише ставить запис у чергу. Для виконання потрібен `stask:worker` або виклик `sTask::execute($task)` у контрольованому процесі.

## 6. Налаштуйте автоматичний запуск

У modal воркера ввімкніть **Автоматичний запуск** і виберіть schedule. Наприклад, щогодини на 15-й хвилині:

```json
{
  "schedule": {
    "enabled": true,
    "type": "periodic",
    "frequency": "hourly",
    "time": "*:15",
    "datetime": "",
    "start_time": "",
    "end_time": ""
  }
}
```

Наступний `stask:worker` створить майбутній queued task з `start_at`. Поки час не настав, task видимий у **Завдання**, але не виконується.

## Контрольний список

- package source reference відповідає очікуваній гілці 2.x;
- міграції успішні;
- permission `stask` призначений потрібній manager role;
- `storage/stask` доступний на запис web user і CLI user;
- cron запускає scheduler щохвилини;
- worker активний, class існує, identifier унікальний;
- task переходить у final status і має `finished_at`.

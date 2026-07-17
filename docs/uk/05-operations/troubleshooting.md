# Діагностика

Починайте з evidence: package reference, routes, migrations, scheduler, DB row, progress file, application log. Не робіть висновок лише з UI badge.

## sTask не видно в модулі «Документація»

1. Перевірте фізичний artifact:

   ```bash
   test -f core/vendor/seiger/stask/docs/uk/README.md
   ```

2. Звірте lock/source reference:

   ```bash
   cd core
   composer show seiger/stask --all
   ```

3. Переконайтеся, що dDocs має `scan_vendor_packages = 1`.
4. Очистіть index/application cache.
5. Перевірте package metadata у `lang/{locale}/global.php`: `module_title`, `module_description`, `module_icon`.

dDocs автоматично сканує packages `seiger/*`. Не додавайте vendor root до `extra_docs_roots`: configured project root може стати writable у viewer.

Якщо в Git repo docs є, а у `core/vendor` немає — проблема в Composer lock/dist/deploy, не у Markdown index.

## dDocs падає до індексації

Перевірте stack trace стороннього documentation package. Locale alias file, service provider або cache можуть падати до того, як sTask source буде прочитаний. Це окремий defect dDocs/environment; sTask docs не можуть виправити чужий bootstrap.

## Модуль sTask не видно

- `composer show seiger/stask`;
- provider присутній у package discovery;
- permission `stask` призначений role;
- migrations пройшли;
- manager cache очищений;
- module/plugin registration завершена installer-ом Evolution CMS.

Provider має метод manager registration, але фактичний module entry може також керуватися Evolution package installer/plugin. Перевіряйте database module record і package discovery, а не викликайте protected method вручну.

## Модуль без стилів або JavaScript

```bash
cd core
php artisan stask:publish
php artisan cache:clear-full
```

У browser Network перевірте `stask-module.css`, `stask-module.js`, `stask.min.css`. Якщо deploy використовує readonly release filesystem, assets треба публікувати під час build.

## `stask:worker` не запускається

```bash
php -v
php artisan list | grep stask
php artisan route:list --path=stask
```

Пакет вимагає PHP 8.4. Перевірте, що CLI і FPM використовують однаковий release, `.env`, extensions і permissions.

## Task queued і не виконується

Перевірте:

```sql
SELECT id, identifier, action, status, start_at, created_at
FROM s_tasks
WHERE status IN (10, 30, 50)
ORDER BY id;
```

- cron реально виконується;
- `start_at` не в майбутньому;
- worker active;
- class існує і implement-ить `TaskInterface`;
- application log не містить resolution exception;
- немає зовнішнього lock, що постійно зайнятий.

## Schedule не створює task

- `settings.schedule.enabled = true`;
- type не `manual`;
- concrete worker має `taskMake()`;
- немає incomplete task цього identifier;
- datetime once у майбутньому;
- weekly має `days`;
- regular window валідне і не overnight;
- `stask:worker` проходить щохвилини.

## Worker не з’являється

```bash
composer dump-autoload
php artisan package:discover
```

Потім refresh registry. Class має бути concrete і в Composer classmap. PSR-4 class, який Composer не оптимізував у classmap, може не знайтися поточною discovery-реалізацією до authoritative/optimized dump.

Перевірте `config/excluded_namespaces.php`: великі framework namespaces навмисно пропускаються.

## `WorkerClassNotFound` / `WorkerInvalidInterface`

- class name у `s_workers.class` правильний;
- autoload актуальний;
- class не abstract;
- class реалізує `TaskInterface`;
- constructor не падає через DB/settings dependency.

Не натискайте clean orphaned під час часткового deploy: record може бути видалений, поки class тимчасово недоступний.

## Live progress 404

404 означає, що `storage/stask/{id}.log` не знайдено. Task може ще бути queued або write міг тихо не вдатися.

```bash
ls -la core/storage/stask
```

Звірте user/group для FPM і cron. Подивіться DB status/message та application log.

## Progress завис, task finished

Progress file append-only і не є source of truth для final DB status. Live watcher зупиняється на terminal string у останньому рядку. Якщо custom worker фіналізував БД, але не викликав `markFinished()`/не записав final snapshot, UI оновиться після Livewire refresh, але file може залишитися running.

## Emergency stop не зупинив process

Це очікувана межа: дія лише переводить DB record у failed. Знайдіть process за інфраструктурною телеметрією, зупиніть його штатно, перевірте side effects, потім запускайте новий task.

## Supervisor restart loop

- startup grace занадто малий;
- inspection не розпізнає starting;
- heartbeat timeout коротший за реальну частоту;
- fingerprint змінюється на кожному pass через timestamp/random text;
- process також рестартить systemd;
- start/restart не detached.

Fingerprint має бути стабільним для однакової діагностики.

## Supervisor state росте

```sql
SELECT worker_id, identifier, supervisor_key, COUNT(*)
FROM s_supervisor_states
GROUP BY worker_id, identifier, supervisor_key;
```

Unique `key_hash` запобігає росту для тієї самої пари, але новий worker ID або мінливий key створює новий row. Виправте key stability до cleanup.

## Статистика показує нульову тривалість/пам’ять

Поточна aggregation implementation повертає placeholder zero для цих metrics. Це не означає нульове споживання. Використовуйте task duration у Logs та зовнішній APM.

## ArtisanWorker блокує команду

Перевірте `config/artisan_security.php`:

- dangerous commands заборонені;
- confirmation-required потребують `confirm=true`;
- whitelist, якщо непорожній, дозволяє тільки listed patterns;
- blacklist блокує додаткові commands;
- потрібен permission `run_artisan`.

Не вимикайте security checks у production.

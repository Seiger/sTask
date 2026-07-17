# FAQ

## sTask — це Laravel Queue?

Ні. sTask має власні Eloquent tables, worker contract і run-and-exit command. Він не використовує broker/queue connection Laravel як основний execution engine.

## Чи потрібен Redis?

Ні для базової роботи. Worker cache/metrics використовують Laravel Cache, а supervisor lock явно бере store `file`. Налаштований application cache все одно повинен бути справним.

## Чи є SSE або WebSocket?

Ні. Live progress — адаптивний HTTP polling endpoint `/stask/task/{id}/progress`.

## Чи виконує кнопка запуску task у background?

Controller намагається закрити FastCGI response і запустити CLI worker; fallback може бути synchronous або не спрацювати через disabled functions. Cron/scheduler — надійний production path.

## Чи зупиняє emergency stop PHP process?

Ні. Вона лише переводить DB record у failed. OS process треба зупиняти окремо.

## Чи є автоматичні retries?

Ні. Attempts/max attempts зберігаються, але failed rows автоматично не requeue-яться стандартною командою.

## Що означає `system` у фільтрі користувача?

Tasks зі `started_by` null або `<= 0`, зокрема scheduler/supervisor events.

## Чому фільтр воркера показує назву, а не identifier?

Provider resolve-ить `worker->title` і використовує identifier лише як fallback. Query фільтрує tasks за identifiers, пов’язаними з selected worker IDs.

## Чому task з тим самим payload не створився вдруге?

Active duplicate suppression порівнює identifier, action і normalized meta для status queued/preparing/running та повертає існуючу модель.

## Чи є duplicate suppression race-safe?

Ні повністю. У schema немає unique key для active payload hash. Паралельні процеси можуть пройти lookup одночасно.

## Де зберігається progress?

`core/storage/stask/{taskId}.log`. DB `progress` оновлюється окремо й не відображає автоматично кожен file snapshot.

## Чому progress відсутній, але task працює?

File write failures навмисно ігноруються, щоб не зламати business task. Перевірте permissions і application logs.

## Як очистити history?

`sTask::cleanOldTasks($days)` видаляє тільки old finished DB tasks. Для failed tasks, `.log`, uploads/results і supervisor state потрібна окрема policy.

## Як запускати кілька workers паралельно?

Поточний CLI не має atomic multi-process claim. Не масштабуйте процеси горизонтально без нового claim/lease design та idempotency.

## Чому regular schedule не створив task наступного дня?

Алгоритм шукає candidate лише в поточному денному window і повертає null після end. Це відоме обмеження поточної реалізації.

## Чим Supervisor schedule відрізняється від periodic health check?

Supervisor оновлює один live-state row щохвилини та створює task лише для meaningful lifecycle event. Periodic schedule підтримує звичайний queued `taskMake`.

## Чи видаляється supervisor state з worker?

Ні автоматично: relation не має DB foreign key/cascade. Orphan rows очищайте після inventory.

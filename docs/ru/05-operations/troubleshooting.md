# Диагностика

## Модуль отсутствует

Проверьте Composer package discovery, регистрацию `Seiger\sTask\sTaskServiceProvider`, наличие permission `stask` и повторный вход пользователя после миграции прав.

## Интерфейс без стилей

```bash
php artisan stask:publish
php artisan vendor:publish --tag=evo-ui --force
php artisan cache:clear-full
```

Убедитесь, что URL CSS/JavaScript возвращают соответствующий content type, а не HTML ошибки.

## Воркеры не обнаружены

- выполните `composer dump-autoload`;
- проверьте namespace и доступность PHP-класса;
- убедитесь, что класс реализует `TaskInterface` или наследует `BaseWorker`;
- обновите реестр воркеров только после завершения deploy.

## Задания остаются queued

Проверьте cron, `php artisan schedule:list`, активность воркера, значение `start_at` и ручной запуск `php artisan stask:worker`.

## Progress не обновляется

Проверьте права записи в `storage/stask`, endpoint progress и browser network. Менеджер использует HTTP polling; наличие SSE/WebSocket не требуется.

## Emergency stop не завершил процесс

Это ожидаемое поведение. Кнопка фиксирует failed state в БД. Остановку PHP/OS-процесса и идемпотентность повторного запуска должен обеспечивать прикладной воркер или process manager.

## Supervisor не восстанавливается

Проверьте `inspectSupervisor()`, heartbeat, PID, startup grace, доступ к file lock и диагностические записи `s_supervisor_states`. Методы start/restart должны быстро возвращать состояние после detached launch.

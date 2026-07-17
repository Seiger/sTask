# Diagnostics

Start with evidence: package reference, routes, migrations, scheduler, DB row, progress file, application log. Do not conclude with the UI badge alone.

## sTask is not visible in the Documentation module

1. Check the physical artifact:

   ```bash
   test -f core/vendor/seiger/stask/docs/uk/README.md
   ```

2. Check the lock/source reference:

   ```bash
   cd core
   composer show seiger/stask --all
   ```

3. Make sure the dDocs have `scan_vendor_packages = 1`.
4. Clear index/application cache.
5. Check package metadata in `lang/{locale}/global.php`: `module_title`, `module_description`, `module_icon`.

dDocs automatically scans packages `seiger/*`. Do not add vendor root to `extra_docs_roots`: configured project root may become writable in the viewer.

If Git repo docs and `core/vendor` don't, the problem is with Composer lock/dist/deploy, not with Markdown index.

## dDocs crashes to indexing

Check the stack trace of the third-party documentation package. Locale alias file, service provider, or cache may crash before sTask source is read. This is a separate defect dDocs/environment; sTask docs can't fix someone else's bootstrap.

## The sTask module is not visible

- `composer show seiger/stask`;
- provider is present in Package Discovery;
- permission `stask` assigned role;
- migrations have passed;
- manager cache is cleared;
- module/plugin registration is completed by the Evolution CMS installer.

The provider has a manager registration method, but the actual module entry can also be managed by Evolution package installer/plugin. Check database module record and package discovery, rather than manually calling protected method.

## Module without styles or JavaScript

```bash
cd core
php artisan stask:publish
php artisan cache:clear-full
```

In browser Network, check `stask-module.css`, `stask-module.js`, `stask.min.css`. If deploy uses readonly release filesystem, assets must be published at build time.

## `stask:worker` won't start

```bash
php -v
php artisan list | grep stask
php artisan route:list --path=stask
```

The package requires PHP 8.4. Check that the CLI and FPM use the same release, `.env`, extensions, and permissions.

## Task queued and not executed

Check out:

```sql
SELECT id, identifier, action, status, start_at, created_at
FROM s_tasks
WHERE status IN (10, 30, 50)
ORDER BY id;
```

- cron is actually executed;
- `start_at` not in the future;
- worker active;
- class exists and implements `TaskInterface`;
- application log does not contain a resolution exception;
- there is no external lock that is constantly occupied.

## Schedule does not create a task

- `settings.schedule.enabled = true`;
- type not `manual`;
- concrete worker has `taskMake()`;
- there is no incomplete task of this identifier;
- datetime once in the future;
- weekly has `days`;
- Regular window valid and not overnight;
- `stask:worker` passes every minute.

## Worker does not appear

```bash
composer dump-autoload
php artisan package:discover
```

Then refresh registry. Class must be concrete and in the Composer classmap. PSR-4 class, which Composer has not optimized in the classmap, may not be found by the current discovery implementation to the authoritative/optimized dump.

Check `config/excluded_namespaces.php`: large framework namespaces are intentionally omitted.

## `WorkerClassNotFound` / `WorkerInvalidInterface`

- class name in `s_workers.class` correct;
- autoload is up-to-date;
- class not abstract;
- class implements `TaskInterface`;
- constructor does not crash due to DB/settings dependency.

Do not click clean orphaned during partial deploy: record may be deleted while class is temporarily unavailable.

## Live progress 404

404 means `storage/stask/{id}.log` not found. Task may still be queued or write may have quietly failed.

```bash
ls -la core/storage/stask
```

Compare user/group for FPM and cron. See DB status/message and application log.

## Progress frozen, task finished

Progress file append-only and is not a source of truth for final DB status. Live watcher stops at terminal string on the last line. If the custom worker finalized the DB but did not call `markFinished()`/did not record the final snapshot, the UI will update after Livewire refresh, but the file may remain running.

## Emergency stop did not stop the process

This is the expected limit: the action only puts the DB record into failed. Find the process using infrastructure telemetry, stop it normally, check the side effects, then run a new task.

## Supervisor restart loop

- Startup Grace is too small;
- inspection does not recognize starting;
- heartbeat timeout is shorter than the real frequency;
- fingerprint changes on each pass via timestamp/random text;
- process will also restart systemd;
- start/restart is not detached.

Fingerprint must be stable for the same diagnosis.

## Supervisor state grows

```sql
SELECT worker_id, identifier, supervisor_key, COUNT(*)
FROM s_supervisor_states
GROUP BY worker_id, identifier, supervisor_key;
```

Unique `key_hash` prevents growth for the same pair, but a new worker ID or variable key creates a new row. Fix key stability to cleanup.

## Statistics show zero duration/memory

The current aggregation implementation returns placeholder zero for these metrics. This does not mean zero consumption. Use task duration in Logs and external APM.

## ArtisanWorker blocks the command

Check `config/artisan_security.php`:

- dangerous commands are prohibited;
- confirmation-required require `confirm=true`;
- whitelist, if not empty, allows only listed patterns;
- blacklist blocks additional commands;
- Permission `run_artisan` is required.

Do not disable security checks in production.

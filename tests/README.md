# Local contract checks

Run the structural suite and heartbeat fixture with PHP 8.4 or newer:

```sh
php tests/run.php
php tests/heartbeat.php
```

The supervisor contract requires Illuminate dependencies and SQLite. It owns an
in-memory database and container; it does not boot the consuming CMS or use its
site database. Point `STASK_AUTOLOAD` at a consuming Composer autoloader when this
checkout has no `vendor/` directory:

```sh
STASK_AUTOLOAD=/path/to/site/core/vendor/autoload.php php tests/supervisor_contract.php
```

The bootstrap prioritizes this checkout and verifies the loaded service path.
The task detail view declares `data-manager-tree="hidden"`; the manager viewport
reads that attribute on iframe load and restores the tree for subsequent pages.
No inline script or separate viewport implementation is needed.

`STASK_AUTOLOAD=/path/to/core/vendor/autoload.php php tests/queue_claim.php`
starts eight independent PHP contenders against a disposable SQLite database.
They load the same queued row before a common start gate. Exactly one may claim
it; losing runners leave the winner untouched. Due-time and terminal-state guards
are also covered. A crashed running task is not automatically requeued: recovery
of externally visible work needs reconciliation rather than an implicit resend.

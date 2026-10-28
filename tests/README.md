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

`STASK_AUTOLOAD=/path/to/core/vendor/autoload.php php tests/queue_admission.php`
verifies that eight concurrent producers admit one identical active task. Worker
row serialization precedes duplicate lookup; scheduled duplicate admission does
not overwrite a task's start time. SQLite contention is qualified locally;
other database engines require their own execution qualification.

The source-contract smoke suite does not need a CMS bootstrap:

```bash
php tests/run.php
```

## Manager Memory Regression

Requires PHP 8.4, PDO SQLite, and installed Composer dependencies. Run from the
package root after `composer install`:

```bash
composer test:metrics
```

An isolated package checkout may reuse a consumer's dependency autoloader without
bootstrapping that application:

```bash
STASK_TEST_AUTOLOAD=/path/to/site/core/vendor/autoload.php \
  php -d memory_limit=96M tests/metrics_memory.php --large
```

The test explicitly loads the three tested sTask classes from this checkout,
even if the consumer's Composer classmap points to another installed sTask copy.
It creates and removes a temporary, prefixed SQLite database. It never connects
to the CMS database, executes a worker, modifies queues, or clears application caches.

The large fixture stores 120 tasks with approximately 1 MiB of combined
`meta`/`result` per task, exceeding the 96 MiB PHP memory limit. Assertions cover:

- empty data, reporting windows, status counts, worker percentages and alert semantics;
- zero task-model hydration and no payload columns in aggregate queries;
- latest and active snapshots bounded to the visible workers, in one query per set;
- creation-time ordering, deterministic ID tie-breaking and backdated IDs;
- queued, preparing, running, terminal and unknown status handling;
- duplicate, absent and parameter-bound worker identifiers;
- action/progress availability and exclusion of payload fields;
- bounded memory growth regardless of stored payload volume.

Omit `--large` for the small functional fixture. Duration/memory metrics that were
already placeholders remain placeholders; their zero values are not measured usage.

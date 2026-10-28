# Regression Tests

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

# Database Tables

Below is the actual schema migrations of the 2.x branch.

## `s_workers`

| Column | Type/attributes | Purpose |
| --- | --- | --- |
| `id` | BIGINT PK | internal worker ID |
| `uuid` | UUID nullable unique | optional external identity |
| `identifier` | string unique | stable task routing key |
| `scope` | string default `''` | package/module grouping |
| `class` | string | FQCN |
| `active` | boolean default false | scheduler/run gate |
| `position` | unsigned int default 0 | UI order |
| `settings` | JSON default empty array expression | schedule/custom settings |
| `hidden` | unsigned int default 0 | UI visibility |
| timestamps | created/updated | audit |

Indexes: unique uuid, unique identifier, plus identifier, scope, active, position indexes. Unique identifier already creates an index; additional explicit index may be redundant depending on the DB.

## `s_tasks`

| Column | Type/attributes | Purpose |
| --- | --- | --- |
| `id` | BIGINT PK | task ID |
| `identifier` | string | worker routing key |
| `action` | string | action code |
| `status` | unsigned small int default 10 | lifecycle |
| `message` | text nullable | persisted summary/error |
| `started_by` | unsigned int nullable | manager user or system |
| `meta` | longText nullable | input metadata |
| `result` | longText nullable | result payload/path |
| `start_at` | timestamp nullable | scheduled/actual start |
| `finished_at` | timestamp nullable | final time |
| `attempts` | int default 0 | incremented on running |
| `max_attempts` | int default 3 | retry metadata |
| `priority` | string default normal | compatibility/queue ordering |
| `progress` | int default 0 | persisted progress |
| timestamps | created/updated | audit |

Indexes: `(identifier, action)`, status, started_by, start_at, created_at, priority.

There is no foreign key from task identifier to `s_workers.identifier`, so history survives deletion of worker record. Relation works logically by identifier.

Meta/result casts the Eloquent model as an array; actual storage is long text, not native JSON.

## `s_supervisor_states`

| Column | Type/attributes | Purpose |
| --- | --- | --- |
| `id` | BIGINT PK | state row ID |
| `worker_id` | unsigned BIGINT, indexed | owner `s_workers.id` without FK |
| `identifier` | string, indexed | denormalized worker key |
| `supervisor_key` | string | adapter process identity |
| `key_hash` | char(64) unique | sha256 worker ID + key |
| `state` | string(24), default stopped, indexed | lifecycle state |
| `pid` | unsigned BIGINT nullable | process ID |
| `heartbeat_at` | timestamp nullable | last heartbeat |
| `supervisor_started_at` | timestamp nullable | process start |
| `uptime_seconds` | unsigned BIGINT nullable | adapter uptime |
| `message` | text nullable | diagnostic |
| `fingerprint` | char(64) nullable | dedup fingerprint |
| `last_transition_at` | timestamp nullable | state transition |
| `last_seen_at` | timestamp nullable, indexed | last scheduler observation |
| `repeat_count` | unsigned int default 0 | repeated fingerprint count |
| `launch_requested_at` | timestamp nullable | startup grace cursor |
| timestamps | created/updated | record audit |

### Life cycle

`firstOrNew(key_hash)` guarantees one live row per worker ID + stable key. Each pass updates the last seen; The same fingerprint increases the repeat count, the new fingerprint resets it to zero. State transition updates `last_transition_at`.

Healthy status cleanses `launch_requested_at`. Other statuses retain the previous or current launch request.

### Growth & Purification

Heartbeat history does not accumulate rows. Growth means new worker IDs or keys. There is no automatic retention, there is no foreign key/cascade.

Safe cleanup:

1. inventory actual workers and adapter keys;
2. Make sure that Daemon with Old Key does not work;
3. archive diagnostic if required;
4. Delete only the exact orphan IDs/hashes.

Do not make a wide `TRUNCATE` during active scheduler.

## Permission tables

Migration, if system tables exist:

- finds/creates a group `sTask`;
- upsert-it permission `stask` with `disabled = 0`;
- adds role_permissions for role `1`;
- on PostgreSQL is able to restore sequence after insert conflict.

Migration disables the Laravel transaction wrapper because PostgreSQL aborts the transaction after the failed statement, and the code has a retry path.

## Portability

Schema is focused on MySQL/MariaDB/PostgreSQL/SQLite through Laravel Schema Builder. JSON default expression `JSON_ARRAY()` is DB-sensitive; Run migration tests on the target engine. Permission migration handles PostgreSQL sequence separately.

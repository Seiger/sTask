# Таблиці бази даних

Нижче — фактична schema migrations гілки 2.x.

## `s_workers`

| Column | Type/attributes | Призначення |
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

Indexes: unique uuid, unique identifier, plus identifier, scope, active, position indexes. Unique identifier уже створює index; додатковий explicit index може бути redundant залежно від DB.

## `s_tasks`

| Column | Type/attributes | Призначення |
| --- | --- | --- |
| `id` | BIGINT PK | task ID |
| `identifier` | string | worker routing key |
| `action` | string | action code |
| `status` | unsigned small int default 10 | lifecycle |
| `message` | text nullable | persisted summary/error |
| `started_by` | unsigned int nullable | manager user або system |
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

Немає foreign key від task identifier до `s_workers.identifier`, тому history переживає видалення worker record. Relation працює логічно за identifier.

Meta/result cast-яться Eloquent model як array; фактичний storage — long text, а не native JSON.

## `s_supervisor_states`

| Column | Type/attributes | Призначення |
| --- | --- | --- |
| `id` | BIGINT PK | state row ID |
| `worker_id` | unsigned BIGINT, indexed | owner `s_workers.id` без FK |
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

### Життєвий цикл

`firstOrNew(key_hash)` гарантує один live row на worker ID + stable key. Кожен pass оновлює last seen; однаковий fingerprint збільшує repeat count, новий fingerprint скидає його до zero. State transition оновлює `last_transition_at`.

Healthy status очищає `launch_requested_at`. Інші status зберігають попереднє або поточне launch request.

### Ріст і очищення

Heartbeat history не накопичується rows. Ріст означає нові worker IDs або keys. Автоматичної retention немає, foreign key/cascade немає.

Безпечний cleanup:

1. inventory actual workers та adapter keys;
2. переконатися, що daemon з old key не працює;
3. archive diagnostic if required;
4. видалити лише exact orphan IDs/hashes.

Не робіть широке `TRUNCATE` під час active scheduler.

## Permission tables

Migration, якщо system tables існують:

- знаходить/створює group `sTask`;
- upsert-ить permission `stask` з `disabled = 0`;
- додає role_permissions для role `1`;
- на PostgreSQL уміє відновити sequence після insert conflict.

Migration вимикає Laravel transaction wrapper, бо PostgreSQL abort-ить transaction після failed statement, а code має retry path.

## Portability

Schema орієнтована на MySQL/MariaDB/PostgreSQL/SQLite через Laravel Schema Builder. JSON default expression `JSON_ARRAY()` є DB-sensitive; проганяйте migration tests на цільовому engine. Permission migration окремо обробляє PostgreSQL sequence.

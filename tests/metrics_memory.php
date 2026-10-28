<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Services\MetricsService;

// Load dependencies only; the tested classes must always come from this checkout.
$autoload = getenv('STASK_TEST_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Install Composer dependencies or set STASK_TEST_AUTOLOAD to an existing vendor/autoload.php.');
}
require_once $autoload;
require_once dirname(__DIR__) . '/src/Models/sTaskModel.php';
require_once dirname(__DIR__) . '/src/Services/MetricsService.php';
require_once dirname(__DIR__) . '/src/Tables/WorkersTableData.php';

$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$file = tempnam(sys_get_temp_dir(), 'stask-metrics-');
if ($file === false) {
    throw new RuntimeException('Cannot create the isolated SQLite fixture.');
}
register_shutdown_function(static function () use ($file): void { @unlink($file); });
$db = new Capsule(new Container());
$db->addConnection(['driver' => 'sqlite', 'database' => $file, 'prefix' => 'fixture_']);
$db->setAsGlobal();
$db->bootEloquent();
$now = Carbon::parse('2026-10-28 18:00:00', 'UTC');
Carbon::setTestNow($now);

try {
    $db->schema()->create('s_tasks', static function ($table): void {
        $table->increments('id');
        $table->string('identifier');
        $table->string('action')->default('run');
        $table->integer('progress')->default(0);
        $table->integer('status');
        $table->text('meta')->nullable();
        $table->text('result')->nullable();
        $table->timestamps();
    });
    $metrics = new MetricsService();
    $empty = $metrics->getSystemSummary();
    $check($empty['tasks']['total'] === 0 && $empty['workers'] === [], 'Empty statistics must remain valid.');
    $check($empty['performance']['success_rate'] === 0.0, 'Empty success rate must remain a float zero.');

    foreach ([['alpha', 80], ['alpha', 100], ['beta', 50], ['beta', 10], ['beta', 777]] as [$identifier, $status]) {
        $db->table('s_tasks')->insert([
            'identifier' => $identifier, 'status' => $status,
            'created_at' => $now->copy()->subMinutes(20), 'updated_at' => $now,
        ]);
    }
    $db->table('s_tasks')->insert([
        'identifier' => 'old', 'status' => 80,
        'created_at' => $now->copy()->subHours(25), 'updated_at' => $now,
    ]);
    $bulk = in_array('--large', $argv ?? [], true) ? 120 : 0;
    if ($bulk > 0) {
        $payload = json_encode(['body' => str_repeat('x', 512 * 1024)], JSON_THROW_ON_ERROR);
        $db->connection()->transaction(function () use ($db, $bulk, $payload, $now): void {
            for ($i = 0; $i < $bulk; $i++) {
                $db->table('s_tasks')->insert([
                    'identifier' => 'bulk', 'status' => 80, 'meta' => $payload, 'result' => $payload,
                    'created_at' => $now->copy()->subMinutes(20), 'updated_at' => $now,
                ]);
            }
        });
        unset($payload);
    }
    $hydrated = 0;
    sTaskModel::setEventDispatcher(new \Illuminate\Events\Dispatcher(new Container()));
    sTaskModel::retrieved(static function () use (&$hydrated): void { $hydrated++; });
    $db->connection()->enableQueryLog();
    $before = memory_get_usage(true);
    $summary = $metrics->getSystemSummary(24);
    $alerts = $metrics->getPerformanceAlerts();
    $growth = memory_get_usage(true) - $before;

    $check($summary['tasks'] === [
        'total' => 5 + $bulk, 'completed' => 1 + $bulk, 'failed' => 1, 'running' => 1, 'queued' => 1,
    ], 'All status counters must preserve the original semantics, including unknown statuses.');
    $check($summary['workers']['alpha'] === ['identifier' => 'alpha', 'task_count' => 2, 'success_rate' => 50.0], 'Per-worker counts and percentages must remain unchanged.');
    $check($summary['workers']['beta']['task_count'] === 3 && !isset($summary['workers']['old']), 'Period filtering and unknown statuses must preserve worker totals.');
    $check($summary['performance']['success_rate'] === round((1 + $bulk) / (5 + $bulk) * 100, 2), 'System success percentage must remain unchanged.');
    $check($summary['errors']['total_errors'] === 1 && $summary['errors']['common_errors'] === [], 'Existing error statistics must remain unchanged.');
    $check($summary['performance']['average_duration'] === 0.0 && $summary['performance']['average_memory'] === 0, 'Existing unimplemented metric placeholders must not change.');
    $check($summary['period']['from'] === $now->copy()->subHours(24)->toISOString(), 'The requested reporting window must remain unchanged.');
    $check(count($alerts) === (($summary['performance']['success_rate'] < 95.0) ? 1 : 0), 'Alert thresholds must retain their behavior.');
    $check($hydrated === 0, 'Statistics must not hydrate task models or their payloads.');
    foreach ($db->connection()->getQueryLog() as $query) {
        $sql = strtolower($query['query']);
        $check(!preg_match('/select\s+\*/', $sql) && !str_contains($sql, '"meta"') && !str_contains($sql, '"result"'), 'Statistics must not select complete tasks or payload columns.');
    }
    $check($growth < 8 * 1024 * 1024, 'Statistics memory must not scale with stored payload volume.');

    $table = new \Seiger\sTask\Tables\WorkersTableData();
    $lastMethod = new ReflectionMethod($table, 'lastTasksFor');
    $activeMethod = new ReflectionMethod($table, 'activeTasksFor');
    $db->connection()->flushQueryLog();
    $check($lastMethod->invoke($table, [])->isEmpty() && $activeMethod->invoke($table, [])->isEmpty(), 'An empty worker page must not load tasks.');
    $check($db->connection()->getQueryLog() === [], 'Empty worker pages must not query task history.');
    $hydrated = 0;
    $visible = ['alpha', 'beta', 'bulk', 'absent', 'alpha'];
    $last = $lastMethod->invoke($table, $visible);
    $active = $activeMethod->invoke($table, $visible);
    $check(count($db->connection()->getQueryLog()) === 2, 'Each snapshot set must use one query, not one roundtrip per worker.');
    $check($last->count() === ($bulk > 0 ? 3 : 2) && $active->count() === 1, 'Only one newest matching task per visible worker may be loaded.');
    $check((int)$last['alpha']->status === 100 && (int)$active['beta']->status === 10, 'Ties must choose the higher id; active tasks must ignore finished or unknown statuses.');
    $check($hydrated === $last->count() + $active->count(), 'Worker history must be reduced in SQL, before model hydration.');
    foreach ($last->merge($active) as $task) {
        $check(array_keys($task->getAttributes()) === ['id', 'identifier', 'action', 'status', 'progress', 'created_at'], 'Worker snapshots must select only the fields consumed by overview rows.');
    }
    foreach ($db->connection()->getQueryLog() as $query) {
        $sql = strtolower($query['query']);
        $check(!preg_match('/select\s+\*/', $sql) && !str_contains($sql, '"meta"') && !str_contains($sql, '"result"'), 'Worker queries must exclude complete task rows and payload columns.');
    }
    $db->table('s_tasks')->insert([
        'identifier' => 'beta', 'status' => 80,
        'created_at' => $now->copy()->subHours(26), 'updated_at' => $now,
    ]);
    $check((int)$lastMethod->invoke($table, ['beta'])['beta']->status === 777, 'Creation time must take precedence over a higher backdated id.');
    $db->table('s_tasks')->insert([
        'identifier' => "worker'quoted", 'status' => 30, 'action' => 'prepare', 'progress' => 17,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $preparing = $activeMethod->invoke($table, ["worker'quoted"]);
    $check($preparing->count() === 1 && (int)$preparing["worker'quoted"]->status === 30, 'Preparing tasks are active; worker identifiers must remain bound query parameters.');
    $check($preparing["worker'quoted"]->action === 'prepare' && $preparing["worker'quoted"]->progress === 17, 'Overview snapshots must retain action and live progress fields.');
    foreach ([24 * 3600, 24 * 3600 + 1] as $age) {
        $db->table('s_tasks')->insert([
            'identifier' => 'boundary', 'status' => 80,
            'created_at' => $now->copy()->subSeconds($age), 'updated_at' => $now,
        ]);
    }
    $db->table('s_tasks')->insert([
        'identifier' => 'hour-boundary', 'status' => 80,
        'created_at' => $now->copy()->subHour(), 'updated_at' => $now,
    ]);
    $daily = $metrics->getSystemSummary(24);
    $hourly = $metrics->getSystemSummary(1);
    $check($daily['workers']['boundary']['task_count'] === 1 && !isset($hourly['workers']['boundary']), 'Reporting windows must include their exact lower bound and exclude older rows.');
    $check($hourly['workers']['hour-boundary']['task_count'] === 1, 'The alert reporting window must include its exact one-hour boundary.');
    echo 'sTask metrics OK: SQLite, ' . ($bulk + 5) . ' recent tasks, ' . $hydrated . ' hydrated models, memory growth ' . $growth . " bytes\n";
} finally {
    Carbon::setTestNow();
    sTaskModel::unsetEventDispatcher();
    $db->getDatabaseManager()->purge();
    @unlink($file);
}

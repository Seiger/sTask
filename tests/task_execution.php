<?php

use Illuminate\Container\Container;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Services\ExecutionLock;
use Seiger\sTask\Services\TaskExecution;
use Symfony\Component\Process\Process;

$testRoot = sys_get_temp_dir() . '/stask-execution-test-' . bin2hex(random_bytes(8));
mkdir($testRoot . '/logs', 0700, true);
$database = $testRoot . '/tasks.sqlite';
touch($database);
function storage_path($path = ''): string { return $GLOBALS['testRoot'] . '/' . $path; }
require __DIR__ . '/bootstrap.php';
$container = new Container();
Container::setInstance($container);
$container->instance('config', new Repository());
$db = new Capsule($container);
$db->addConnection(['driver' => 'sqlite', 'database' => $database]);
$db->setAsGlobal();
$db->bootEloquent();
$container->instance('db', $db->getDatabaseManager());
Facade::setFacadeApplication($container);
$db->schema()->create('s_tasks', static function ($table): void {
    $table->increments('id');
    $table->string('identifier'); $table->string('action'); $table->integer('status');
    $table->integer('attempts')->default(0); $table->integer('max_attempts')->default(3);
    $table->text('message')->nullable(); $table->text('result')->nullable();
    $table->timestamp('start_at')->nullable(); $table->timestamp('finished_at')->nullable(); $table->timestamps();
});
$assert = static function ($condition, $message): void { if (!$condition) { throw new RuntimeException($message); } };
$makeTask = static fn ($action = 'complete') => sTaskModel::query()->create([
    'identifier' => 'sChatCenter', 'action' => $action, 'status' => 10, 'attempts' => 0, 'max_attempts' => 3,
]);
$execution = new class extends TaskExecution {
    public function timeout(sTaskModel $task): int { return 2; }
    protected function process(sTaskModel $task, string $token): Process
    {
        $code = <<<'PHP'
if ($argv[3] === 'hang') { sleep(10); }
if ($argv[3] === 'crash') { exit(7); }
if ($argv[3] === 'silent') { exit(0); }
$db = new PDO('sqlite:' . $argv[1]);
$status = $argv[3] === 'defer' ? 10 : 80;
$query = $db->prepare('UPDATE s_tasks SET status=? WHERE id=?');
$query->execute([$status, (int) $argv[2]]);
PHP;
        $process = new Process([PHP_BINARY, '-d', 'extension_dir=' . ini_get('extension_dir'), '-d', 'extension=pdo_sqlite',
            '-r', $code, $GLOBALS['database'], (string) $task->id, $task->action]);
        $process->setTimeout(1.5);
        $process->disableOutput();
        return $process;
    }
};
try {
    $runner = new ExecutionLock();
    $other = new ExecutionLock();
    $assert($runner->acquire('runner') && !$other->acquire('runner'), 'Only one local runner may enter scheduling and queue processing.');
    $runner->release();
    $assert($other->acquire('runner'), 'Runner lock must be reusable after release.');
    $other->release();
    $wakeup = new \Seiger\sTask\Services\QueueWakeup();
    $assert($wakeup->shouldLaunch() && !$wakeup->shouldLaunch(), 'Concurrent producer wakeups must launch PHP at most once per coalescing window.');

    foreach (['complete', 'defer', 'crash', 'silent', 'hang'] as $action) {
        $task = $makeTask($action);
        $stale = $task->fresh();
        $assert($task->tryClaim() && !$stale->tryClaim(), 'A stale queue snapshot must not execute the same task.');
        $start = microtime(true);
        $execution->run($task);
        $fresh = $task->fresh();
        if ($action === 'complete') {
            $assert($fresh->status === 80, 'The parent must preserve successful child completion.');
        } elseif ($action === 'defer') {
            $assert($fresh->status === 10, 'A deferred task must not be marked completed by the parent.');
        } else {
            $assert($fresh->status === 100 && data_get($fresh->result, 'retry_safe') === false && !$fresh->canRetry(), 'A killed or crashed task needs an unknown outcome and no automatic retry.');
            $assert(microtime(true) - $start < 5, 'The process watchdog must enforce its wall-clock deadline.');
            $assert(\Seiger\sTask\Services\TaskProgress::readProgress($fresh->id)['status'] === 'failed', 'The UI progress snapshot must not remain running after interruption.');
        }
        $assert($execution->read($task->id) === [], 'Completed process leases must not accumulate.');
        $execution->interrupt($task, 'Late parent error');
        $assert($fresh->status === $task->fresh()->status, 'A late watchdog must not overwrite a terminal or deferred state.');
    }

    $orphan = $makeTask();
    $orphan->tryClaim();
    $state = ['task_id' => $orphan->id, 'attempt' => $orphan->attempts, 'deadline' => time() - 10, 'token' => 'fixture'];
    file_put_contents(storage_path('stask/leases/task-' . $orphan->id . '.json'), json_encode($state));
    $live = new ExecutionLock();
    $live->acquire('task:' . $orphan->id);
    $execution->recover();
    $assert($orphan->fresh()->status === 50, 'A live task owner must not be reaped by age alone.');
    $live->release();
    $execution->recover();
    $assert($orphan->fresh()->status === 100 && !$orphan->fresh()->canRetry(), 'A dead expired owner must become interrupted without requeueing.');
    $legacy = $makeTask();
    $legacy->tryClaim();
    $execution->recover();
    $assert($legacy->fresh()->status === 50, 'Legacy rows with no lease must not be assumed dead.');
    echo "sTask claim, process deadline, locks and orphan recovery OK\n";
} finally {
    $db->getDatabaseManager()->disconnect();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) { $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); }
    rmdir($testRoot);
}

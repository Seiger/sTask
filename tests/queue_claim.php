<?php
// Real SQLite contention; no CMS bootstrap, external transport, or live database.
require __DIR__ . '/bootstrap.php';
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Seiger\sTask\Models\sTaskModel as Task;

$child = ($argv[1] ?? '') === '--claim';
$path = $child ? $argv[2] : tempnam(sys_get_temp_dir(), 'stask-claim-');
$container = new Container(); Container::setInstance($container);
$db = new Capsule($container); $db->addConnection(['driver'=>'sqlite','database'=>$path]);
$db->setAsGlobal(); $db->bootEloquent();
$db->getConnection()->statement('PRAGMA busy_timeout=10000');
$check = static function ($ok, $message): void { if (!$ok) throw new RuntimeException($message); };
if ($child) {
    $task = Task::findOrFail((int)$argv[3]);
    file_put_contents($argv[4] . '.' . getmypid(), 'ready');
    for ($i=0; $i<1000 && !file_exists($argv[4]); $i++) usleep(10000);
    $check(file_exists($argv[4]), 'Start gate not opened');
    echo $task->tryClaim() ? 'won' : 'lost';
    exit;
}
try {
    $db->schema()->create('s_tasks', static function ($t): void {
        $t->increments('id'); $t->integer('status'); $t->integer('attempts')->default(0);
        $t->timestamp('start_at')->nullable(); $t->timestamp('finished_at')->nullable();
        $t->text('message')->nullable(); $t->timestamps();
    });
    $task=Task::create(['status'=>Task::TASK_STATUS_QUEUED]);
    $gate=$path.'.gate'; $processes=[];
    for ($i=0;$i<8;$i++) {
        $pipes=[];
        $p=proc_open([PHP_BINARY,'-n',__FILE__,'--claim',$path,(string)$task->id,$gate],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $check(is_resource($p),'Cannot spawn contender'); fclose($pipes[0]);$processes[]=[$p,$pipes];
    }
    for ($i=0;$i<1000 && count(glob($gate.'.*'))<8;$i++) usleep(10000);
    $check(count(glob($gate.'.*'))===8,'Contenders did not load stale snapshots');
    file_put_contents($gate,'go');$won=0;
    foreach ($processes as [$p,$pipes]) {
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);
        $check($code===0 && in_array($out,['won','lost'],true),'Contender failed: '.$err);$won+=($out==='won');
    }
    $task->refresh();$check($won===1 && $task->attempts===1 && $task->status===Task::TASK_STATUS_RUNNING,'Exactly one claimant required');
    // A loser must return before worker lookup (there is deliberately no workers table).
    $runner=new \Seiger\sTask\Console\TaskWorker();
    $run=new ReflectionMethod($runner,'runOne');
    $check($run->invoke($runner,$task)===false,'Losing runner did not exit');
    $check($task->fresh()->status===Task::TASK_STATUS_RUNNING,'Loser changed winner state');
    foreach ([Task::TASK_STATUS_RUNNING,Task::TASK_STATUS_PREPARING,Task::TASK_STATUS_FINISHED,Task::TASK_STATUS_FAILED] as $status) {
        $t=Task::create(['status'=>$status,'attempts'=>2]);
        $check(!$t->tryClaim() && $t->fresh()->attempts===2,'Nonqueued task claimed');
    }
    $future=Task::create(['status'=>Task::TASK_STATUS_QUEUED,'start_at'=>now()->addHour()]);
    $check(!$future->tryClaim() && $future->fresh()->attempts===0,'Future task claimed early');
    $past=Task::create(['status'=>Task::TASK_STATUS_QUEUED,'start_at'=>now()->subMinute(),'attempts'=>2]);
    $check($past->tryClaim() && $past->attempts===3 && !$past->tryClaim(),'Due task claim/increment failed');
    echo "Queue claim: PASS (8 contenders, one winner; loser isolation; due/status guards)\n";
} finally {
    $db->getDatabaseManager()->disconnect();
    foreach (glob($path.'*') as $f) @unlink($f);
}

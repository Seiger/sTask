<?php
if(!function_exists('storage_path')){function storage_path($path=''){return $GLOBALS['executorStorage'].'/'.$path;}}
require __DIR__.'/bootstrap.php';
use Illuminate\Container\Container;use Illuminate\Database\Capsule\Manager as Capsule;use Illuminate\Support\Facades\Facade;
use Seiger\sTask\Models\sTaskModel as Task;use Seiger\sTask\Services\TaskExecutor;
class ExecutorFixtureWorker extends \Seiger\sTask\Workers\BaseWorker
{
 public function identifier():string{return 'executor-fixture';}public function scope():string{return 'fixture';}public function icon():string{return '';}public function title():string{return 'fixture';}public function description():string{return 'fixture';}
 public function taskMake(Task $task,array $options=[]):void{$GLOBALS['executorCalls']++;if($options['fail']??false)throw new RuntimeException('Synthetic failure');}
}
$c=new Container();Container::setInstance($c);$c->instance('translator',new class{public function get($key,$replace=[]){return $key;}});$c->instance('log',new class{public function error(...$args):void{}});
$db=new Capsule($c);$db->addConnection(['driver'=>'sqlite','database'=>':memory:']);$db->setAsGlobal();$db->bootEloquent();$c->instance('db',$db->getDatabaseManager());Facade::setFacadeApplication($c);
$GLOBALS['executorStorage']=sys_get_temp_dir().'/executor-'.bin2hex(random_bytes(8));mkdir($GLOBALS['executorStorage'],0700);$GLOBALS['executorCalls']=0;
$check=fn($ok,$why)=>$ok?:throw new RuntimeException($why);
try{
 $db->schema()->create('s_workers',function($t){$t->increments('id');$t->string('identifier');$t->string('class');$t->boolean('active');});
 $db->schema()->create('s_tasks',function($t){$t->increments('id');$t->string('identifier');$t->string('action');$t->integer('status');$t->integer('attempts')->default(0);$t->integer('progress')->default(0);$t->timestamp('start_at')->nullable();$t->timestamp('finished_at')->nullable();$t->text('message')->nullable();$t->text('meta');$t->timestamps();});
 $db->table('s_workers')->insert(['identifier'=>'executor-fixture','class'=>ExecutorFixtureWorker::class,'active'=>true]);
 $make=fn($meta=[])=>Task::create(['identifier'=>'executor-fixture','action'=>'make','status'=>10,'meta'=>$meta]);$executor=new TaskExecutor();
 $task=$make();$stale=$task->fresh();$check($executor->run($task) && !$executor->run($stale),'Duplicate claim executed');$check($GLOBALS['executorCalls']===1 && $task->fresh()->status===80 && $task->fresh()->attempts===1,'Success finalization failed');
 $future=$make();$future->update(['start_at'=>now()->addMinute()]);$check(!$executor->run($future),'Future task ran');
 $failed=$make(['fail'=>true]);$check($executor->run($failed) && $failed->fresh()->status===100,'Failure was not persisted');$check(!$executor->run($failed) && $GLOBALS['executorCalls']===2,'Failed task replayed');
 $db->table('s_workers')->update(['active'=>false]);$inactive=$make();$check($executor->run($inactive) && $inactive->fresh()->status===100 && $GLOBALS['executorCalls']===2,'Inactive worker invoked');
 echo "Task executor: PASS (success, failure, stale contender, due time, inactive worker, no replay)\n";
}finally{
 $db->getDatabaseManager()->disconnect();$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($GLOBALS['executorStorage'],FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($GLOBALS['executorStorage']);
}

<?php
// Concurrent duplicate admission uses only this disposable file database.
if(!function_exists('storage_path')){function storage_path($path=''){return (string)getenv('STASK_FIXTURE_STORAGE').'/'.$path;}}
require __DIR__.'/bootstrap.php';
use Illuminate\Container\Container;use Illuminate\Database\Capsule\Manager as Capsule;
use Seiger\sTask\Models\sTaskModel as Task;
$child=($argv[1]??'')==='--admit';$path=$child?$argv[2]:tempnam(sys_get_temp_dir(),'stask-admit-');
$c=new Container();Container::setInstance($c);$c->instance('translator',new class{public function get($key,$replace=[]){return $key;}});
$db=new Capsule($c);$db->addConnection(['driver'=>'sqlite','database'=>$path]);$db->setAsGlobal();$db->bootEloquent();$db->getConnection()->statement('PRAGMA busy_timeout=10000');
$check=fn($ok,$why)=>$ok?:throw new RuntimeException($why);
class AdmissionWorker extends \Seiger\sTask\Workers\BaseWorker{public function identifier():string{return 'fixture';}public function scope():string{return 'fixture';}public function icon():string{return '';}public function title():string{return 'fixture';}public function description():string{return 'fixture';}}
if($child){$worker=new AdmissionWorker();file_put_contents($argv[3].'.'.getmypid(),'ready');for($i=0;$i<1000&&!file_exists($argv[3]);$i++)usleep(10000);$check(file_exists($argv[3]),'No start gate');echo $worker->createTask('make',['fixture'=>'same'])->id;exit;}
$storage=$path.'.storage';mkdir($storage,0700);putenv('STASK_FIXTURE_STORAGE='.$storage);
try{
 $db->schema()->create('s_workers',function($t){$t->increments('id');$t->string('identifier')->unique();$t->timestamps();});$db->table('s_workers')->insert(['identifier'=>'fixture']);
 $db->schema()->create('s_tasks',function($t){$t->increments('id');$t->string('identifier');$t->string('action');$t->integer('status');$t->text('meta');$t->text('message')->nullable();$t->integer('started_by')->default(0);$t->string('priority');$t->timestamps();});
 $gate=$path.'.gate';$procs=[];for($i=0;$i<8;$i++){$pipes=[];$p=proc_open([PHP_BINARY,'-n',__FILE__,'--admit',$path,$gate],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);$check(is_resource($p),'Spawn failed');fclose($pipes[0]);$procs[]=[$p,$pipes];}
 for($i=0;$i<1000&&count(glob($gate.'.*'))<8;$i++)usleep(10000);$check(count(glob($gate.'.*'))===8,'No contenders');file_put_contents($gate,'go');$ids=[];
 foreach($procs as[$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);$check($code===0&&ctype_digit($out),'Child failed: '.$err.$out);$ids[]=$out;}
 $check(count(array_unique($ids))===1&&$db->table('s_tasks')->count()===1,'Duplicate admitted');echo "Queue admission: PASS (8 concurrent producers, one task)\n";
}finally{$db->getDatabaseManager()->disconnect();$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storage,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}@rmdir($storage);foreach(glob($path.'*')as$f)if(is_file($f))@unlink($f);}

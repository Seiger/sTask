<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\View\Compilers\BladeCompiler;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Models\sWorker;
use Seiger\sTask\Support\DashboardData;
use Seiger\sTask\Tables\LogsTableData;
use Seiger\sTask\Tables\TasksTableData;

// Pure view fixtures: no CMS bootstrap, routes, API requests or database connection.
function __($key, array $replace = []): string { return app('translator')->get($key, $replace); }
function route($name, $parameters = []): string { return '/fixture/' . $name; }

require __DIR__ . '/bootstrap.php';

/** Expose persisted task context through an optional localized title hook. @since 2.4.0 */
class ContextTitleWorker
{
    /** Keep the registered worker label independent of individual tasks. @since 2.4.0 */
    public function title(): string { return 'Catalogue'; }

    /** Format the stored operation and entity in the current manager language. @since 2.4.0 */
    public function taskTitle(sTaskModel $task): string
    {
        return $this->title() . ' · ' . __('tasks.' . $task->action . '.' . ($task->meta['entity'] ?? 'unknown'));
    }
}

/** Represent workers written before task-specific labels were introduced. @since 2.4.0 */
class LegacyTitleWorker
{
    /** Preserve the existing registry label as the default task label. @since 2.4.0 */
    public function title(): string { return 'Legacy worker'; }
}

/** Ensure a private method with the same name is not treated as the public hook. @since 2.4.0 */
class PrivateTitleWorker extends LegacyTitleWorker
{
    /** Deliberately remain inaccessible to task presentation code. @since 2.4.0 */
    private function taskTitle(): string { throw new RuntimeException('Private method was called.'); }
}

/** Exercise the documented empty-hook fallback for an existing worker. @since 2.4.0 */
class EmptyTitleWorker extends LegacyTitleWorker
{
    /** Allow the registry label to handle a task without custom wording. @since 2.4.0 */
    public function taskTitle(sTaskModel $task): string { return " \n "; }
}

/** Supply untrusted plain text to verify native Blade escaping. @since 2.4.0 */
class MarkupTitleWorker extends LegacyTitleWorker
{
    /** Return literal markup that must never be interpreted by manager views. @since 2.4.0 */
    public function taskTitle(sTaskModel $task): string { return '  <script>alert("title")</script> & label  '; }
}

$container = new Container();
Container::setInstance($container);
$translator = new Translator(new ArrayLoader(), 'uk');
$translator->addLines([
    'tasks.synchronize.categories' => 'Синхронізація категорій',
    'tasks.synchronize.products' => 'Синхронізація товарів',
    'tasks.check.products' => 'Перевірка товарів',
], 'uk');
$translator->addLines(['tasks.check.products' => 'Product check'], 'en');
$container->instance('translator', $translator);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) { throw new RuntimeException($message); }
};
$worker = new sWorker(['identifier' => 'catalogue', 'class' => ContextTitleWorker::class]);
$task = new sTaskModel(['identifier' => 'catalogue', 'action' => 'synchronize', 'meta' => ['entity' => 'categories'],
    'status' => sTaskModel::TASK_STATUS_FINISHED, 'progress' => 100, 'priority' => 'normal']);
$task->id = 19;
$task->exists = true;
$task->syncOriginal();
$task->setRelation('worker', $worker);
$task->setRelation('user', null);
$before = $task->getAttributes();
$assert($task->display_title === 'Catalogue · Синхронізація категорій', 'Historical tasks must receive contextual wording without a migration.');
$assert($task->getAttributes() === $before && !$task->isDirty(), 'Resolving a label must not rewrite task metadata or attributes.');
$assert($worker->title === 'Catalogue', 'Worker registry titles must remain stable.');
$task->meta = ['entity' => 'products'];
$assert($task->display_title === 'Catalogue · Синхронізація товарів', 'The stored entity must distinguish tasks of one worker.');
$task->action = 'check';
$assert($task->display_title === 'Catalogue · Перевірка товарів', 'The stored action must distinguish checks from imports.');
$translator->setLocale('en');
$expected = 'Catalogue · Product check';
$assert($task->display_title === $expected, 'The same historical row must follow the current manager locale.');

foreach ([
    [new DashboardData(), 'taskRow'],
    [new TasksTableData(), 'row'],
    [new LogsTableData(), 'basePayload'],
] as [$provider, $method]) {
    $row = (new ReflectionMethod($provider, $method))->invoke($provider, $task);
    $assert($row['worker_title'] === $expected, get_class($provider) . ' must use the contextual task title.');
    $assert(($row['identifier'] ?? $row['worker_identifier']) === 'catalogue', 'Filtering and routing must retain the registered identifier.');
}

foreach ([LegacyTitleWorker::class, PrivateTitleWorker::class, EmptyTitleWorker::class] as $class) {
    $task->setRelation('worker', new sWorker(['identifier' => 'legacy', 'class' => $class]));
    $assert($task->display_title === 'Legacy worker', 'Legacy, private and empty hooks must preserve the ordinary worker title.');
}
$task->setRelation('worker', new sWorker(['identifier' => 'missing_worker', 'class' => 'MissingTaskTitleFixture']));
$assert($task->display_title === 'Missing Worker', 'An unavailable worker implementation must preserve its existing fallback label.');
$task->setRelation('worker', null);
$assert($task->display_title === 'catalogue', 'An orphaned task must fall back to its persisted identifier.');

$task->setRelation('worker', new sWorker(['identifier' => 'markup', 'class' => MarkupTitleWorker::class]));
$expected = '<script>alert("title")</script> & label';
$assert($task->display_title === $expected, 'The hook must be trimmed and remain plain text at the model boundary.');
$view = (string)file_get_contents(dirname(__DIR__) . '/views/module/task-detail.blade.php');
$assert(preg_match('/\{\{\s*\$task->display_title\s*\}\}/', $view, $expression) === 1, 'Task details must use an escaped contextual-title expression.');
$compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
$compiled = $compiler->compileString($expression[0]);
$render = static function (sTaskModel $task, string $compiled): string {
    ob_start();
    try { eval('?>' . $compiled); return (string)ob_get_contents(); }
    finally { ob_end_clean(); }
};
$assert($render($task, $compiled) === e($expected), 'The actual task-details Blade expression must escape worker-supplied markup.');

echo "sTask contextual task titles OK ({$assertions} assertions; native models, row providers and Blade escaping)\n";

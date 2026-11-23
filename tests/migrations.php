<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;

require_once __DIR__ . '/bootstrap.php';

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
    echo "PASS {$message}\n";
};
$directory = dirname(__DIR__) . '/database/migrations';
$names = array_map(static fn (string $file): string => basename($file, '.php'), glob($directory . '/*.php'));
$setup = static function (): array {
    $container = new Container();
    Container::setInstance($container);
    $database = new Capsule($container);
    $database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'evo_']);
    $database->setAsGlobal();
    $database->bootEloquent();
    $container->instance('db', $database->getDatabaseManager());
    $container->instance('db.schema', $database->schema());
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($container);
    $schema = $database->schema();
    $schema->create('permissions_groups', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->string('lang_key');
        $table->timestamps();
    });
    $schema->create('permissions', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('key')->unique();
        $table->string('name');
        $table->string('lang_key');
        $table->unsignedInteger('group_id');
        $table->boolean('disabled');
        $table->timestamps();
    });
    $schema->create('role_permissions', static function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('role_id');
        $table->string('permission');
        $table->timestamps();
    });
    $repository = new DatabaseMigrationRepository($database->getDatabaseManager(), 'migrations');
    $repository->createRepository();
    return [$database, $repository, new Migrator($repository, $database->getDatabaseManager(), new Filesystem())];
};
$seed = static function (Capsule $db): void {
    $db->table('s_workers')->insert(['identifier' => 'gallery', 'class' => 'PreservedWorker', 'settings' => '{"enabled":true}']);
    $db->table('s_tasks')->insert(['identifier' => 'gallery', 'action' => 'optimize', 'meta' => '{"source":"original.png"}', 'result' => 'preserved', 'status' => 10]);
    $db->table('s_supervisor_states')->insert(['worker_id' => 1, 'identifier' => 'gallery', 'supervisor_key' => 'main', 'key_hash' => str_repeat('a', 64), 'state' => 'running']);
};
$snapshot = static function (Capsule $db): string {
    $rows = [];
    foreach (['s_workers', 's_tasks', 's_supervisor_states'] as $table) {
        $rows[$table] = $db->table($table)->orderBy('id')->get()->all();
    }
    return json_encode($rows, JSON_THROW_ON_ERROR);
};
$installer = static function (Capsule $db, array $migrations): void {
    $history = new DatabaseMigrationRepository($db->getDatabaseManager(), 'migrations_install');
    $history->createRepository();
    foreach ($migrations as $name) {
        $history->log($name, 1);
    }
};

[$db, $history, $migrator] = $setup();
$migrator->run([$directory]);
$seed($db);
$before = $snapshot($db);
$migrator->run([$directory]);
$check($history->getRan() === $names && $snapshot($db) === $before, 'Fresh install and repeated migrate preserve queued work');
$check($db->table('role_permissions')->where('permission', 'stask')->count() === 1, 'Repeated migrate does not duplicate admin access');
$migrator->rollback([$directory]);
$check(!$db->schema()->hasTable('s_workers') && !$db->schema()->hasTable('s_tasks') && !$db->schema()->hasTable('s_supervisor_states'), 'Application-created tables retain normal rollback');
$check(!$db->table('permissions')->where('key', 'stask')->exists() && !$db->table('role_permissions')->where('permission', 'stask')->exists(), 'Application-created permissions retain normal rollback');
$migrator->run([$directory]);
$check($history->getRan() === $names, 'Fresh installation can migrate again after rollback');

[$db, $history, $migrator] = $setup();
foreach ($names as $name) {
    (require $directory . '/' . $name . '.php')->up();
}
$installer($db, $names);
$seed($db);
$before = $snapshot($db);
$installerBefore = json_encode($db->table('migrations_install')->get()->all(), JSON_THROW_ON_ERROR);
$migrator->run([$directory]);
$check($history->getRan() === $names && $snapshot($db) === $before, 'Installer tables are adopted without replacing rows');
$permissionBefore = json_encode($db->table('permissions')->get()->all(), JSON_THROW_ON_ERROR);
$rolesBefore = json_encode($db->table('role_permissions')->get()->all(), JSON_THROW_ON_ERROR);
$migrator->rollback([$directory]);
$check($snapshot($db) === $before, 'Rollback preserves installer workers, tasks and supervisor state');
$check(json_encode($db->table('permissions')->get()->all(), JSON_THROW_ON_ERROR) === $permissionBefore && json_encode($db->table('role_permissions')->get()->all(), JSON_THROW_ON_ERROR) === $rolesBefore, 'Rollback preserves installer permissions and role assignments');
$migrator->run([$directory]);
$migrator->run([$directory]);
$check($history->getRan() === $names && $snapshot($db) === $before && json_encode($db->table('migrations_install')->get()->all(), JSON_THROW_ON_ERROR) === $installerBefore, 'Repeated adoption keeps separate histories and all queued work');

foreach (['s_workers', 's_tasks', 's_supervisor_states'] as $missing) {
    [$db, $history, $migrator] = $setup();
    foreach ($names as $name) {
        (require $directory . '/' . $name . '.php')->up();
    }
    $seed($db);
    $preserved = [];
    foreach (['s_workers', 's_tasks', 's_supervisor_states'] as $table) {
        if ($table !== $missing) {
            $preserved[$table] = json_encode($db->table($table)->get()->all(), JSON_THROW_ON_ERROR);
        }
    }
    $db->schema()->drop($missing);
    $migrator->run([$directory]);
    $check($db->schema()->hasTable($missing), "Partial installation recreates {$missing}");
    foreach ($preserved as $table => $rows) {
        $check(json_encode($db->table($table)->get()->all(), JSON_THROW_ON_ERROR) === $rows, "Repair of {$missing} preserves {$table}");
    }
}

[$db, $history, $migrator] = $setup();
$installer($db, ['another_package']);
$migrator->run([$directory]);
$migrator->rollback([$directory]);
$check(!$db->schema()->hasTable('s_tasks') && !$db->schema()->hasTable('s_supervisor_states'), 'Unrelated installer history does not block normal rollback');

echo "sTask migration regressions OK ({$checks} assertions)\n";

<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    $autoload = (string)getenv('STASK_AUTOLOAD');
}
if ($autoload === '' || !is_file($autoload)) {
    $autoload = dirname(__DIR__, 3) . '/autoload.php';
}
if (!is_file($autoload)) {
    throw new RuntimeException('Set STASK_AUTOLOAD to a Composer autoload.php path.');
}

require $autoload;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Seiger\sTask\Contracts\SupervisorWorkerInterface;
use Seiger\sTask\Enums\SupervisorState;
use Seiger\sTask\Models\sSupervisorState;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Models\sWorker;
use Seiger\sTask\Services\SupervisorService;
use Seiger\sTask\Support\SupervisorStatus;

$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$container = new Container();
Container::setInstance($container);
$container->instance('translator', new class {
    /**
     * Return a deterministic translated string for the isolated contract test.
     *
     * @param string $key Translation key
     * @param array<string, scalar> $replace Placeholder replacements
     * @return string Deterministic test translation
     */
    public function get(string $key, array $replace = []): string
    {
        $value = $key;
        foreach ($replace as $name => $replacement) {
            $value = str_replace(':' . $name, (string)$replacement, $value);
        }

        return $value;
    }
});

$database = new Capsule($container);
$database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$database->setAsGlobal();
$database->bootEloquent();

$schema = $database->schema();
$schema->create('s_workers', function (Blueprint $table): void {
    $table->increments('id');
    $table->string('identifier');
    $table->string('class')->default('FakeSupervisor');
    $table->boolean('active')->default(true);
    $table->text('settings')->nullable();
    $table->timestamps();
});
$schema->create('s_tasks', function (Blueprint $table): void {
    $table->increments('id');
    $table->string('identifier');
    $table->string('action');
    $table->unsignedSmallInteger('status');
    $table->text('message')->nullable();
    $table->unsignedInteger('started_by')->nullable();
    $table->text('meta')->nullable();
    $table->text('result')->nullable();
    $table->timestamp('start_at')->nullable();
    $table->timestamp('finished_at')->nullable();
    $table->integer('attempts')->default(0);
    $table->integer('max_attempts')->default(3);
    $table->string('priority')->default('normal');
    $table->integer('progress')->default(0);
    $table->timestamps();
});
$schema->create('s_supervisor_states', function (Blueprint $table): void {
    $table->increments('id');
    $table->unsignedBigInteger('worker_id');
    $table->string('identifier');
    $table->string('supervisor_key');
    $table->string('key_hash')->unique();
    $table->string('state');
    $table->unsignedBigInteger('pid')->nullable();
    $table->timestamp('heartbeat_at')->nullable();
    $table->timestamp('supervisor_started_at')->nullable();
    $table->unsignedBigInteger('uptime_seconds')->nullable();
    $table->text('message')->nullable();
    $table->string('fingerprint')->nullable();
    $table->timestamp('last_transition_at')->nullable();
    $table->timestamp('last_seen_at')->nullable();
    $table->unsignedInteger('repeat_count')->default(0);
    $table->timestamp('launch_requested_at')->nullable();
    $table->timestamps();
});

$workerRecord = sWorker::query()->create([
    'identifier' => 'fake-supervisor',
    'class' => 'FakeSupervisor',
    'active' => true,
    'settings' => ['schedule' => ['enabled' => true, 'type' => 'supervisor']],
]);

$adapter = new class implements SupervisorWorkerInterface {
    public SupervisorStatus $observed;
    public SupervisorStatus $launchResult;
    public int $starts = 0;
    public int $restarts = 0;

    /** Initialize deterministic stopped and starting snapshots. */
    public function __construct()
    {
        $this->observed = new SupervisorStatus(SupervisorState::Stopped, message: 'missing');
        $this->launchResult = new SupervisorStatus(SupervisorState::Starting, pid: 42, message: 'launch requested');
    }

    /** @return string Stable fake supervisor identity */
    public function supervisorKey(): string
    {
        return 'fake-session';
    }

    /** @return SupervisorStatus Mutable fake observation */
    public function inspectSupervisor(): SupervisorStatus
    {
        return $this->observed;
    }

    /** @return SupervisorStatus Immediate fake launch result */
    public function startSupervisor(): SupervisorStatus
    {
        $this->starts++;

        return $this->launchResult;
    }

    /** @return SupervisorStatus Immediate fake restart result */
    public function restartSupervisor(): SupervisorStatus
    {
        $this->restarts++;

        return $this->launchResult;
    }

    /** @return int Long grace period used to verify launch suppression */
    public function supervisorStartupGraceSeconds(): int
    {
        return 300;
    }
};

$supervisor = new class extends SupervisorService {
    /**
     * Run the serialized core directly so the test is independent of cache setup.
     *
     * @param sWorker $workerRecord Fake worker record
     * @param SupervisorWorkerInterface $worker Fake supervisor adapter
     * @return int Created lifecycle events
     */
    public function pass(sWorker $workerRecord, SupervisorWorkerInterface $worker): int
    {
        $key = $worker->supervisorKey();

        return $this->superviseLocked($workerRecord, $worker, $key, hash('sha256', $workerRecord->id . ':' . $key));
    }
};

$check($supervisor->pass($workerRecord, $adapter) === 1, 'Missing supervisor must create one start event.');
$check($adapter->starts === 1 && $adapter->restarts === 0, 'First pass must request one detached start.');
$check(sTaskModel::query()->count() === 1, 'First launch must create exactly one task event.');

$adapter->observed = new SupervisorStatus(SupervisorState::Starting, pid: 42, message: 'launch requested');
$check($supervisor->pass($workerRecord, $adapter) === 0, 'Starting supervisor inside grace must stay silent.');
$check($adapter->starts === 1 && $adapter->restarts === 0, 'Startup grace must suppress parallel launch.');

$adapter->observed = new SupervisorStatus(SupervisorState::Healthy, pid: 42, message: 'ready');
$check($supervisor->pass($workerRecord, $adapter) === 1, 'Recovery must create one lifecycle event.');
$check($supervisor->pass($workerRecord, $adapter) === 0, 'Unchanged healthy pass must create no task.');
$check(sTaskModel::query()->count() === 2, 'Healthy polling must not add task rows.');
$check((int)sSupervisorState::query()->first()->repeat_count === 1, 'Repeated diagnostic must increment repeat_count.');

$adapter->observed = new SupervisorStatus(SupervisorState::Healthy, pid: 42, message: 'new diagnostic');
$check($supervisor->pass($workerRecord, $adapter) === 1, 'Changed diagnostic fingerprint must create one event.');
$check(sTaskModel::query()->count() === 3, 'Diagnostic change must add exactly one event row.');

$healthy = new SupervisorStatus(SupervisorState::Healthy, pid: 42, message: 'ready');
$same = new SupervisorStatus(SupervisorState::Healthy, pid: 99, message: 'ready');
$changed = new SupervisorStatus(SupervisorState::Healthy, pid: 42, message: 'changed');
$check($healthy->diagnosticFingerprint() === $same->diagnosticFingerprint(), 'PID must not change the diagnostic fingerprint.');
$check($healthy->diagnosticFingerprint() !== $changed->diagnosticFingerprint(), 'Message changes must change the diagnostic fingerprint.');

echo "supervisor contract: ok\n";

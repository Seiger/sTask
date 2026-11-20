<?php namespace Seiger\sTask\Services;

use Seiger\sTask\Models\sTaskModel;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Bound child execution and retain uncertain outcomes without automatic retries.
 * @since 2.4.0
 */
class TaskExecution
{
    /** Resolve the wall-clock limit; slow workers can configure a longer limit. @since 2.4.0 */
    public function timeout(sTaskModel $task): int
    {
        $default = $task->identifier === 'sChatCenter' ? 120 : 900;
        return max(10, (int) config('seiger.settings.sTask.execution_timeout', $default));
    }

    /** Publish a lease, release the parent PDO and supervise one claimed task. @since 2.4.0 */
    public function run(sTaskModel $task): void
    {
        $token = bin2hex(random_bytes(16));
        $process = null;
        try {
            $path = $this->path((int) $task->id);
            $state = ['token' => $token, 'task_id' => (int) $task->id, 'attempt' => (int) $task->attempts,
                'deadline' => time() + $this->timeout($task), 'heartbeat_at' => time()];
            $this->write($path, $state);
            $connection = $task->getConnection();
            if ($connection->transactionLevel() > 0) {
                throw new \RuntimeException('Tasks must execute after the creating database transaction commits.');
            }
            // Waiting for a provider must not occupy the parent runner's PDO connection.
            $connection->disconnect();
            $process = $this->process($task, $token);
            $process->start();
            while ($process->isRunning()) {
                $process->checkTimeout();
                if (time() - $state['heartbeat_at'] >= 5) {
                    $state['heartbeat_at'] = time();
                    $this->write($path, $state);
                    @touch(storage_path('logs/sTask.heartbeat'));
                }
                usleep(250000);
            }
            if (!$process->isSuccessful() || $task->fresh()?->isRunning()) {
                $this->interrupt($task, 'Task process exited without completing (exit ' . $process->getExitCode() . ').');
            }
        } catch (\Throwable $exception) {
            $process?->stop(1);
            $this->interrupt($task, 'Task execution interrupted: ' . $exception->getMessage());
        } finally {
            // Retain the lease when the database is unavailable so recovery can settle it later.
            try {
                $fresh = $task->fresh();
                if (isset($path) && (!$fresh || !$fresh->isRunning() || $fresh->attempts !== $task->attempts)
                    && ($this->read((int) $task->id)['token'] ?? '') === $token) {
                    @unlink($path);
                }
            } catch (\Throwable) {
            }
        }
    }

    /** Build the internal CLI handoff with a private token and enforced timeout. @since 2.4.0 */
    protected function process(sTaskModel $task, string $token): Process
    {
        $core = defined('EVO_CORE_PATH') ? EVO_CORE_PATH : dirname(__DIR__, 5) . '/';
        $php = trim((string) config('seiger.settings.sTask.php_binary', '')) ?: (new PhpExecutableFinder())->find(false);
        if (!$php) {
            throw new \RuntimeException('PHP CLI executable was not found.');
        }
        $process = new Process([$php, rtrim($core, '/\\') . '/artisan', 'stask:worker',
            '--execute=' . (int) $task->id, '--lease=' . $token]);
        $process->setTimeout($this->timeout($task));
        $process->disableOutput();
        return $process;
    }

    /** Validate ownership, attempt and deadline before the child invokes a worker. @since 2.4.0 */
    public function authorized(sTaskModel $task, string $token): bool
    {
        $state = $this->read((int) $task->id);
        return $token !== '' && hash_equals((string) ($state['token'] ?? ''), $token)
            && (int) ($state['task_id'] ?? 0) === (int) $task->id
            && (int) ($state['attempt'] ?? -1) === (int) $task->attempts
            && (int) ($state['deadline'] ?? 0) > time()
            && (int) $task->status === sTaskModel::TASK_STATUS_RUNNING;
    }

    /** Settle expired leases only after acquiring the missing owner's task lock. @since 2.4.0 */
    public function recover(): void
    {
        foreach (glob(storage_path('stask/leases/task-*.json')) ?: [] as $path) {
            $state = json_decode((string) file_get_contents($path), true);
            if (!is_array($state) || (int) ($state['deadline'] ?? PHP_INT_MAX) >= time()
                || basename($path) !== 'task-' . (int) ($state['task_id'] ?? 0) . '.json') {
                continue;
            }
            $id = (int) ($state['task_id'] ?? 0);
            $lock = new ExecutionLock();
            if ($id <= 0 || !$lock->acquire('task:' . $id)) {
                continue;
            }
            try {
                $task = sTaskModel::query()->find($id);
                if ($task && (int) $task->attempts === (int) ($state['attempt'] ?? -1)) {
                    $this->interrupt($task, 'Task owner disappeared after its execution deadline.');
                }
                @unlink($path);
            } finally {
                $lock->release();
            }
        }
    }

    /** Fail the same running attempt without overwriting deferred or terminal rows. @since 2.4.0 */
    public function interrupt(sTaskModel $task, string $message): void
    {
        $result = ['interrupted' => true, 'delivery_outcome' => 'unknown', 'retry_safe' => false];
        $updated = sTaskModel::query()->whereKey($task->getKey())->where('attempts', $task->attempts)
            ->where('status', sTaskModel::TASK_STATUS_RUNNING)->update([
                'status' => sTaskModel::TASK_STATUS_FAILED, 'finished_at' => now(),
                'message' => $message . ' Outcome unknown; automatic retry is disabled.',
                'result' => json_encode($result), 'updated_at' => now(),
            ]);
        if ($updated === 1) {
            try {
                TaskProgress::write(['id' => (int) $task->id, 'identifier' => $task->identifier,
                    'action' => $task->action, 'status' => 'failed', 'progress' => (int) $task->progress,
                    'message' => $message . ' Outcome unknown; automatic retry is disabled.']);
            } catch (\Throwable) {
                // The durable task outcome must survive a progress-log write failure.
            }
        }
    }

    /** Read local execution metadata, never the task payload. @since 2.4.0 */
    public function read(int $id): array
    {
        $path = $this->path($id);
        return is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
    }

    /** Create the lease directory and resolve one numeric task filename. @since 2.4.0 */
    private function path(int $id): string
    {
        $directory = storage_path('stask/leases');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create sTask lease directory.');
        }
        return $directory . '/task-' . $id . '.json';
    }

    /** Publish a complete lease snapshot using a same-directory atomic rename. @since 2.4.0 */
    private function write(string $path, array $state): void
    {
        $temporary = $path . '.' . getmypid() . '.tmp';
        if (file_put_contents($temporary, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) === false
            || !rename($temporary, $path)) {
            throw new \RuntimeException('Unable to publish sTask execution heartbeat.');
        }
    }
}

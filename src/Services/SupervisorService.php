<?php namespace Seiger\sTask\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Seiger\sTask\Contracts\SupervisorWorkerInterface;
use Seiger\sTask\Enums\SupervisorState;
use Seiger\sTask\Models\sSupervisorState;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Models\sWorker;
use Seiger\sTask\Support\SupervisorStatus;
use Throwable;

/**
 * Supervises detached long-running processes without turning health polls into tasks.
 *
 * @package Seiger\sTask
 * @since 2.2.0
 */
class SupervisorService
{
    private const LOCK_SECONDS = 55;

    /**
     * Inspect and, when required, request a detached process launch or restart.
     *
     * A file-cache lock serializes scheduler passes for the same process. Healthy
     * unchanged observations only update the live-state cursor and create no task.
     *
     * @param sWorker $workerRecord Persisted worker configuration
     * @param SupervisorWorkerInterface $worker Generic supervisor adapter
     * @return int Number of lifecycle event tasks created by this pass
     */
    public function supervise(sWorker $workerRecord, SupervisorWorkerInterface $worker): int
    {
        try {
            $supervisorKey = trim($worker->supervisorKey());
        } catch (Throwable $exception) {
            return $this->recordContractFailure($workerRecord, 'supervisor-contract', $exception->getMessage());
        }
        if ($supervisorKey === '') {
            return $this->recordContractFailure($workerRecord, 'empty-supervisor-key', __('sTask::global.supervisor_empty_key'));
        }

        $keyHash = $this->keyHash((int)$workerRecord->id, $supervisorKey);
        $lock = Cache::store('file')->lock('stask:supervisor:' . $keyHash, self::LOCK_SECONDS);

        try {
            return (int)($lock->get(function () use ($workerRecord, $worker, $supervisorKey, $keyHash): int {
                return $this->superviseLocked($workerRecord, $worker, $supervisorKey, $keyHash);
            }) ?? 0);
        } catch (LockTimeoutException) {
            return 0;
        } catch (Throwable $exception) {
            return $this->recordContractFailure($workerRecord, $supervisorKey, $exception->getMessage());
        }
    }

    /**
     * Persist a configuration failure for a supervisor schedule without repeated rows.
     *
     * @param sWorker $workerRecord Persisted worker configuration
     * @param string $supervisorKey Stable diagnostic key
     * @param string $message Localized failure message
     * @return int Number of event tasks created
     */
    public function recordContractFailure(sWorker $workerRecord, string $supervisorKey, string $message): int
    {
        $keyHash = $this->keyHash((int)$workerRecord->id, $supervisorKey);
        $state = sSupervisorState::query()->firstOrNew(['key_hash' => $keyHash]);
        $status = new SupervisorStatus(SupervisorState::Failed, message: $message);
        $changed = !$state->exists || $state->fingerprint !== $status->diagnosticFingerprint();

        $this->persist($state, $workerRecord, $supervisorKey, $keyHash, $status, false);

        return $changed ? $this->createEvent($workerRecord, $supervisorKey, 'failed', $status) : 0;
    }

    /**
     * Execute one serialized supervision pass.
     *
     * @param sWorker $workerRecord Persisted worker configuration
     * @param SupervisorWorkerInterface $worker Generic supervisor adapter
     * @param string $supervisorKey Stable supervisor identity
     * @param string $keyHash Storage and lock identity
     * @return int Number of event tasks created
     */
    protected function superviseLocked(
        sWorker $workerRecord,
        SupervisorWorkerInterface $worker,
        string $supervisorKey,
        string $keyHash,
    ): int {
        $state = sSupervisorState::query()->firstOrNew(['key_hash' => $keyHash]);

        try {
            $observed = $worker->inspectSupervisor();
        } catch (Throwable $exception) {
            $observed = new SupervisorStatus(SupervisorState::Failed, message: $exception->getMessage());
        }

        $previousState = $state->exists ? (string)$state->state : null;
        $previousFingerprint = $state->exists ? (string)$state->fingerprint : null;
        $withinGrace = $this->withinStartupGrace($state, $worker->supervisorStartupGraceSeconds());
        $event = null;
        $status = $observed;
        $launchRequested = false;

        if ($observed->state->requiresLaunch() && !$withinGrace) {
            $firstLaunch = !$state->exists || $previousState === SupervisorState::Stopped->value;

            try {
                $status = $firstLaunch ? $worker->startSupervisor() : $worker->restartSupervisor();
                $launchRequested = true;
                $event = $firstLaunch ? 'started' : 'restarted';
            } catch (Throwable $exception) {
                $status = new SupervisorStatus(SupervisorState::Failed, message: $exception->getMessage());
                $launchRequested = true;
                $event = 'failed';
            }

            if ($status->state === SupervisorState::Stopped) {
                $event = 'stopped';
            } elseif ($status->state === SupervisorState::Failed) {
                $event = 'failed';
            }
        } elseif ($state->exists) {
            if ($observed->state->isHealthy() && $previousState !== SupervisorState::Healthy->value) {
                $event = 'recovered';
            } elseif ($previousState !== $observed->state->value) {
                $event = $observed->state->value;
            } elseif ($previousFingerprint !== $observed->diagnosticFingerprint()) {
                $event = 'diagnostic_changed';
            }
        }

        $changed = !$state->exists
            || $previousState !== $status->state->value
            || $previousFingerprint !== $status->diagnosticFingerprint();

        $this->persist($state, $workerRecord, $supervisorKey, $keyHash, $status, $launchRequested);

        return $event !== null && $changed
            ? $this->createEvent($workerRecord, $supervisorKey, $event, $status)
            : 0;
    }

    /**
     * Check whether a previous detached launch is still inside startup grace.
     *
     * @param sSupervisorState $state Persisted supervisor state
     * @param int $graceSeconds Adapter-defined grace period
     * @return bool True when a parallel launch must be suppressed
     */
    protected function withinStartupGrace(sSupervisorState $state, int $graceSeconds): bool
    {
        if (!$state->exists || !$state->launch_requested_at) {
            return false;
        }

        $graceSeconds = max(1, $graceSeconds);

        return (now()->timestamp - $state->launch_requested_at->timestamp) < $graceSeconds;
    }

    /**
     * Update the separate live-state cursor for a supervisor observation.
     *
     * @param sSupervisorState $state State model to update
     * @param sWorker $workerRecord Owning worker configuration
     * @param string $supervisorKey Stable supervisor identity
     * @param string $keyHash Storage identity
     * @param SupervisorStatus $status Latest status snapshot
     * @param bool $launchRequested Whether this pass requested a detached launch
     * @return void
     */
    protected function persist(
        sSupervisorState $state,
        sWorker $workerRecord,
        string $supervisorKey,
        string $keyHash,
        SupervisorStatus $status,
        bool $launchRequested,
    ): void {
        $fingerprint = $status->diagnosticFingerprint();
        $sameFingerprint = $state->exists && (string)$state->fingerprint === $fingerprint;
        $stateChanged = !$state->exists || (string)$state->state !== $status->state->value;

        $state->fill([
            'worker_id' => (int)$workerRecord->id,
            'identifier' => (string)$workerRecord->identifier,
            'supervisor_key' => $supervisorKey,
            'key_hash' => $keyHash,
            'state' => $status->state->value,
            'pid' => $status->pid,
            'heartbeat_at' => $status->heartbeatAt,
            'supervisor_started_at' => $status->startedAt,
            'uptime_seconds' => $status->uptimeSeconds,
            'message' => $status->message,
            'fingerprint' => $fingerprint,
            'last_transition_at' => $stateChanged ? now() : $state->last_transition_at,
            'last_seen_at' => now(),
            'repeat_count' => $sameFingerprint ? ((int)$state->repeat_count + 1) : 0,
            'launch_requested_at' => $status->state->isHealthy()
                ? null
                : ($launchRequested ? now() : $state->launch_requested_at),
        ]);
        $state->save();
    }

    /**
     * Create one completed or failed task row for a meaningful supervisor event.
     *
     * @param sWorker $workerRecord Owning worker configuration
     * @param string $supervisorKey Stable supervisor identity
     * @param string $event Lifecycle event code
     * @param SupervisorStatus $status Event status snapshot
     * @return int Always one after the event row is created
     */
    protected function createEvent(
        sWorker $workerRecord,
        string $supervisorKey,
        string $event,
        SupervisorStatus $status,
    ): int {
        $failed = in_array($status->state, [SupervisorState::Failed, SupervisorState::Degraded, SupervisorState::Stopped], true);
        $message = __('sTask::global.supervisor_event_' . $event, ['supervisor' => $supervisorKey]);
        if (trim($status->message) !== '') {
            $message .= ' ' . trim($status->message);
        }

        sTaskModel::query()->create([
            'identifier' => (string)$workerRecord->identifier,
            'action' => 'supervisor',
            'status' => $failed ? sTaskModel::TASK_STATUS_FAILED : sTaskModel::TASK_STATUS_FINISHED,
            'message' => $message,
            'started_by' => 0,
            'meta' => [
                'manual' => false,
                'supervisor_event' => $event,
                'supervisor_key' => $supervisorKey,
                'supervisor_state' => $status->state->value,
                'fingerprint' => $status->diagnosticFingerprint(),
            ],
            'start_at' => now(),
            'finished_at' => now(),
            'attempts' => 1,
            'max_attempts' => 1,
            'priority' => 'normal',
            'progress' => $failed ? 0 : 100,
        ]);

        return 1;
    }

    /**
     * Build a stable database and lock key for one worker supervisor.
     *
     * @param int $workerId Worker database identifier
     * @param string $supervisorKey Adapter-provided supervisor identity
     * @return string SHA-256 key hash
     */
    protected function keyHash(int $workerId, string $supervisorKey): string
    {
        return hash('sha256', $workerId . ':' . $supervisorKey);
    }
}

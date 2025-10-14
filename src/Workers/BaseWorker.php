<?php namespace Seiger\sTask\Workers;

use Seiger\sTask\Contracts\TaskInterface;
use Seiger\sTask\Models\sWorker;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Services\TaskProgress;

/**
 * BaseWorker - Abstract base class for sTask workers
 *
 * This abstract class provides a comprehensive foundation for all sTask workers.
 * It implements common functionality shared across all workers, including task management,
 * progress tracking, settings handling, and action dispatching.
 *
 * Key Features:
 * - Task creation and management through sTaskModel
 * - Progress tracking via TaskProgress file-based system
 * - Settings retrieval and management from database
 * - Action method resolution and invocation
 * - Error handling and task status management
 *
 * Worker Lifecycle:
 * 1. Constructor loads worker configuration from database
 * 2. createTask() creates new tasks with proper initialization
 * 3. invokeAction() dispatches actions to concrete implementation
 * 4. pushProgress() updates task progress in real-time
 * 5. markFinished()/markFailed() finalizes task execution
 *
 * Concrete implementations must:
 * - Implement TaskInterface methods (identifier, scope, icon, title, description, etc.)
 * - Define action methods following naming convention (task{Action})
 * - Handle specific business logic for their worker type
 *
 * @package Seiger\sTask\Workers
 * @author Seiger IT Team
 * @since 1.0.0
 */
abstract class BaseWorker implements TaskInterface
{
    /**
     * The worker instance loaded from the database or initialized with default values.
     *
     * @var sWorker
     */
    protected sWorker $worker;

    /**
     * BaseWorker constructor.
     *
     * This constructor initializes the worker by loading its configuration from the database
     * using the worker identifier. If no configuration exists, it creates a new instance with
     * default values including the worker identifier, scope, and class name.
     *
     * The worker configuration includes settings, active status, and other metadata
     * that controls the behavior of the worker.
     */
    public function __construct()
    {
        $this->worker = sWorker::where('identifier', $this->identifier())->first() ?? new sWorker([
            'identifier' => $this->identifier(),
            'scope' => $this->scope(),
            'class' => static::class,
        ]);
    }

    /**
     * Retrieve the settings of the worker.
     *
     * The settings are stored in the database as a JSON array and represent configurable options.
     *
     * @return array An associative array of settings for the worker.
     */
    public function settings(): array
    {
        $settings = $this->worker->settings ?? [];
        return is_array($settings) ? $settings : [];
    }

    /**
     * Create a new worker task and initialize progress tracking.
     *
     * This method creates a new sTaskModel record with the specified action and options,
     * initializes the TaskProgress system, and returns the created task instance.
     *
     * The method automatically:
     * - Retrieves options from the current request if not provided
     * - Determines the user who started the task
     * - Sets initial task status to pending (10)
     * - Initializes progress tracking with TaskProgress::init()
     *
     * @param string $action The action to perform (e.g., 'import', 'export', 'sync_stock')
     * @param array|null $options Optional explicit options (overrides request input)
     * @return sTaskModel The created task instance
     */
    public function createTask(string $action, ?array $options = null): sTaskModel
    {
        $options ??= (array)request()->input('options', []);

        $startedBy = 0;
        try {
            if (evo()->getLoginUserID()) {
                $startedBy = (int)evo()->getLoginUserID();
            }
        } catch (\Throwable) {}

        $task = sTaskModel::create([
            'identifier' => $this->identifier(),
            'action'     => $action,
            'status'     => 10, // pending
            'message'    => '_' . __('sTask::global.task_queued') . '..._',
            'started_by' => $startedBy,
            'meta'       => $options,
            'priority'   => 'normal',
        ]);

        TaskProgress::init([
            'id'         => (int)$task->id,
            'identifier' => $task->identifier,
            'action'     => $task->action,
            'status'     => 'pending',
            'message'    => $task->message,
        ]);

        return $task;
    }

    /**
     * Invoke a concrete action method by naming convention.
     *
     * This method dynamically calls the appropriate action method on the concrete worker
     * class based on the action name. The method name is derived by converting the action
     * to StudlyCase and prefixing with 'task' (e.g., 'export' becomes 'taskExport').
     *
     * @param string $action The action to invoke (e.g., 'export', 'import', 'sync_stock')
     * @param sTaskModel $task The task instance containing execution context
     * @param array $options Additional options to pass to the action method
     * @return void
     * @throws \BadMethodCallException If the action method doesn't exist
     */
    public function invokeAction(string $action, sTaskModel $task, array $options = []): void
    {
        $method = $this->resolveActionMethod($action);
        if (!method_exists($this, $method)) {
            throw new \BadMethodCallException(static::class." missing action method {$method}()");
        }
        $this->{$method}($task, $options);
    }

    /**
     * Resolve a method name from action using StudlyCase conversion.
     *
     * This method converts action names to method names by:
     * 1. Converting to lowercase
     * 2. Replacing hyphens and underscores with spaces
     * 3. Converting to StudlyCase (first letter of each word uppercase)
     * 4. Prefixing with 'task'
     *
     * Examples:
     * - "export" -> "taskExport"
     * - "sync_stock" -> "taskSyncStock"
     * - "import-csv" -> "taskImportCsv"
     *
     * @param string $action The action name to convert
     * @return string The resolved method name
     */
    protected function resolveActionMethod(string $action): string
    {
        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($action))));
        return 'task' . $studly;
    }

    /**
     * Push a volatile progress snapshot to the filesystem-based progress system.
     *
     * This method updates the task progress by writing a snapshot to the TaskProgress
     * system. It merges the provided delta with default values to ensure all required
     * fields are present. This approach avoids database churn during long-running tasks.
     *
     * The progress snapshot includes:
     * - Task identification (id, identifier, action)
     * - Current status and progress percentage
     * - Processing statistics (processed, total, eta)
     * - Current message and result information
     *
     * @param sTaskModel $task The task to update progress for
     * @param array $delta Progress delta to merge with defaults (e.g., ['status'=>'running','progress'=>55,'message'=>'...'])
     * @return void
     */
    protected function pushProgress(sTaskModel $task, array $delta = []): void
    {
        $payload = array_merge([
            'id'         => (int)$task->id,
            'identifier' => $task->identifier,
            'action'     => $task->action,
            'status'     => $task->status_text,
            'progress'   => 0,
            'processed'  => 0,
            'total'      => 0,
            'eta'        => '—',
            'message'    => $task->message,
            'result'     => $task->result,
        ], $delta);

        TaskProgress::write($payload);
    }

    /**
     * Mark task as finished with optional result file path and custom message.
     *
     * This method finalizes a task by updating its status to completed (30), setting the
     * finished timestamp, and optionally providing a result file path and custom message.
     * It also pushes a final progress update to the TaskProgress system.
     *
     * @param sTaskModel $task The task to mark as finished
     * @param string|null $result Path to the result file (for exports, downloads, etc.)
     * @param string|null $message Custom completion message (defaults to 'Done')
     * @return void
     */
    protected function markFinished(sTaskModel $task, ?string $result = null, ?string $message = null): void
    {
        $task->update([
            'status' => 30, // completed
            'message' => $message ?? __('sTask::global.done'),
            'result' => $result,
            'finished_at' => now(),
        ]);

        $this->pushProgress($task, [
            'status' => 'completed',
            'progress' => 100,
            'message' => $message ?? __('sTask::global.done'),
            'result' => $result,
        ]);
    }

    /**
     * Mark task as failed with error message.
     *
     * This method finalizes a task by updating its status to failed (40), setting the
     * finished timestamp, and providing an error message. It also pushes a final
     * progress update to the TaskProgress system with the error information.
     *
     * @param sTaskModel $task The task to mark as failed
     * @param string $message Error message describing the failure
     * @return void
     */
    protected function markFailed(sTaskModel $task, string $message): void
    {
        $task->update([
            'status' => 40, // failed
            'message' => $message,
            'finished_at' => now(),
        ]);

        $this->pushProgress($task, [
            'status' => 'failed',
            'message' => $message,
        ]);
    }

    /**
     * Format ETA seconds into human-readable format.
     *
     * Converts seconds into a user-friendly time format:
     * - Less than 60 seconds: "45s"
     * - Less than 1 hour: "5m 30s"
     * - 1 hour or more: "2h 15m"
     *
     * @param float $seconds Number of seconds to format
     * @return string Human-readable time format
     * 
     * @example
     * $this->formatEta(45.5);     // "46s"
     * $this->formatEta(150);      // "2m 30s"
     * $this->formatEta(3600);     // "1h 0m"
     * $this->formatEta(8100);     // "2h 15m"
     */
    protected function formatEta(float $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%.0fs', $seconds);
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return sprintf('%.0fm %.0fs', $minutes, $remainingSeconds);
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return sprintf('%.0fh %.0fm', $hours, $minutes);
        }
    }
}


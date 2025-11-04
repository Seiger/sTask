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
 * @package Seiger\sTask
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
     * Get a specific configuration value.
     *
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public function getConfig(string $key, $default = null)
    {
        $settings = $this->settings();

        // Support dot notation (e.g., 'schedule.enabled')
        $keys = explode('.', $key);
        $value = $settings;

        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set a specific configuration value.
     *
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $value Configuration value
     * @return void
     */
    public function setConfig(string $key, $value): void
    {
        $settings = $this->settings();

        // Support dot notation
        $keys = explode('.', $key);
        $current = &$settings;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }

        $this->worker->updateSettings($settings);
    }

    /**
     * Update multiple configuration values at once.
     *
     * @param array $config Configuration values to update
     * @return void
     */
    public function updateConfig(array $config): void
    {
        $settings = array_merge($this->settings(), $config);
        $this->worker->updateSettings($settings);
    }

    /**
     * Get schedule configuration.
     *
     * @return array Schedule configuration
     */
    public function getSchedule(): array
    {
        return $this->getConfig('schedule', [
            'type' => 'manual', // manual, once, periodic, regular
            'enabled' => false,
            'datetime' => null, // for 'once' type
            'time' => null, // for 'periodic' type (e.g., '14:00')
            'frequency' => 'hourly', // hourly, daily, weekly for 'periodic'
            'start_time' => null, // for 'regular' type (e.g., '05:00')
            'end_time' => null, // for 'regular' type (e.g., '23:00')
            'interval' => 'hourly', // hourly, every_30min, every_15min for 'regular'
        ]);
    }

    /**
     * Check if worker should run now based on schedule.
     *
     * @return bool True if should run, false otherwise
     */
    public function shouldRunNow(): bool
    {
        $schedule = $this->getSchedule();

        if (!($schedule['enabled'] ?? false)) {
            return false;
        }

        $now = time();
        $currentHour = (int)date('G');
        $currentMinute = (int)date('i');

        switch ($schedule['type'] ?? 'manual') {
            case 'once':
                $scheduledTime = strtotime($schedule['datetime'] ?? '');
                // Only return true if scheduled time has passed but not more than 30 seconds ago
                return $scheduledTime && $now >= $scheduledTime && ($now - $scheduledTime) < 30;

            case 'periodic':
                // Check if current time matches scheduled time
                if (!empty($schedule['time'])) {
                    [$hour, $minute] = explode(':', $schedule['time']);
                    if ((int)$hour === $currentHour && (int)$minute === $currentMinute) {
                        return true;
                    }
                }
                return false;

            case 'regular':
                // Check if within time range
                if (!empty($schedule['start_time']) && !empty($schedule['end_time'])) {
                    [$startHour, $startMin] = explode(':', $schedule['start_time']);
                    [$endHour, $endMin] = explode(':', $schedule['end_time']);

                    $currentTime = $currentHour * 60 + $currentMinute;
                    $startTime = (int)$startHour * 60 + (int)$startMin;
                    $endTime = (int)$endHour * 60 + (int)$endMin;

                    if ($currentTime < $startTime || $currentTime > $endTime) {
                        return false;
                    }

                    // Check interval
                    switch ($schedule['interval'] ?? 'hourly') {
                        case 'every_15min':
                            return $currentMinute % 15 === 0;
                        case 'every_30min':
                            return $currentMinute % 30 === 0;
                        case 'hourly':
                            return $currentMinute === 0;
                        default:
                            return false;
                    }
                }
                return false;

            case 'manual':
            default:
                return false;
        }
    }

    /**
     * Render the worker widget for the administrative interface.
     *
     * This method provides a default widget rendering implementation that can be
     * overridden in concrete worker classes for custom widget layouts. The default
     * widget includes basic worker information and a standard action button layout.
     *
     * The widget includes:
     * - Worker identification (icon, title, description)
     * - Available actions as buttons
     * - Worker settings and status
     * - Standard layout and styling
     *
     * Override this method in concrete workers to provide custom widget functionality,
     * additional controls, or specialized interfaces.
     *
     * @return string HTML content for the worker widget
     */
    public function renderWidget(): string
    {
        return view('sTask::partials.defaultWorkerWidget', [
            'worker' => $this->worker,
            'identifier' => $this->identifier(),
            'scope' => $this->scope(),
            'icon' => $this->icon(),
            'title' => $this->title(),
            'description' => $this->description(),
            'settings' => $this->settings(),
            'class' => static::class,
        ])->render();
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
        if ($options === null) {
            // Get options from request, including direct parameters
            $options = (array)request()->input('options', []);
            // Also include direct request parameters (like filename)
            $requestData = request()->all();
            if (isset($requestData['filename'])) {
                $options['filename'] = $requestData['filename'];
            }
        }

        $startedBy = 0;
        try {
            if (evo()->getLoginUserID()) {
                $startedBy = (int)evo()->getLoginUserID();
            }
        } catch (\Throwable) {}

        $task = sTaskModel::create([
            'identifier' => $this->identifier(),
            'action'     => $action,
            'status'     => sTaskModel::TASK_STATUS_QUEUED,
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
            'message'    => $task->message ?? '',
            'result'     => $task->result,
        ], $delta);

        TaskProgress::write($payload);
    }

    /**
     * Mark task as finished with optional result file path and custom message.
     *
     * This method finalizes a task by updating its status to finished, setting the
     * finished timestamp, and optionally providing a result file path and custom message.
     * It also pushes a final progress update to the TaskProgress system.
     *
     * @param sTaskModel $task The task to mark as finished
     * @param string|null $result Path to the result file (for exports, downloads, etc.) - stored in storage/stask/uploads/
     * @param string|null $message Custom completion message (defaults to 'Done')
     * @return void
     */
    protected function markFinished(sTaskModel $task, ?string $result = null, ?string $message = null): void
    {
        $task->update([
            'status' => sTaskModel::TASK_STATUS_FINISHED,
            'message' => $message ?? __('sTask::global.done'),
            'result' => $result,
            'finished_at' => now(),
        ]);

        $this->pushProgress($task, [
            'status' => $task->status_text,
            'progress' => 100,
            'message' => $message ?? __('sTask::global.done'),
            'result' => $result,
        ]);
    }

    /**
     * Mark task as failed with error message.
     *
     * This method finalizes a task by updating its status to failed, setting the
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
            'status' => sTaskModel::TASK_STATUS_FAILED,
            'message' => $message,
            'finished_at' => now(),
        ]);

        $this->pushProgress($task, [
            'status' => $task->status_text,
            'message' => $message,
        ]);
    }
}

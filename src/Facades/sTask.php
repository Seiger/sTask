<?php namespace Seiger\sTask\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class sTask Facade
 *
 * Facade for asynchronous task management service
 *
 * @method static \Seiger\sTask\Models\sTaskModel create(string $identifier, string $action, array $data = [], string $priority = 'normal', int $userId = null) Create a new task
 * @method static bool execute(\Seiger\sTask\Models\sTaskModel $task) Execute a task
 * @method static void retry(\Seiger\sTask\Models\sTaskModel $task) Retry a failed task
 * @method static \Illuminate\Support\Collection getPendingTasks(int $limit = 10) Get pending tasks
 * @method static int processPendingTasks(int $batchSize = null) Process pending tasks
 * @method static array getStats() Get task statistics
 * @method static int cleanOldTasks(int $days = 30) Clean old completed tasks
 * @method static int cleanOldLogs(int $days = 30) Clean old log files
 * @method static void log(\Seiger\sTask\Models\sTaskModel $task, string $level, string $message, array $context = []) Log a message for a task
 * @method static array discoverWorkers() Discover and register new workers
 * @method static \Seiger\sTask\Models\sWorker|null registerWorker(string $className) Register a single worker
 * @method static array rescanWorkers() Re-scan and update existing workers
 * @method static int cleanOrphanedWorkers() Clean orphaned workers
 * @method static \Illuminate\Support\Collection getWorkers(bool $activeOnly = false) Get all workers
 * @method static \Seiger\sTask\Models\sWorker|null getWorker(string $identifier) Get worker by identifier
 * @method static bool activateWorker(string $identifier) Activate worker
 * @method static bool deactivateWorker(string $identifier) Deactivate worker
 *
 * @mixin \Seiger\sTask\sTask
 *
 * @see \Seiger\sTask\sTask
 */
class sTask extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sTask';
    }
}
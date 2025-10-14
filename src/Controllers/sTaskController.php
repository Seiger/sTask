<?php namespace Seiger\sTask\Controllers;

use Seiger\sTask\Facades\sTask as sTaskFacade;
use Seiger\sTask\Models\sTaskModel;
use Illuminate\Http\Request;

/**
 * Class sTaskController
 *
 * Controller for managing tasks and workers
 *
 * @package Seiger\sTask\Controllers
 * @author Seiger IT Team
 * @since 1.0.0
 */
class sTaskController
{
    /**
     * Display tasks dashboard
     */
    public function index()
    {
        $data = [
            'tabIcon' => '<i data-lucide="layout-dashboard" class="w-6 h-6 text-blue-400 drop-shadow-[0_0_6px_#3b82f6]"></i>',
            'tabName' => __('sTask::global.dashboard'),
        ];

        $data['tasks'] = sTaskModel::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $data['stats'] = sTaskFacade::getStats();

        return view('sTask::dashboard', $data);
    }

    /**
     * Show specific task details
     */
    public function show(sTaskModel $task)
    {
        $task->load(['user', 'worker']);
        $logs = $task->getLastLogs(100);

        return view('sTask::task-details', compact('task', 'logs'));
    }

    /**
     * Get task logs
     */
    public function logs(sTaskModel $task)
    {
        $limit = request()->input('limit', 100);
        $logs = $task->getLogs($limit);

        return response()->json([
            'success' => true,
            'logs' => $logs,
            'count' => count($logs)
        ]);
    }

    /**
     * Download task logs
     */
    public function downloadLogs(sTaskModel $task)
    {
        return $task->logger()->downloadLogs($task);
    }

    /**
     * Clear task logs
     */
    public function clearLogs(sTaskModel $task)
    {
        $result = $task->clearLogs();

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Logs cleared successfully' : 'Failed to clear logs'
        ]);
    }

    /**
     * Create a new task
     */
    public function create(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'action' => 'required|string',
            'data' => 'array',
            'priority' => 'in:low,normal,high',
        ]);

        $task = sTaskFacade::create(
            $request->input('identifier'),
            $request->input('action'),
            $request->input('data', []),
            $request->input('priority', 'normal'),
            evo()->getLoginUserID()
        );

        return response()->json([
            'success' => true,
            'task' => $task,
            'message' => 'Task created successfully'
        ]);
    }

    /**
     * Execute a task manually
     */
    public function execute(sTaskModel $task)
    {
        if ($task->isRunning()) {
            return response()->json([
                'success' => false,
                'message' => 'Task is already running'
            ], 400);
        }

        $result = sTaskFacade::execute($task);

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Task executed successfully' : 'Task execution failed',
            'task' => $task->fresh()
        ]);
    }

    /**
     * Cancel a task
     */
    public function cancel(sTaskModel $task)
    {
        if ($task->isFinished()) {
            return response()->json([
                'success' => false,
                'message' => 'Task is already finished'
            ], 400);
        }

        $task->markAsCancelled('Cancelled by user');

        return response()->json([
            'success' => true,
            'message' => 'Task cancelled successfully',
            'task' => $task
        ]);
    }

    /**
     * Retry a failed task
     */
    public function retry(sTaskModel $task)
    {
        if (!$task->canRetry()) {
            return response()->json([
                'success' => false,
                'message' => 'Task cannot be retried'
            ], 400);
        }

        sTaskFacade::retry($task);

        return response()->json([
            'success' => true,
            'message' => 'Task scheduled for retry',
            'task' => $task->fresh()
        ]);
    }

    /**
     * Process pending tasks (for cron/manual execution)
     */
    public function process()
    {
        $processed = sTaskFacade::processPendingTasks();

        return response()->json([
            'success' => true,
            'message' => "Processed {$processed} tasks",
            'processed' => $processed
        ]);
    }

    /**
     * Get task statistics
     */
    public function stats()
    {
        $stats = sTaskFacade::getStats();

        return response()->json($stats);
    }

    /**
     * Clean old tasks
     */
    public function clean(Request $request)
    {
        $days = $request->input('days', 30);

        $deletedTasks = sTaskFacade::cleanOldTasks($days);
        $deletedLogs = sTaskFacade::cleanOldLogs($days);

        return response()->json([
            'success' => true,
            'message' => "Cleaned {$deletedTasks} tasks and {$deletedLogs} logs",
            'deleted_tasks' => $deletedTasks,
            'deleted_logs' => $deletedLogs
        ]);
    }

    /**
     * List all workers
     */
    public function workers(Request $request)
    {
        $data = [
            'tabIcon' => '<i data-lucide="cpu" class="w-6 h-6 text-blue-400 drop-shadow-[0_0_6px_#3b82f6]"></i>',
            'tabName' => __('sTask::global.workers'),
        ];

        $data['workers'] = sTaskFacade::getWorkers();
        $data['stats'] = sTaskFacade::getStats();

        return view('sTask::workers', $data);
    }

    /**
     * Discover new workers
     */
    public function discoverWorkers()
    {
        $registered = sTaskFacade::discoverWorkers();

        return response()->json([
            'success' => true,
            'message' => "Discovered and registered {count($registered)} new workers",
            'workers' => $registered,
            'count' => count($registered)
        ]);
    }

    /**
     * Rescan existing workers
     */
    public function rescanWorkers()
    {
        $updated = sTaskFacade::rescanWorkers();

        return response()->json([
            'success' => true,
            'message' => "Updated {count($updated)} workers",
            'workers' => $updated,
            'count' => count($updated)
        ]);
    }

    /**
     * Clean orphaned workers
     */
    public function cleanOrphanedWorkers()
    {
        $deleted = sTaskFacade::cleanOrphanedWorkers();

        return response()->json([
            'success' => true,
            'message' => "Removed {$deleted} orphaned workers",
            'deleted' => $deleted
        ]);
    }

    /**
     * Activate worker
     */
    public function activateWorker(Request $request)
    {
        $identifier = $request->input('identifier');
        $result = sTaskFacade::activateWorker($identifier);

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Worker activated' : 'Failed to activate worker'
        ]);
    }

    /**
     * Deactivate worker
     */
    public function deactivateWorker(Request $request)
    {
        $identifier = $request->input('identifier');
        $result = sTaskFacade::deactivateWorker($identifier);

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Worker deactivated' : 'Failed to deactivate worker'
        ]);
    }
}
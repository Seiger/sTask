<?php namespace Seiger\sTask\Controllers;

use Seiger\sTask\Facades\sTask as sTaskFacade;
use Seiger\sTask\Models\sTaskModel;
use Illuminate\Http\Request;

/**
 * Class sTaskController
 *
 * Controller for managing tasks and workers
 *
 * @package Seiger\sTask
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

        return view('sTask::task-details', compact('task'));
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

        return response()->json([
            'success' => true,
            'message' => "Cleaned {$deletedTasks} tasks",
            'deleted_tasks' => $deletedTasks
        ]);
    }

    /**
     * List all workers
     */
    public function workers(Request $request)
    {
        // Auto-discover new workers (like in sCommerce)
        $this->autoDiscoverWorkers();

        $data = [
            'tabIcon' => '<i data-lucide="cpu" class="w-6 h-6 text-blue-400 drop-shadow-[0_0_6px_#3b82f6]"></i>',
            'tabName' => __('sTask::global.workers'),
        ];

        $data['workers'] = sTaskFacade::getWorkers();
        $data['stats'] = sTaskFacade::getStats();

        return view('sTask::workers', $data);
    }

    /**
     * Auto-discover new workers (similar to sCommerce integration discovery)
     */
    private function autoDiscoverWorkers()
    {
        try {
            sTaskFacade::discoverWorkers();
        } catch (\Exception $e) {
            // Silently continue if discovery fails
            \Log::error('Auto-discovery failed: ' . $e->getMessage());
        }
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

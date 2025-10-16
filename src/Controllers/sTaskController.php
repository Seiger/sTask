<?php namespace Seiger\sTask\Controllers;

use Illuminate\Support\Facades\Log;
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
        if (!evo()->hasPermission('stask')) {abort(403, 'Access denied');}

        $data = [
            'tabIcon' => '<i data-lucide="layout-dashboard" class="w-6 h-6 text-blue-400 drop-shadow-[0_0_6px_#3b82f6]"></i>',
            'tabName' => __('sTask::global.dashboard'),
        ];

        $data['tasks'] = sTaskModel::orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $data['stats'] = sTaskFacade::getStats();
        return view('sTask::dashboard', $data);
    }

    /**
     * Create a new task
     */
    public function create(Request $request)
    {
        if (!evo()->hasPermission('stask')) {abort(403, 'Access denied');}

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
     * Store a new task (alias for create)
     */
    public function store(Request $request)
    {
        return $this->create($request);
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
        if (!evo()->hasPermission('stask')) {abort(403, 'Access denied');}

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
            Log::error('Auto-discovery failed: ' . $e->getMessage());
        }
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
     * Get system performance summary
     */
    public function getPerformanceSummary(Request $request)
    {
        // Check permissions
        if (!evo()->hasPermission('stask')) {abort(403, 'Access denied');}

        $hours = (int) $request->input('hours', 24);
        $metrics = sTaskFacade::getPerformanceMetrics($hours);

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Get worker performance statistics
     */
    public function getWorkerStats(Request $request)
    {
        // Check permissions
        if (!evo()->hasPermission('stask')) {abort(403, 'Access denied');}

        $identifier = $request->input('identifier');
        $hours = (int) $request->input('hours', 24);
        $stats = sTaskFacade::getWorkerStats($identifier, $hours);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get performance alerts
     */
    public function getPerformanceAlerts()
    {
        if (!evo()->hasPermission('stask')) {abort(403, 'Access denied');}

        $alerts = sTaskFacade::getPerformanceAlerts();

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats()
    {
        if (!evo()->hasPermission('stask')) {abort(403, 'Access denied');}

        $stats = sTaskFacade::getCacheStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Clear worker cache
     */
    public function clearCache(Request $request)
    {
        if (!evo()->hasPermission('stask')) {abort(403, 'Access denied');}

        $identifier = $request->input('identifier');
        sTaskFacade::clearWorkerCache($identifier);

        return response()->json([
            'success' => true,
            'message' => $identifier ? "Cache cleared for worker: {$identifier}" : 'All worker cache cleared',
        ]);
    }

    /**
     * Show worker settings page
     */
    public function workerSettings(Request $request, string $identifier)
    {
        if (!evo()->hasPermission('stask')) {abort(403, 'Access denied');}

        $worker = sTaskFacade::getWorker($identifier);
        if (!$worker) {abort(404, 'Worker not found');}

        $data = [
            'tabIcon' => '<i data-lucide="settings" class="w-6 h-6 text-blue-400 drop-shadow-[0_0_6px_#3b82f6]"></i>',
            'tabName' => __('sTask::global.worker_settings'),
            'worker' => $worker,
        ];

        return view('sTask::workerSettings', $data);
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

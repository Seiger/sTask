<?php namespace Seiger\sTask\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Seiger\sTask\Facades\sTask as sTaskFacade;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Models\sWorker;

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

    /**
     * Save worker settings.
     *
     * This method updates the worker configuration including endpoint, schedule,
     * and other worker-specific settings.
     *
     * @param string $identifier Worker identifier
     * @return JsonResponse JSON response with success status
     */
    public function saveWorkerSettings(Request $request, string $identifier): JsonResponse
    {
        try {
            $worker = sWorker::where('identifier', $identifier)->firstOrFail();
            $workerInstance = $worker->getInstance();

            if (!$workerInstance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Worker instance not found',
                ], 404);
            }

            $data = $request->all();

            // Prepare config - separate schedule from other settings
            $config = [];

            // Schedule configuration (if provided)
            if (isset($data['schedule'])) {
                $config['schedule'] = [
                    'type' => $data['schedule']['type'] ?? 'manual',
                    'enabled' => (bool)($data['schedule']['enabled'] ?? false),
                    'datetime' => $data['schedule']['datetime'] ?? null,
                    'time' => $data['schedule']['time'] ?? null,
                    'frequency' => $data['schedule']['frequency'] ?? 'hourly',
                    'start_time' => $data['schedule']['start_time'] ?? null,
                    'end_time' => $data['schedule']['end_time'] ?? null,
                    'interval' => $data['schedule']['interval'] ?? 'hourly',
                ];
                unset($data['schedule']);
            }

            // All other fields are custom worker settings
            // (endpoint, api_key, etc. - defined by worker's renderSettings)
            foreach ($data as $key => $value) {
                // Sanitize URLs
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    $config[$key] = filter_var($value, FILTER_SANITIZE_URL);
                } else {
                    $config[$key] = $value;
                }
            }

            // Update worker settings
            $workerInstance->updateConfig($config);

            // Clear worker cache to ensure fresh data on next load
            app(\Seiger\sTask\Services\WorkerService::class)->clearCache($identifier);

            Log::info('Worker settings updated', [
                'identifier' => $identifier,
                'config_keys' => array_keys($config),
            ]);

            // For 'once' schedule type, create task immediately with future start_at
            if (isset($config['schedule']) &&
                ($config['schedule']['enabled'] ?? false) &&
                ($config['schedule']['type'] ?? '') === 'once' &&
                !empty($config['schedule']['datetime'])) {

                // Delete any existing queued/preparing tasks for this worker
                $worker->tasks()
                    ->whereIn('status', [sTaskModel::TASK_STATUS_QUEUED, sTaskModel::TASK_STATUS_PREPARING])
                    ->delete();

                // Create new scheduled task
                $scheduledTime = \Carbon\Carbon::parse($config['schedule']['datetime']);
                $task = $workerInstance->createTask('make', ['manual' => false]);

                // Update start_at to scheduled time (this field controls when task should start)
                $task->update(['start_at' => $scheduledTime]);

                Log::info('Scheduled task created', [
                    'identifier' => $identifier,
                    'task_id' => $task->id,
                    'scheduled_for' => $scheduledTime->toDateTimeString(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => __('sTask::global.settings_saved'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to save worker settings', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('sTask::global.settings_save_failed') . ': ' . $e->getMessage(),
            ], 500);
        }
    }
}

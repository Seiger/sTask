<?php namespace Seiger\sTask\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Seiger\sTask\Facades\sTask as sTaskFacade;
use Seiger\sTask\Models\sTaskModel;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
        $this->authorizeStask();

        $tabs = [
            ['key' => 'dashboard', 'label' => __('sTask::global.dashboard'), 'icon' => 'layout-dashboard'],
            ['key' => 'tasks', 'label' => __('sTask::global.tasks'), 'icon' => 'list-checks'],
            ['key' => 'workers', 'label' => __('sTask::global.workers'), 'icon' => 'cpu'],
            ['key' => 'logs', 'label' => __('sTask::global.logs'), 'icon' => 'file-text'],
            ['key' => 'performance', 'label' => __('sTask::global.statistics'), 'icon' => 'chart-bar'],
        ];

        return view('sTask::module.shell', [
            'moduleTitle' => __('sTask::global.module_title'),
            'tabs' => $tabs,
            'activeTab' => $this->normalizeModuleTab((string)request()->query('get', 'dashboard'), $tabs),
            'context' => [
                'moduleUrl' => (string)request()->fullUrl(),
            ],
        ]);
    }

    protected function normalizeModuleTab(string $tab, array $tabs): string
    {
        $allowed = array_values(array_filter(array_map(
            static fn (array $item): string => (string)($item['key'] ?? ''),
            $tabs
        )));

        return in_array($tab, $allowed, true) ? $tab : ($allowed[0] ?? 'dashboard');
    }

    /**
     * Display task details and execution log.
     */
    public function show(int $id): View
    {
        $this->authorizeStask();

        $task = sTaskModel::with(['worker', 'user'])->findOrFail($id);

        return view('sTask::module.task-detail', [
            'tabIcon' => '<i data-lucide="file-text" class="w-6 h-6 text-blue-400 drop-shadow-[0_0_6px_#3b82f6]"></i>',
            'tabName' => __('sTask::global.task') . ' #' . $task->id,
            'task' => $task,
            'metaPretty' => $this->prettyPrintPayload($task->meta),
            'resultPretty' => $this->prettyPrintPayload($task->result),
        ]);
    }

    /**
     * Create a new task
     */
    public function create(Request $request)
    {
        $this->authorizeStask();

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
     * Pretty print task payload for diagnostic view.
     */
    private function prettyPrintPayload($payload): ?string
    {
        if ($payload === null || $payload === '' || $payload === []) {
            return null;
        }

        if (is_string($payload)) {
            return trim($payload) !== '' ? $payload : null;
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : (string)$payload;
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
        $this->authorizeStask();

        $this->autoDiscoverWorkers();

        return redirect()->route('sTask.index', ['get' => 'workers']);
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
        $this->authorizeStask();

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
        $this->authorizeStask();

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
        $this->authorizeStask();

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
        $this->authorizeStask();

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
        $this->authorizeStask();

        $identifier = $request->input('identifier');
        sTaskFacade::clearWorkerCache($identifier);

        return response()->json([
            'success' => true,
            'message' => $identifier ? "Cache cleared for worker: {$identifier}" : 'All worker cache cleared',
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

    /**
     * Ensure the current manager user can access sTask.
     *
     * @return void
     */
    private function authorizeStask(): void
    {
        if (!evo()->hasPermission('stask', 'mgr')) {
            throw new AccessDeniedHttpException('Access denied');
        }
    }
}

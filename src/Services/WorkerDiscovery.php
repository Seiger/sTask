<?php namespace Seiger\sTask\Services;

use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Seiger\sTask\Contracts\TaskInterface;
use Seiger\sTask\Models\sWorker;
use Throwable;

/**
 * Class WorkerDiscovery
 *
 * Automatically discovers and registers workers that implement TaskInterface
 *
 * @package Seiger\sTask
 * @author Seiger IT Team
 * @since 1.0.0
 */
class WorkerDiscovery
{
    /**
     * Discover and register new workers
     *
     * @return array Array of newly registered workers
     */
    public function discover(): array
    {
        $newWorkers = [];
        $existingWorkers = sWorker::all()->pluck('class')->toArray();

        $classMap = $this->getClassMap();
        $excludedNamespaces = $this->getExcludedNamespaces();

        foreach ($classMap as $className => $path) {
            // Skip excluded namespaces
            if ($this->isExcluded($className, $excludedNamespaces)) {
                continue;
            }

            try {
                if (class_exists($className)) {
                    $reflection = new ReflectionClass($className);

                    // Check if class implements TaskInterface and is not abstract
                    if ($reflection->implementsInterface(TaskInterface::class) && !$reflection->isAbstract()) {
                        $newWorkers[] = $className;
                    }
                }
            } catch (Throwable $e) {
                // Skip classes that can't be loaded
                continue;
            }
        }

        // Find workers that need to be registered
        $needRegistration = array_diff($newWorkers, $existingWorkers);
        $registered = [];

        if (!empty($needRegistration)) {
            foreach ($needRegistration as $workerClass) {
                try {
                    $worker = $this->registerWorker($workerClass);
                    if ($worker) {
                        $registered[] = $worker;
                    }
                } catch (Throwable $e) {
                    Log::error("Failed to register worker: {$workerClass}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }

        return $registered;
    }

    /**
     * Register a single worker
     *
     * @param string $className
     * @return sWorker|null
     */
    public function registerWorker(string $className): ?sWorker
    {
        try {
            // Create instance to get slug and description
            $instance = app($className);
            
            if (!$instance instanceof TaskInterface) {
                return null;
            }

            $identifier = $instance->identifier();
            
            // Check if worker with this identifier already exists
            if (sWorker::where('identifier', $identifier)->exists()) {
                Log::warning("Worker with identifier '{$identifier}' already exists", [
                    'existing_class' => sWorker::where('identifier', $identifier)->first()->class,
                    'new_class' => $className
                ]);
                return null;
            }

            $scope = method_exists($instance, 'scope') ? $instance->scope() : 'stask';

            $worker = sWorker::create([
                'identifier' => $identifier,
                'scope' => $scope,
                'class' => $className,
                'active' => false,
                'position' => sWorker::max('position') + 1,
                'settings' => [],
                'hidden' => 0,
            ]);

            Log::info("Worker registered successfully", [
                'identifier' => $identifier,
                'class' => $className
            ]);

            return $worker;
        } catch (Throwable $e) {
            Log::error("Failed to register worker: {$className}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get Composer classmap
     *
     * @return array
     */
    protected function getClassMap(): array
    {
        $classMapPath = base_path('vendor/composer/autoload_classmap.php');
        
        if (!file_exists($classMapPath)) {
            return [];
        }

        return require $classMapPath;
    }

    /**
     * Get excluded namespaces
     *
     * @return array
     */
    protected function getExcludedNamespaces(): array
    {
        $excludedPath = dirname(__DIR__, 2) . '/config/excluded_namespaces.php';
        
        if (!file_exists($excludedPath)) {
            return [];
        }

        return require $excludedPath;
    }

    /**
     * Check if class is in excluded namespace
     *
     * @param string $className
     * @param array $excludedNamespaces
     * @return bool
     */
    protected function isExcluded(string $className, array $excludedNamespaces): bool
    {
        foreach ($excludedNamespaces as $excludedNamespace) {
            if (str_starts_with($className, $excludedNamespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Re-scan and update existing workers
     *
     * @return array
     */
    public function rescan(): array
    {
        $updated = [];
        $workers = sWorker::all();

        foreach ($workers as $worker) {
            try {
                if (!class_exists($worker->class)) {
                    Log::warning("Worker class not found: {$worker->class}", [
                        'identifier' => $worker->identifier
                    ]);
                    continue;
                }

                $instance = app($worker->class);
                
                if (!$instance instanceof TaskInterface) {
                    Log::warning("Worker class does not implement TaskInterface: {$worker->class}", [
                        'identifier' => $worker->identifier
                    ]);
                    continue;
                }

                // Update identifier if changed
                $newIdentifier = $instance->identifier();
                if ($worker->identifier !== $newIdentifier) {
                    $worker->identifier = $newIdentifier;
                    $worker->save();
                    $updated[] = $worker;
                    
                    Log::info("Worker identifier updated", [
                        'old_identifier' => $worker->identifier,
                        'new_identifier' => $newIdentifier,
                        'class' => $worker->class
                    ]);
                }
            } catch (Throwable $e) {
                Log::error("Failed to rescan worker: {$worker->class}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $updated;
    }

    /**
     * Clean orphaned workers (classes no longer exist)
     *
     * @return int Number of deleted workers
     */
    public function cleanOrphaned(): int
    {
        $deleted = 0;
        $workers = sWorker::all();

        foreach ($workers as $worker) {
            if (!class_exists($worker->class)) {
                Log::info("Removing orphaned worker", [
                    'identifier' => $worker->identifier,
                    'class' => $worker->class
                ]);
                
                $worker->delete();
                $deleted++;
            }
        }

        return $deleted;
    }
}

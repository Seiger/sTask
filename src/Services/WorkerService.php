<?php namespace Seiger\sTask\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Seiger\sTask\Contracts\TaskInterface;
use Seiger\sTask\Exceptions\WorkerNotFoundException;
use Seiger\sTask\Exceptions\WorkerClassNotFoundException;
use Seiger\sTask\Exceptions\WorkerInvalidInterfaceException;
use Seiger\sTask\Models\sWorker;

/**
 * WorkerService - High-performance worker management service
 *
 * This service provides optimized worker resolution, caching, and management
 * for the sTask system. It implements caching strategies to minimize database
 * queries and improve overall system performance.
 *
 * Key Features:
 * - Worker instance caching with TTL
 * - Automatic cache invalidation
 * - Performance monitoring and metrics
 * - Error handling and logging
 * - Memory management and cleanup
 *
 * Performance Optimizations:
 * - Single database query per worker resolution
 * - In-memory caching with configurable TTL
 * - Lazy loading of worker instances
 * - Batch worker resolution for multiple identifiers
 *
 * @package Seiger\sTask
 * @author Seiger IT Team
 * @since 1.0.0
 */
class WorkerService
{
    /**
     * Cache key prefix for worker instances
     */
    private const CACHE_PREFIX = 'stask_worker_';

    /**
     * Default cache TTL in seconds (5 minutes)
     */
    private const DEFAULT_CACHE_TTL = 300;

    /**
     * Maximum cache size to prevent memory issues
     */
    private const MAX_CACHE_SIZE = 100;

    /**
     * In-memory cache for frequently accessed workers
     *
     * @var array<string, TaskInterface>
     */
    private static array $workerCache = [];

    /**
     * Cache statistics for monitoring
     *
     * @var array<string, int>
     */
    private static array $cacheStats = [
        'hits' => 0,
        'misses' => 0,
        'evictions' => 0,
    ];

    /**
     * Resolve a worker instance by identifier with caching
     *
     * This method provides optimized worker resolution with multiple layers
     * of caching to minimize database queries and improve performance.
     *
     * Caching Strategy:
     * 1. In-memory cache (fastest, per-request)
     * 2. Laravel cache (fast, shared across requests)
     * 3. Database query (slowest, fallback)
     *
     * @since 1.0.9
     * @param string $identifier The worker identifier to resolve
     * @param bool $forceRefresh Force refresh from database (bypass cache)
     * @return TaskInterface The resolved worker instance
     * @throws WorkerNotFoundException If worker not found or inactive
     * @throws WorkerClassNotFoundException If worker class not found
     * @throws WorkerInvalidInterfaceException If worker doesn't implement TaskInterface
     */
    public function resolveWorker(string $identifier, bool $forceRefresh = false): TaskInterface
    {
        // Check in-memory cache first
        if (!$forceRefresh && isset(self::$workerCache[$identifier])) {
            $worker = self::$workerCache[$identifier];
            if ($this->isValidWorkerInstance($worker)) {
                self::$cacheStats['hits']++;
                return $worker;
            }

            unset(self::$workerCache[$identifier]);
            self::$cacheStats['evictions']++;
            Log::warning('Invalid worker instance found in memory cache, evicted', [
                'identifier' => $identifier,
                'type' => is_object($worker) ? get_class($worker) : gettype($worker),
            ]);
        }

        // Check Laravel cache
        $cacheKey = self::CACHE_PREFIX . $identifier;
        if (!$forceRefresh && Cache::has($cacheKey)) {
            $worker = Cache::get($cacheKey);
            if ($this->isValidWorkerInstance($worker)) {
                self::$workerCache[$identifier] = $worker;
                self::$cacheStats['hits']++;
                return $worker;
            }

            Cache::forget($cacheKey);
            self::$cacheStats['evictions']++;
            Log::warning('Invalid worker instance found in persistent cache, evicted', [
                'identifier' => $identifier,
                'type' => is_object($worker) ? get_class($worker) : gettype($worker),
            ]);
        }

        // Database fallback
        self::$cacheStats['misses']++;
        $worker = $this->resolveWorkerFromDatabase($identifier);

        // Cache the resolved worker
        $this->cacheWorker($identifier, $worker);

        return $worker;
    }

    /**
     * Resolve multiple workers in a single database query
     *
     * This method optimizes the resolution of multiple workers by
     * fetching them all in a single database query and caching
     * them efficiently.
     *
     * @since 1.0.9
     * @param array<string> $identifiers Array of worker identifiers
     * @return array<string, TaskInterface> Array of resolved workers
     */
    public function resolveWorkers(array $identifiers): array
    {
        $workers = [];
        $missingIdentifiers = [];

        // Check cache for each identifier
        foreach ($identifiers as $identifier) {
            if (isset(self::$workerCache[$identifier])) {
                $worker = self::$workerCache[$identifier];
                if ($this->isValidWorkerInstance($worker)) {
                    $workers[$identifier] = $worker;
                    self::$cacheStats['hits']++;
                } else {
                    unset(self::$workerCache[$identifier]);
                    self::$cacheStats['evictions']++;
                    $missingIdentifiers[] = $identifier;
                }
            } else {
                $missingIdentifiers[] = $identifier;
            }
        }

        // Resolve missing workers from database
        if (!empty($missingIdentifiers)) {
            $dbWorkers = $this->resolveWorkersFromDatabase($missingIdentifiers);

            foreach ($dbWorkers as $identifier => $worker) {
                $workers[$identifier] = $worker;
                $this->cacheWorker($identifier, $worker);
            }
        }

        return $workers;
    }

    /**
     * Clear worker cache for a specific identifier or all workers
     *
     * @param string|null $identifier Worker identifier to clear, or null for all
     * @return void
     */
    public function clearCache(?string $identifier = null): void
    {
        if ($identifier === null) {
            // Clear all caches
            $cacheSize = count(self::$workerCache);
            self::$workerCache = [];
            self::$cacheStats['evictions'] += $cacheSize;

            // Clear Laravel cache - only if using Redis driver
            try {
                $store = Cache::getStore();
                if (method_exists($store, 'getRedis')) {
                    $redis = $store->getRedis();
                    $cacheKeys = $redis->keys(self::CACHE_PREFIX . '*');
                    if (!empty($cacheKeys)) {
                        $redis->del($cacheKeys);
                    }
                } else {
                    // For non-Redis stores, clear cache by iterating through known workers
                    $workers = sWorker::pluck('identifier');
                    foreach ($workers as $workerIdentifier) {
                        Cache::forget(self::CACHE_PREFIX . $workerIdentifier);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to clear worker cache from store', [
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            // Clear specific worker cache
            unset(self::$workerCache[$identifier]);
            Cache::forget(self::CACHE_PREFIX . $identifier);
            self::$cacheStats['evictions']++;
        }
    }

    /**
     * Get cache statistics for monitoring
     *
     * @return array<string, mixed> Cache statistics
     */
    public function getCacheStats(): array
    {
        $total = self::$cacheStats['hits'] + self::$cacheStats['misses'];
        $hitRate = $total > 0 ? (self::$cacheStats['hits'] / $total) * 100 : 0;

        return [
            'hits' => self::$cacheStats['hits'],
            'misses' => self::$cacheStats['misses'],
            'evictions' => self::$cacheStats['evictions'],
            'hit_rate' => round($hitRate, 2),
            'cache_size' => count(self::$workerCache),
            'memory_usage' => memory_get_usage(true),
        ];
    }

    /**
     * Resolve worker from database with optimized query
     *
     * @param string $identifier Worker identifier
     * @return TaskInterface Resolved worker instance
     * @throws WorkerNotFoundException
     * @throws WorkerClassNotFoundException
     * @throws WorkerInvalidInterfaceException
     */
    private function resolveWorkerFromDatabase(string $identifier): TaskInterface
    {
        $workerRecord = sWorker::query()
            ->active()
            ->where('identifier', $identifier)
            ->select(['id', 'identifier', 'class', 'active', 'settings'])
            ->first();

        if (!$workerRecord) {
            throw new WorkerNotFoundException($identifier);
        }

        return $this->instantiateWorker($workerRecord);
    }

    /**
     * Resolve multiple workers from database in a single query
     *
     * @param array<string> $identifiers Worker identifiers
     * @return array<string, TaskInterface> Resolved workers
     */
    private function resolveWorkersFromDatabase(array $identifiers): array
    {
        $workerRecords = sWorker::query()
            ->active()
            ->whereIn('identifier', $identifiers)
            ->select(['id', 'identifier', 'class', 'active', 'settings'])
            ->get()
            ->keyBy('identifier');

        $workers = [];
        foreach ($identifiers as $identifier) {
            if (isset($workerRecords[$identifier])) {
                try {
                    $workers[$identifier] = $this->instantiateWorker($workerRecords[$identifier]);
                } catch (\Exception $e) {
                    Log::warning('Failed to instantiate worker', [
                        'identifier' => $identifier,
                        'error' => $e->getMessage(),
                    ]);
                    // Skip failed workers
                }
            }
        }

        return $workers;
    }

    /**
     * Instantiate worker class and validate interface
     *
     * @param sWorker $workerRecord Worker database record
     * @return TaskInterface Instantiated worker
     * @throws WorkerClassNotFoundException
     * @throws WorkerInvalidInterfaceException
     */
    private function instantiateWorker(sWorker $workerRecord): TaskInterface
    {
        $className = $workerRecord->class;

        if (!$className || !class_exists($className)) {
            throw new WorkerClassNotFoundException($className);
        }

        try {
            $instance = app()->make($className);
        } catch (\Exception $e) {
            throw new WorkerClassNotFoundException($className, $e);
        }

        if (!$instance instanceof TaskInterface) {
            throw new WorkerInvalidInterfaceException($className);
        }

        return $instance;
    }

    /**
     * Cache worker instance with size management
     *
     * @param string $identifier Worker identifier
     * @param TaskInterface $worker Worker instance
     * @return void
     */
    private function cacheWorker(string $identifier, TaskInterface $worker): void
    {
        // Add to in-memory cache
        self::$workerCache[$identifier] = $worker;

        // Manage cache size
        if (count(self::$workerCache) > self::MAX_CACHE_SIZE) {
            // Remove oldest entries (simple FIFO)
            $keysToRemove = array_slice(array_keys(self::$workerCache), 0, 10);
            foreach ($keysToRemove as $key) {
                unset(self::$workerCache[$key]);
                self::$cacheStats['evictions']++;
            }
        }

        // Add to Laravel cache with TTL
        $cacheKey = self::CACHE_PREFIX . $identifier;
        Cache::put($cacheKey, $worker, self::DEFAULT_CACHE_TTL);
    }

    /**
     * Clean up memory and cache resources
     *
     * This method should be called periodically or when memory usage
     * becomes high to prevent memory leaks.
     *
     * @return void
     */
    public function cleanup(): void
    {
        // Clear in-memory cache
        self::$workerCache = [];

        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        Log::info('WorkerService cache cleaned up', [
            'memory_before' => memory_get_usage(true),
            'memory_after' => memory_get_usage(true),
        ]);
    }

    /**
     * Validate cached worker instance type.
     *
     * @since 1.0.9
     * @param mixed $worker
     * @return bool
     */
    private function isValidWorkerInstance(mixed $worker): bool
    {
        return $worker instanceof TaskInterface;
    }
}

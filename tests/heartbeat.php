<?php

declare(strict_types=1);

namespace Illuminate\Console {
    /** Minimal console parent for the isolated runner contract test. @since 2.2.0 */
    class Command {}
}

namespace Illuminate\Support\Facades {
    /** Captures marker-write warnings without bootstrapping Laravel. @since 2.2.0 */
    class Log
    {
        public static array $warnings = [];
        /** Capture a warning for assertions. @since 2.2.0 */
        public static function warning(string $message): void
        {
            self::$warnings[] = $message;
        }
    }
}

namespace Seiger\sTask\Models {
    /** Stops execution at the first queue operation after recording the marker. @since 2.2.0 */
    class sWorker
    {
        /** Signal that runner execution reached scheduling. @since 2.2.0 */
        public static function where(...$arguments): never
        {
            throw new \RuntimeException('Queue access reached');
        }
    }
}

namespace {
    $directory = sys_get_temp_dir() . '/stask-heartbeat-' . bin2hex(random_bytes(8));
    mkdir($directory . '/logs', 0700, true);

    function storage_path(string $path): string
    {
        return $GLOBALS['directory'] . '/' . $path;
    }

    require dirname(__DIR__) . '/src/sTask.php';
    require dirname(__DIR__) . '/src/Console/TaskWorker.php';

    $check = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };
    $service = (new ReflectionClass(\Seiger\sTask\sTask::class))->newInstanceWithoutConstructor();
    $path = storage_path('logs/sTask.heartbeat');

    try {
        $check(!$service->heartbeat(), 'Missing marker must be unavailable');
        $check($service->heartbeatStatus()['state'] === 'missing', 'Missing marker must have its own display state');
        $check(!file_exists($path), 'Reading must not create a heartbeat');

        touch($path);
        $check($service->heartbeat(), 'Fresh marker must be available');
        $check($service->heartbeatStatus()['state'] === 'available', 'Fresh marker must display as available');
        touch($path, time() - 3611);
        $check(!$service->heartbeat(), 'Expired marker must be unavailable despite prior stat cache');
        $check($service->heartbeatStatus()['state'] === 'expired', 'Expired marker must display as unavailable');
        touch($path, time() - 3609);
        $check($service->heartbeat(), 'Default TTL must allow a marker younger than 3610 seconds');
        $check(!$service->heartbeat(60), 'Custom TTL must be respected');
        $check(!$service->heartbeat(-1), 'Negative TTL must be unavailable');
        touch($path, time() + 60);
        $check(!$service->heartbeat(), 'Future timestamp must be unavailable');
        $check($service->heartbeatStatus()['state'] === 'error', 'Future timestamp must display a check error');

        unlink($path);
        $runner = new \Seiger\sTask\Console\TaskWorker();
        try {
            $runner->handle();
        } catch (RuntimeException $exception) {
            $check($exception->getMessage() === 'Queue access reached', 'Unexpected runner exception');
        }
        $check($service->heartbeat(), 'Runner must create its marker before queue access');

        unlink($path);
        rmdir($directory . '/logs');
        try {
            $runner->handle();
        } catch (RuntimeException $exception) {
            $check($exception->getMessage() === 'Queue access reached', 'Write failure must not stop queue access');
        }
        $check(count(\Illuminate\Support\Facades\Log::$warnings) === 1, 'Failed write must be logged');
        $check(!$service->heartbeat(), 'Failed write must not report a fresh heartbeat');
        echo "sTask heartbeat OK\n";
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
        if (is_dir($directory . '/logs')) {
            rmdir($directory . '/logs');
        }
        rmdir($directory);
    }
}

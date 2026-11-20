<?php namespace Seiger\sTask\Services;

/**
 * Coalesce web/event wakeups before PHP starts and opens database connections.
 * @since 2.4.0
 */
class QueueWakeup
{
    /** Allow one wakeup per 30 seconds when no local runner owns the queue. @since 2.4.0 */
    public function shouldLaunch(): bool
    {
        $runner = new ExecutionLock();
        if (!$runner->acquire('runner')) {
            return false;
        }
        $runner->release();
        $handle = fopen(storage_path('stask/leases/wakeup.lock'), 'c+');
        if (!$handle || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) { fclose($handle); }
            return false;
        }
        try {
            $last = (int) stream_get_contents($handle);
            if ($last > time() - 30) {
                return false;
            }
            rewind($handle);
            ftruncate($handle, 0);
            if (fwrite($handle, (string) time()) === false || !fflush($handle)) {
                throw new \RuntimeException('Unable to record sTask queue wakeup.');
            }
            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

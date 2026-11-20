<?php namespace Seiger\sTask\Services;

/**
 * Process-owned locks released by the OS, including after a fatal error.
 * @since 2.4.0
 */
class ExecutionLock
{
    private mixed $handle = null;

    /** Acquire a nonblocking local runner or task lock. @since 2.4.0 */
    public function acquire(string $key): bool
    {
        $directory = storage_path('stask/leases');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create sTask lease directory.');
        }
        $this->handle = fopen($directory . '/' . hash('sha256', $key) . '.lock', 'c+');
        if ($this->handle === false) {
            throw new \RuntimeException('Unable to open sTask execution lock.');
        }
        if (!flock($this->handle, LOCK_EX | LOCK_NB)) {
            fclose($this->handle);
            $this->handle = null;
            return false;
        }
        return true;
    }

    /** Release only this instance's file handle. @since 2.4.0 */
    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
        $this->handle = null;
    }

    /** Release the process-owned handle during normal shutdown. @since 2.4.0 */
    public function __destruct()
    {
        $this->release();
    }
}

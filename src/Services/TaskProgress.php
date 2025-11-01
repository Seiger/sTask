<?php namespace Seiger\sTask\Services;

/**
 * TaskProgress - Filesystem-based task progress tracking system
 *
 * This class provides a lightweight, configuration-free service for tracking
 * task progress through filesystem-based structured LOG files. It's designed to be
 * efficient, reliable, and suitable for high-frequency updates without causing
 * database churn during long-running tasks.
 *
 * Key Features:
 * - Filesystem-based storage for high performance
 * - Append-only log format (no file locking needed)
 * - Structured format: status|progress|processed|total|eta|message
 * - Full history of all progress updates
 * - Minimal memory footprint and configuration
 *
 * Storage Structure:
 * - Progress files stored in storage/stask/
 * - Each task has a unique LOG file (taskId.log)
 * - Structured format with pipe-separated values
 * - Cleanup handled by TaskWorker command when idle
 *
 * Usage Pattern:
 * 1. Initialize progress with TaskProgress::init()
 * 2. Update progress with TaskProgress::write() (appends new line)
 * 3. Read current state with TaskProgress::readProgress() (last line)
 * 4. Read log history with TaskProgress::readLog() (last N messages)
 * 5. Cleanup via TaskWorker command (stask:worker)
 *
 * @package Seiger\sTask
 * @author Seiger IT Team
 * @since 1.0.0
 */
class TaskProgress
{
    /**
     * Ensure and return directory for progress snapshots.
     *
     * This method creates the progress directory if it doesn't exist and returns
     * the full path. The directory is created with appropriate permissions (0775)
     * to ensure proper access for the web server and application.
     *
     * @return string The full path to the progress directory
     */
    public static function dir(): string
    {
        $dir = storage_path('stask');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return $dir;
    }

    /**
     * Get the file path for a task's progress log.
     *
     * This method constructs the full file path for a task's progress log
     * based on the task ID. The file will be a structured LOG file with format:
     * status|progress|processed|total|eta|message
     *
     * @param int|string $taskId The task ID to get the file path for
     * @return string The full file path to the task's progress log
     */
    public static function file(int|string $taskId): string
    {
        return self::dir() . '/' . $taskId . '.log';
    }

    /**
     * Read the current progress state from the last line of the log file.
     *
     * Parses the last line of the log to extract current status, progress, etc.
     * Format: status|progress|processed|total|eta|message
     *
     * @param int|string $taskId The task ID
     * @return array|null Parsed progress data or null if file doesn't exist
     */
    public static function readProgress(int|string $taskId): ?array
    {
        $file = self::file($taskId);

        if (!is_file($file)) {
            return null;
        }

        // Read last line efficiently
        $handle = @fopen($file, 'r');
        if ($handle === false) {
            return null;
        }

        $lastLine = '';
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line !== false) {
                $lastLine = $line;
            }
        }
        fclose($handle);

        // Parse last line: status|progress|processed|total|eta|message
        $parts = explode('|', trim($lastLine), 6);

        if (count($parts) < 6) {
            return null;
        }

        return [
            'id' => (int)$taskId,
            'status' => $parts[0],
            'progress' => (int)$parts[1],
            'processed' => (int)$parts[2],
            'total' => (int)$parts[3],
            'eta' => $parts[4],
            'message' => $parts[5] ?? '',
        ];
    }

    /**
     * Read the last N log entries (messages only).
     *
     * @param int|string $taskId The task ID
     * @param int $lines Number of lines to read (default: 50)
     * @return array Array of log messages
     */
    public static function readLog(int|string $taskId, int $lines = 50): array
    {
        $file = self::file($taskId);

        if (!is_file($file)) {
            return [];
        }

        // Read file into array of lines
        $allLines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($allLines === false) {
            return [];
        }

        // Get last N lines
        $recentLines = array_slice($allLines, -$lines);

        // Extract message part (6th column)
        $messages = [];
        foreach ($recentLines as $line) {
            $parts = explode('|', $line, 6);
            if (count($parts) >= 6) {
                $messages[] = $parts[5]; // message column
            }
        }

        return $messages;
    }

    /**
     * Initialize a new progress log with default values.
     *
     * This method creates the initial progress log entry for a task with sensible
     * default values. It's typically called when a task is first created to establish
     * the initial state before any processing begins.
     *
     * Required payload keys:
     * - id (required) - The task ID
     *
     * Optional payload keys (with defaults):
     * - status - Task status (default: 'queued')
     * - progress - Progress percentage (default: 0)
     * - processed - Processed items (default: 0)
     * - total - Total items (default: 0)
     * - eta - Estimated time (default: '—')
     * - message - Status message (default: '')
     *
     * @param array<string,mixed> $payload The initial progress data
     * @throws \InvalidArgumentException If required 'id' key is missing
     */
    public static function init(array $payload): void
    {
        if (!isset($payload['id'])) {
            throw new \InvalidArgumentException('Progress payload must contain "id".');
        }

        self::write($payload);
    }

    /**
     * Write a progress entry to the log file.
     *
     * This method appends a structured log entry to the task's progress log.
     * Format: status|progress|processed|total|eta|message
     *
     * The write process:
     * 1. Validates required 'id' key in payload
     * 2. Formats data as pipe-separated line
     * 3. Appends to log file (no locking needed for append operations)
     *
     * @param array<string,mixed> $payload The progress data to write
     * @throws \InvalidArgumentException If required 'id' key is missing
     */
    public static function write(array $payload): void
    {
        if (!isset($payload['id'])) {
            throw new \InvalidArgumentException('Progress payload must contain "id".');
        }

        $file = self::file((string)$payload['id']);

        // Format: status|progress|processed|total|eta|message
        $status = $payload['status'] ?? 'unknown';
        $progress = $payload['progress'] ?? 0;
        $processed = $payload['processed'] ?? 0;
        $total = $payload['total'] ?? 0;
        $eta = $payload['eta'] ?? '—';
        $message = $payload['message'] ?? '';

        // Escape pipe characters in message to avoid breaking format
        $message = str_replace('|', '¦', $message);

        // Build line
        $line = implode('|', [$status, $progress, $processed, $total, $eta, $message]);

        // Append to log file (FILE_APPEND is atomic on most systems)
        file_put_contents($file, $line . "\n", FILE_APPEND);
        @chmod($file, 0664);
    }
}

<?php namespace Seiger\sTask\Services;

use Seiger\sTask\Models\sTaskModel;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

/**
 * Class TaskLogger
 *
 * Handles file-based logging for tasks
 *
 * @package Seiger\sTask\Services
 * @author Seiger IT Team
 * @since 1.0.0
 */
class TaskLogger
{
    /**
     * Log storage path
     */
    protected string $logPath;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logPath = storage_path('stask');
        $this->ensureLogDirectoryExists();
    }

    /**
     * Ensure log directory exists
     */
    protected function ensureLogDirectoryExists(): void
    {
        if (!File::isDirectory($this->logPath)) {
            File::makeDirectory($this->logPath, 0755, true);
        }
    }

    /**
     * Get log file path for task
     */
    public function getLogFilePath(sTaskModel $task): string
    {
        return $this->logPath . '/' . $task->id . '.log';
    }

    /**
     * Write log entry
     */
    public function log(sTaskModel $task, string $level, string $message, array $context = []): void
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s.u');
        $contextJson = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        $logEntry = sprintf(
            "[%s] [%s] %s%s\n",
            $timestamp,
            strtoupper($level),
            $message,
            $contextJson
        );

        File::append($this->getLogFilePath($task), $logEntry);
    }

    /**
     * Get task logs
     */
    public function getLogs(sTaskModel $task, int $limit = null): array
    {
        $logFile = $this->getLogFilePath($task);
        
        if (!File::exists($logFile)) {
            return [];
        }

        $logs = File::lines($logFile)->all();
        
        if ($limit) {
            $logs = array_slice($logs, -$limit);
        }

        return array_map(function($line) {
            return $this->parseLogLine($line);
        }, $logs);
    }

    /**
     * Parse log line
     */
    protected function parseLogLine(string $line): array
    {
        // Pattern: [2025-10-15 12:34:56.123456] [INFO] Message {"context":"data"}
        preg_match('/\[(.*?)\]\s+\[(.*?)\]\s+(.*?)(\s+\{.*\})?$/', $line, $matches);
        
        if (empty($matches)) {
            return [
                'timestamp' => null,
                'level' => 'unknown',
                'message' => $line,
                'context' => []
            ];
        }

        return [
            'timestamp' => $matches[1] ?? null,
            'level' => strtolower($matches[2] ?? 'unknown'),
            'message' => $matches[3] ?? '',
            'context' => isset($matches[4]) ? json_decode(trim($matches[4]), true) : []
        ];
    }

    /**
     * Get last N log entries
     */
    public function getLastLogs(sTaskModel $task, int $count = 10): array
    {
        return $this->getLogs($task, $count);
    }

    /**
     * Get logs by level
     */
    public function getLogsByLevel(sTaskModel $task, string $level): array
    {
        $logs = $this->getLogs($task);
        
        return array_filter($logs, function($log) use ($level) {
            return strtolower($log['level']) === strtolower($level);
        });
    }

    /**
     * Get error logs
     */
    public function getErrorLogs(sTaskModel $task): array
    {
        return $this->getLogsByLevel($task, 'error');
    }

    /**
     * Clear task logs
     */
    public function clearLogs(sTaskModel $task): bool
    {
        $logFile = $this->getLogFilePath($task);
        
        if (File::exists($logFile)) {
            return File::delete($logFile);
        }

        return true;
    }

    /**
     * Clear old logs
     */
    public function clearOldLogs(int $days = 30): int
    {
        $cutoff = Carbon::now()->subDays($days);
        $deleted = 0;

        $files = File::files($this->logPath);
        
        foreach ($files as $file) {
            if (File::lastModified($file) < $cutoff->timestamp) {
                File::delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get log file size
     */
    public function getLogSize(sTaskModel $task): int
    {
        $logFile = $this->getLogFilePath($task);
        
        if (!File::exists($logFile)) {
            return 0;
        }

        return File::size($logFile);
    }

    /**
     * Get formatted log size
     */
    public function getFormattedLogSize(sTaskModel $task): string
    {
        $bytes = $this->getLogSize($task);
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Export logs to string
     */
    public function exportLogs(sTaskModel $task): string
    {
        $logFile = $this->getLogFilePath($task);
        
        if (!File::exists($logFile)) {
            return '';
        }

        return File::get($logFile);
    }

    /**
     * Download logs
     */
    public function downloadLogs(sTaskModel $task): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $logFile = $this->getLogFilePath($task);
        
        return response()->download($logFile, 'task-' . $task->id . '-' . now()->format('Y-m-d-His') . '.log');
    }
}

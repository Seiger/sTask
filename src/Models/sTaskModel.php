<?php namespace Seiger\sTask\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use EvolutionCMS\Models\User;
use Seiger\sTask\Services\TaskLogger;

/**
 * Class sTaskModel
 *
 * Model for asynchronous tasks
 *
 * @package Seiger\sTask\Models
 * @author Seiger IT Team
 * @since 1.0.0
 */
class sTaskModel extends Model
{
    protected $table = 's_tasks';
    
    protected $fillable = [
        'identifier',
        'action',
        'status',
        'message',
        'started_by',
        'meta',
        'result',
        'start_at',
        'finished_at',
        'attempts',
        'max_attempts',
        'priority',
        'progress',
    ];

    protected $casts = [
        'meta' => 'array',
        'result' => 'array',
        'start_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * Get task logger
     */
    public function logger(): TaskLogger
    {
        return app(TaskLogger::class);
    }

    /**
     * Get task logs from file
     */
    public function getLogs(int $limit = null): array
    {
        return $this->logger()->getLogs($this, $limit);
    }

    /**
     * Get last logs
     */
    public function getLastLogs(int $count = 10): array
    {
        return $this->logger()->getLastLogs($this, $count);
    }

    /**
     * Get error logs
     */
    public function getErrorLogs(): array
    {
        return $this->logger()->getErrorLogs($this);
    }

    /**
     * Clear task logs
     */
    public function clearLogs(): bool
    {
        return $this->logger()->clearLogs($this);
    }

    /**
     * Get user who started the task
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * Get worker for this task
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(sWorker::class, 'identifier', 'identifier');
    }

    /**
     * Scope for pending tasks
     */
    public function scopePending($query)
    {
        return $query->where('status', 10);
    }

    /**
     * Scope for running tasks
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 20);
    }

    /**
     * Scope for completed tasks
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 30);
    }

    /**
     * Scope for failed tasks
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 40);
    }

    /**
     * Scope for cancelled tasks
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 50);
    }

    /**
     * Scope for high priority tasks
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority', 'high');
    }

    /**
     * Scope for normal priority tasks
     */
    public function scopeNormalPriority($query)
    {
        return $query->where('priority', 'normal');
    }

    /**
     * Scope for low priority tasks
     */
    public function scopeLowPriority($query)
    {
        return $query->where('priority', 'low');
    }

    /**
     * Scope for tasks by identifier
     */
    public function scopeByIdentifier($query, string $identifier)
    {
        return $query->where('identifier', $identifier);
    }

    /**
     * Scope for tasks by action
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Mark task as running
     */
    public function markAsRunning(): void
    {
        $this->update([
            'status' => 20,
            'start_at' => now(),
            'attempts' => $this->attempts + 1,
        ]);
    }

    /**
     * Mark task as completed
     */
    public function markAsCompleted(string $message = null): void
    {
        $this->update([
            'status' => 30,
            'progress' => 100,
            'finished_at' => now(),
            'message' => $message ?? 'Task completed successfully',
        ]);
    }

    /**
     * Mark task as failed
     */
    public function markAsFailed(string $message): void
    {
        $this->update([
            'status' => 40,
            'finished_at' => now(),
            'message' => $message,
        ]);
    }

    /**
     * Mark task as cancelled
     */
    public function markAsCancelled(string $message = null): void
    {
        $this->update([
            'status' => 50,
            'finished_at' => now(),
            'message' => $message ?? 'Task cancelled',
        ]);
    }

    /**
     * Update task progress
     */
    public function updateProgress(int $progress, string $message = null): void
    {
        $this->update([
            'progress' => min(100, max(0, $progress)),
            'message' => $message,
        ]);
    }

    /**
     * Get task duration in seconds
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->start_at) {
            return null;
        }

        $end = $this->finished_at ?? now();
        return $end->diffInSeconds($this->start_at);
    }

    /**
     * Check if task can be retried
     */
    public function canRetry(): bool
    {
        return $this->status === 40 && $this->attempts < $this->max_attempts;
    }

    /**
     * Check if task is finished (completed, failed, or cancelled)
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [30, 40, 50]);
    }

    /**
     * Check if task is running
     */
    public function isRunning(): bool
    {
        return $this->status === 20;
    }

    /**
     * Check if task is pending
     */
    public function isPending(): bool
    {
        return $this->status === 10;
    }

    /**
     * Get status text
     */
    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            10 => 'pending',
            20 => 'running',
            30 => 'completed',
            40 => 'failed',
            50 => 'cancelled',
            default => 'unknown',
        };
    }
}


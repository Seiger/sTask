<?php namespace Seiger\sTask\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use EvolutionCMS\Models\User;

/**
 * Class sTaskModel
 *
 * Model for asynchronous tasks
 *
 * @package Seiger\sTask
 * @author Seiger IT Team
 * @since 1.0.0
 */
class sTaskModel extends Model
{
    // Task status constants
    public const TASK_STATUS_QUEUED = 10;      // Task is queued for execution
    public const TASK_STATUS_PREPARING = 30;   // Task is being prepared
    public const TASK_STATUS_RUNNING = 50;     // Task is currently running
    public const TASK_STATUS_FINISHED = 80;    // Task completed successfully
    public const TASK_STATUS_FAILED = 100;     // Task failed with error

    public const TASK_ACTIVE_STATUSES = [
        self::TASK_STATUS_QUEUED,
        self::TASK_STATUS_PREPARING,
        self::TASK_STATUS_RUNNING,
    ];

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
     * Return statuses that represent queued or currently processed tasks.
     *
     * @return array<int, int>
     * @since 2.1.0
     */
    public static function activeStatuses(): array
    {
        return self::TASK_ACTIVE_STATUSES;
    }

    /**
     * Normalize task metadata for stable duplicate comparison.
     *
     * @param array $meta Raw task metadata
     * @return array Normalized metadata with sorted associative keys
     * @since 2.1.0
     */
    public static function normalizeMeta(array $meta): array
    {
        foreach ($meta as $key => $value) {
            if (is_array($value)) {
                $meta[$key] = self::normalizeMeta($value);
            }
        }

        if (!array_is_list($meta)) {
            ksort($meta);
        }

        return $meta;
    }

    /**
     * Find an active task with the same worker, action, and metadata.
     *
     * @param string $identifier Worker identifier
     * @param string $action Task action
     * @param array $meta Task metadata
     * @return self|null Existing active duplicate task, if present
     * @since 2.1.0
     */
    public static function findActiveDuplicate(string $identifier, string $action, array $meta = []): ?self
    {
        $normalizedMeta = self::normalizeMeta($meta);

        return self::query()
            ->where('identifier', $identifier)
            ->where('action', $action)
            ->whereIn('status', self::activeStatuses())
            ->orderBy('created_at')
            ->get()
            ->first(function (self $task) use ($normalizedMeta) {
                return self::normalizeMeta((array)($task->meta ?? [])) === $normalizedMeta;
            });
    }

    /**
     * Scope for queued tasks
     */
    public function scopeQueued($query)
    {
        return $query->where('status', self::TASK_STATUS_QUEUED);
    }

    /**
     * Scope for preparing tasks
     */
    public function scopePreparing($query)
    {
        return $query->where('status', self::TASK_STATUS_PREPARING);
    }

    /**
     * Scope for running tasks
     */
    public function scopeRunning($query)
    {
        return $query->where('status', self::TASK_STATUS_RUNNING);
    }

    /**
     * Scope for finished tasks
     */
    public function scopeFinished($query)
    {
        return $query->where('status', self::TASK_STATUS_FINISHED);
    }

    /**
     * Scope for failed tasks
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::TASK_STATUS_FAILED);
    }

    /**
     * Scope for incomplete tasks (not finished and not failed)
     */
    public function scopeIncomplete($query)
    {
        return $query->whereNotIn('status', [
            self::TASK_STATUS_FINISHED,
            self::TASK_STATUS_FAILED
        ]);
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
            'status' => self::TASK_STATUS_RUNNING,
            'start_at' => now(),
            'attempts' => $this->attempts + 1,
        ]);
    }

    /**
     * Mark task as finished
     */
    public function markAsFinished(?string $message = null): void
    {
        $this->update([
            'status' => self::TASK_STATUS_FINISHED,
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
            'status' => self::TASK_STATUS_FAILED,
            'finished_at' => now(),
            'message' => $message,
        ]);
    }

    /**
     * Update task progress
     */
    public function updateProgress(int $progress, ?string $message = null): void
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
        return max(0, (int)$this->start_at->diffInSeconds($end));
    }

    /**
     * Check if task can be retried
     */
    public function canRetry(): bool
    {
        return $this->status === self::TASK_STATUS_FAILED && $this->attempts < $this->max_attempts;
    }

    /**
     * Check if task is finished (completed, failed, or cancelled)
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [self::TASK_STATUS_FINISHED, self::TASK_STATUS_FAILED]);
    }

    /**
     * Check if task is running
     */
    public function isRunning(): bool
    {
        return $this->status === self::TASK_STATUS_RUNNING;
    }

    /**
     * Check if task is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::TASK_STATUS_QUEUED;
    }

    /**
     * Get status text
     */
    public function getStatusTextAttribute(): string
    {
        return self::statusText($this->status);
    }

    /**
     * Convert status code to text representation.
     *
     * @param int $status Status code
     * @return string Text representation of status
     */
    public static function statusText(int $status): string
    {
        return match($status) {
            self::TASK_STATUS_QUEUED => 'pending',
            self::TASK_STATUS_PREPARING => 'preparing',
            self::TASK_STATUS_RUNNING => 'running',
            self::TASK_STATUS_FINISHED => 'completed',
            self::TASK_STATUS_FAILED => 'failed',
            default => 'unknown',
        };
    }
}

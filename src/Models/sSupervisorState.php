<?php namespace Seiger\sTask\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persisted live state and diagnostic deduplication cursor for one supervisor.
 *
 * @package Seiger\sTask
 * @since 2.2.0
 */
class sSupervisorState extends Model
{
    /** @var string */
    protected $table = 's_supervisor_states';

    /** @var array<int, string> */
    protected $fillable = [
        'worker_id',
        'identifier',
        'supervisor_key',
        'key_hash',
        'state',
        'pid',
        'heartbeat_at',
        'supervisor_started_at',
        'uptime_seconds',
        'message',
        'fingerprint',
        'last_transition_at',
        'last_seen_at',
        'repeat_count',
        'launch_requested_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'heartbeat_at' => 'datetime',
        'supervisor_started_at' => 'datetime',
        'last_transition_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'launch_requested_at' => 'datetime',
    ];

    /**
     * Get the configured worker that owns this supervisor state.
     *
     * @return BelongsTo<sWorker, $this>
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(sWorker::class, 'worker_id');
    }
}

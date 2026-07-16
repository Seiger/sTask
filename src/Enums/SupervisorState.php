<?php namespace Seiger\sTask\Enums;

/**
 * Runtime states understood by the generic sTask Supervisor service.
 *
 * @package Seiger\sTask
 * @since 2.2.0
 */
enum SupervisorState: string
{
    case Healthy = 'healthy';
    case Starting = 'starting';
    case Degraded = 'degraded';
    case Failed = 'failed';
    case Stopped = 'stopped';

    /**
     * Determine whether the supervisor is available for normal work.
     *
     * @return bool True only for a healthy supervisor
     */
    public function isHealthy(): bool
    {
        return $this === self::Healthy;
    }

    /**
     * Determine whether the supervisor is inside its non-terminal startup phase.
     *
     * @return bool True while a detached launch is still starting
     */
    public function isStarting(): bool
    {
        return $this === self::Starting;
    }

    /**
     * Determine whether sTask should request a detached launch or restart.
     *
     * @return bool True for stopped, degraded, or failed supervisors
     */
    public function requiresLaunch(): bool
    {
        return in_array($this, [self::Stopped, self::Degraded, self::Failed], true);
    }
}

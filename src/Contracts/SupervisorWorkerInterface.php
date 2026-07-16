<?php namespace Seiger\sTask\Contracts;

use Seiger\sTask\Support\SupervisorStatus;

/**
 * Generic contract for workers that expose a detached long-running process to sTask Supervisor.
 *
 * Implementations must return promptly. In particular, start and restart hooks
 * must launch detached work and must never wait for the supervised process to exit.
 *
 * @package Seiger\sTask
 * @since 2.2.0
 */
interface SupervisorWorkerInterface
{
    /**
     * Return a stable key that uniquely identifies the supervised process.
     *
     * @return string Supervised process identity, such as an account or session key
     */
    public function supervisorKey(): string;

    /**
     * Inspect the supervised process without launching or mutating it.
     *
     * @return SupervisorStatus Current process health snapshot
     */
    public function inspectSupervisor(): SupervisorStatus;

    /**
     * Request a detached first launch and return its immediate status.
     *
     * @return SupervisorStatus Immediate post-launch snapshot, normally starting
     */
    public function startSupervisor(): SupervisorStatus;

    /**
     * Request a detached restart and return its immediate status.
     *
     * @return SupervisorStatus Immediate post-restart snapshot, normally starting
     */
    public function restartSupervisor(): SupervisorStatus;

    /**
     * Return the grace period during which another launch must not be requested.
     *
     * @return int Startup grace period in seconds
     */
    public function supervisorStartupGraceSeconds(): int;
}

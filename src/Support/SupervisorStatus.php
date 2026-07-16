<?php namespace Seiger\sTask\Support;

use DateTimeInterface;
use Seiger\sTask\Enums\SupervisorState;

/**
 * Immutable health snapshot returned by a supervisor worker adapter.
 *
 * The adapter owns process-specific inspection. sTask only persists and compares
 * this transport-neutral snapshot.
 *
 * @package Seiger\sTask
 * @since 2.2.0
 */
final readonly class SupervisorStatus
{
    /**
     * Create a supervisor health snapshot.
     *
     * @param SupervisorState $state Current lifecycle state
     * @param int|null $pid Current process identifier
     * @param DateTimeInterface|null $heartbeatAt Last confirmed heartbeat
     * @param DateTimeInterface|null $startedAt Supervisor process start time
     * @param int|null $uptimeSeconds Known uptime in seconds
     * @param string $message Human-readable diagnostic message
     * @param string|null $fingerprint Stable adapter-provided diagnostic fingerprint
     */
    public function __construct(
        public SupervisorState $state,
        public ?int $pid = null,
        public ?DateTimeInterface $heartbeatAt = null,
        public ?DateTimeInterface $startedAt = null,
        public ?int $uptimeSeconds = null,
        public string $message = '',
        public ?string $fingerprint = null,
    ) {
    }

    /**
     * Resolve a stable fingerprint used to deduplicate repeated diagnostics.
     *
     * @return string SHA-256 diagnostic fingerprint
     */
    public function diagnosticFingerprint(): string
    {
        $fingerprint = trim((string)$this->fingerprint);

        return $fingerprint !== ''
            ? hash('sha256', $fingerprint)
            : hash('sha256', $this->state->value . "\n" . trim($this->message));
    }
}

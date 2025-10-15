<?php namespace Seiger\sTask\Exceptions;

use Exception;

/**
 * WorkerNotFoundException - Exception thrown when a worker is not found
 *
 * This exception is thrown when attempting to resolve a worker that doesn't
 * exist in the database or is inactive. It provides detailed information
 * about the missing worker for debugging and error handling.
 *
 * @package Seiger\sTask\Exceptions
 * @author Seiger IT Team
 * @since 1.0.0
 */
class WorkerNotFoundException extends Exception
{
    /**
     * The worker identifier that was not found
     *
     * @var string
     */
    public readonly string $identifier;

    /**
     * Create a new worker not found exception
     *
     * @param string $identifier The worker identifier that was not found
     * @param string $message Optional custom error message
     * @param int $code Optional error code
     * @param \Throwable|null $previous Optional previous exception
     */
    public function __construct(
        string $identifier,
        string $message = '',
        int $code = 404,
        ?\Throwable $previous = null
    ) {
        $this->identifier = $identifier;
        
        if (empty($message)) {
            $message = "Worker not found or inactive: {$identifier}";
        }
        
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the exception context for logging
     *
     * @return array<string, mixed> Exception context
     */
    public function getContext(): array
    {
        return [
            'identifier' => $this->identifier,
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}

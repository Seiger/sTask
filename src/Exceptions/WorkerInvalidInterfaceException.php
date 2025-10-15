<?php namespace Seiger\sTask\Exceptions;

use Exception;

/**
 * WorkerInvalidInterfaceException - Exception thrown when a worker doesn't implement TaskInterface
 *
 * This exception is thrown when attempting to use a worker class that
 * doesn't implement the required TaskInterface. It provides detailed
 * information about the invalid class for debugging and error handling.
 *
 * @package Seiger\sTask\Exceptions
 * @author Seiger IT Team
 * @since 1.0.0
 */
class WorkerInvalidInterfaceException extends Exception
{
    /**
     * The class name that doesn't implement TaskInterface
     *
     * @var string
     */
    public readonly string $className;

    /**
     * Create a new worker invalid interface exception
     *
     * @param string $className The class name that doesn't implement TaskInterface
     * @param string $message Optional custom error message
     * @param int $code Optional error code
     * @param \Throwable|null $previous Optional previous exception
     */
    public function __construct(
        string $className,
        string $message = '',
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        $this->className = $className;
        
        if (empty($message)) {
            $message = "Worker class must implement TaskInterface: {$className}";
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
            'className' => $this->className,
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}

<?php namespace Seiger\sTask\Exceptions;

use Exception;

/**
 * WorkerClassNotFoundException - Exception thrown when a worker class is not found
 *
 * This exception is thrown when attempting to instantiate a worker class
 * that doesn't exist or cannot be loaded. It provides detailed information
 * about the missing class for debugging and error handling.
 *
 * @package Seiger\sTask\Exceptions
 * @author Seiger IT Team
 * @since 1.0.0
 */
class WorkerClassNotFoundException extends Exception
{
    /**
     * The class name that was not found
     *
     * @var string
     */
    public readonly string $className;

    /**
     * Create a new worker class not found exception
     *
     * @param string $className The class name that was not found
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
            $message = "Worker class not found or cannot be instantiated: {$className}";
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

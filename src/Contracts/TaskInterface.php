<?php namespace Seiger\sTask\Contracts;

/**
 * Interface TaskInterface
 *
 * Interface for task workers
 *
 * @package Seiger\sTask\Contracts
 * @author Seiger IT Team
 * @since 1.0.0
 */
interface TaskInterface
{
    /**
     * Execute the task with given data
     *
     * @param array $data Task data and parameters
     * @return bool True if successful, false otherwise
     */
    public function execute(array $data): bool;

    /**
     * Get task type identifier
     *
     * @return string Unique task type identifier
     */
    public static function getType(): string;

    /**
     * Get task description for UI
     *
     * @return string Human-readable task description
     */
    public static function getDescription(): string;

    /**
     * Validate task data before execution
     *
     * @param array $data Task data to validate
     * @return bool True if valid, false otherwise
     */
    public function validate(array $data): bool;
}

<?php namespace Seiger\sTask\Workers;

use Illuminate\Support\Facades\Log;
use Seiger\sTask\Models\sTaskModel;

/**
 * ArtisanWorker - Worker for executing Artisan commands
 *
 * This class implements worker functionality for executing Laravel Artisan commands
 * in the Evolution CMS environment. It extends BaseWorker and provides comprehensive
 * functionality for running artisan commands with progress tracking.
 *
 * Features:
 * - Execute any artisan command
 * - Real-time progress tracking
 * - Output logging and error handling
 * - Memory and timeout management
 * - Support for various artisan options
 * - Multiple execution methods (proc_open, popen, shell_exec, exec)
 *
 * Artisan Execution Process:
 * 1. Validates artisan file exists
 * 2. Executes artisan command with specified options
 * 3. Tracks command output in real-time
 * 4. Logs all output for debugging
 * 5. Handles errors and provides detailed feedback
 *
 * @package Seiger\sTask\Workers
 * @author Seiger IT Team
 * @since 1.0.3
 */
class ArtisanWorker extends BaseWorker
{
    /**
     * Get the unique identifier for this worker.
     *
     * @return string The worker identifier
     */
    public function identifier(): string
    {
        return 'artisan';
    }

    /**
     * Get the scope/module this worker belongs to.
     *
     * @return string The module scope
     */
    public function scope(): string
    {
        return 'sTask';
    }

    /**
     * Get the icon for this worker.
     *
     * @return string The worker icon
     */
    public function icon(): string
    {
        return '<i class="fas fa-terminal"></i>';
    }

    /**
     * Get the title for this worker.
     *
     * @return string The worker title
     */
    public function title(): string
    {
        return __('sTask::global.artisan');
    }

    /**
     * Get the description for this worker.
     *
     * @return string The worker description
     */
    public function description(): string
    {
        return __('sTask::global.artisan_desc');
    }

    /**
     * Render custom widget for Artisan worker.
     *
     * @return string The rendered widget HTML
     */
    public function renderWidget(): string
    {
        return view('sTask::widgets.artisanWorkerWidget', [
            'identifier' => $this->identifier(),
            'description' => $this->description(),
        ])->render();
    }

    /**
     * Get dangerous commands from config
     * 
     * @return array
     */
    protected function getDangerousCommands(): array
    {
        return config('artisan_security.dangerous_commands', [
            'migrate:fresh',
            'migrate:reset',
            'db:wipe',
        ]);
    }
    
    /**
     * Get commands that require confirmation
     * 
     * @return array
     */
    protected function getConfirmationRequired(): array
    {
        return config('artisan_security.confirmation_required', [
            'migrate',
            'migrate:refresh',
            'migrate:rollback',
            'db:seed',
            'cache:clear-full',
        ]);
    }

    /**
     * Execute the artisan command action.
     *
     * Runs artisan command with proper error handling and progress tracking.
     * Includes security checks for dangerous commands.
     *
     * @param sTaskModel $task The task model for progress tracking
     * @param array $opt Action parameters:
     *                   - command: Artisan command to execute (e.g., 'cache:clear', 'migrate')
     *                   - arguments: Additional command arguments (optional)
     *                   - confirm: Set to true to bypass confirmation for dangerous commands
     *                   - timeout: Command timeout in seconds (default: 300)
     * @return void
     * @throws \RuntimeException If artisan is not found or command fails
     * @throws \Throwable For any other errors during processing
     */
    public function taskMake(sTaskModel $task, array $opt = []): void
    {
        @ini_set('auto_detect_line_endings', '1');
        @ini_set('output_buffering', '0');
        @set_time_limit(0);

        try {
            // Preparing
            $task->update([
                'status' => sTaskModel::TASK_STATUS_PREPARING,
                'message' => '_' . __('sTask::global.task_preparing') . '..._',
            ]);

            $this->pushProgress($task, [
                'message' => __('sTask::global.task_preparing') . '...',
            ]);

            // Get command from options (can be empty to show list of commands)
            $command = trim($opt['command'] ?? '');
            
            // Get additional arguments if provided
            $arguments = trim($opt['arguments'] ?? '');
            
            // Security check: validate command
            if (!empty($command)) {
                $this->validateCommand($command, $opt);
            }
            
            // Log command execution for audit (if enabled in config)
            if (config('artisan_security.log_executions', true)) {
                Log::info('Artisan command execution', [
                    'command' => $command ?: '(list)',
                    'arguments' => $arguments,
                    'user_id' => $task->started_by,
                    'task_id' => $task->id,
                ]);
            }

            // Set working directory to core (where artisan is located)
            $projectRoot = base_path(); // In Evolution CMS this is the core/ directory

            $this->pushProgress($task, [
                'progress' => 2,
                'message' => __('sTask::global.checking_working_directory') . ': ' . basename($projectRoot),
            ]);

            // Verify artisan exists in working directory
            $artisanPath = $projectRoot . '/artisan';
            if (!file_exists($artisanPath)) {
                throw new \RuntimeException('artisan file not found in: ' . $projectRoot);
            }

            $this->pushProgress($task, [
                'progress' => 5,
                'message' => __('sTask::global.found_artisan') . ': ' . basename($artisanPath),
            ]);

            // Prepare artisan command
            $phpBinary = PHP_BINARY;
            $fullCommand = $phpBinary . ' ' . escapeshellarg($artisanPath);
            
            if (!empty($command)) {
                $fullCommand .= ' ' . $command;
            }
            
            if (!empty($arguments)) {
                $fullCommand .= ' ' . $arguments;
            }

            $fullCommand .= ' 2>&1';

            $commandDisplay = !empty($command) ? $command : '(список команд)';
            $this->pushProgress($task, [
                'progress' => 8,
                'message' => __('sTask::global.preparing_command') . ': artisan ' . $commandDisplay,
            ]);

            $task->update([
                'status' => sTaskModel::TASK_STATUS_RUNNING,
                'message' => '_' . __('sTask::global.executing_artisan') . (!empty($command) ? ': ' . $command : '') . '_',
            ]);

            $this->pushProgress($task, ['progress' => 10]);

            // Execute artisan command
            $startTime = microtime(true);
            $output = [];
            $progress = 10;

            // Change to project directory (core/) FIRST
            $oldDir = getcwd();

            if (!chdir($projectRoot)) {
                throw new \RuntimeException('Failed to change directory to: ' . $projectRoot);
            }

            $this->pushProgress($task, [
                'progress' => 12,
                'message' => __('sTask::global.working_directory') . ': ' . getcwd(),
            ]);

            // Execute command and capture output line by line
            $descriptors = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w'],  // stderr
            ];

            $pipes = [];
            $returnVar = 1;

            // Try proc_open first (most reliable for real-time output)
            if (function_exists('proc_open')) {
                $this->pushProgress($task, [
                    'progress' => 15,
                    'message' => __('sTask::global.executing_via_proc_open') . '...',
                ]);

                $process = proc_open($fullCommand, $descriptors, $pipes, $projectRoot);

                if (is_resource($process)) {
                    // Close stdin
                    fclose($pipes[0]);

                    // Set non-blocking mode for real-time output
                    stream_set_blocking($pipes[1], false);
                    stream_set_blocking($pipes[2], false);

                    $buffer = '';
                    
                    // Read output line by line
                    while (!feof($pipes[1])) {
                        $line = fgets($pipes[1]);
                        if ($line === false) {
                            usleep(10000); // 10ms sleep to prevent CPU spinning
                            continue;
                        }

                        $cleanData = trim($line);
                        if (empty($cleanData)) continue;

                        $output[] = $cleanData;
                        $progress = $this->processOutputLine($cleanData, $progress, $task);
                    }

                    // Read any error output
                    $errorOutput = stream_get_contents($pipes[2]);
                    if (!empty(trim($errorOutput))) {
                        $errorLines = explode("\n", trim($errorOutput));
                        foreach ($errorLines as $errorLine) {
                            $cleanError = trim($errorLine);
                            if (!empty($cleanError)) {
                                $output[] = 'ERROR: ' . $cleanError;
                                $this->pushProgress($task, [
                                    'message' => 'ERROR: ' . $cleanError,
                                ]);
                            }
                        }
                    }

                    fclose($pipes[1]);
                    fclose($pipes[2]);

                    $returnVar = proc_close($process);
                } else {
                    throw new \RuntimeException('Failed to execute artisan using proc_open');
                }
            } elseif (function_exists('popen')) {
                // Fallback to popen
                $this->pushProgress($task, [
                    'progress' => 15,
                    'message' => __('sTask::global.executing_via_popen') . '...',
                ]);

                $handle = popen($fullCommand, 'r');

                if ($handle) {
                    while (!feof($handle)) {
                        $line = fgets($handle);
                        if ($line === false) break;

                        $cleanData = trim($line);
                        if (empty($cleanData)) continue;

                        $output[] = $cleanData;
                        $progress = $this->processOutputLine($cleanData, $progress, $task);
                    }

                    $returnVar = pclose($handle);
                } else {
                    throw new \RuntimeException('Failed to execute artisan command using popen');
                }
            } elseif (function_exists('shell_exec')) {
                // Try shell_exec
                $this->pushProgress($task, [
                    'progress' => 15,
                    'message' => __('sTask::global.executing_via_shell_exec') . '...',
                ]);

                $fullOutput = shell_exec($fullCommand);

                if ($fullOutput === null) {
                    throw new \RuntimeException('Failed to execute artisan command using shell_exec');
                }

                $outputLines = explode("\n", $fullOutput);
                $totalLines = count($outputLines);
                $returnVar = 0; // shell_exec doesn't return exit code

                foreach ($outputLines as $index => $line) {
                    $cleanData = trim($line);
                    if (empty($cleanData)) continue;

                    $output[] = $cleanData;
                    $progress = $this->processOutputLine($cleanData, $progress, $task);

                    // Update based on line position
                    $lineProgress = (int)(($index + 1) / $totalLines * 80) + 15; // 15-95%
                    $progress = max($progress, $lineProgress);
                }

                // Check for errors in output
                foreach ($output as $line) {
                    if (stripos($line, 'error') !== false || stripos($line, 'exception') !== false) {
                        $returnVar = 1;
                        break;
                    }
                }
            } elseif (function_exists('exec')) {
                // Fallback to exec
                $this->pushProgress($task, [
                    'progress' => 15,
                    'message' => __('sTask::global.executing_via_exec') . '...',
                ]);

                $outputLines = [];
                $returnVar = 0;

                exec($fullCommand, $outputLines, $returnVar);

                $totalLines = count($outputLines);
                foreach ($outputLines as $index => $line) {
                    $cleanData = trim($line);
                    if (empty($cleanData)) continue;

                    $output[] = $cleanData;
                    $progress = $this->processOutputLine($cleanData, $progress, $task);

                    // Update based on line position
                    $lineProgress = (int)(($index + 1) / $totalLines * 80) + 15; // 15-95%
                    $progress = max($progress, $lineProgress);
                }
            } else {
                throw new \RuntimeException('No suitable PHP function available for executing commands. Please enable proc_open, popen, shell_exec, or exec in php.ini (disable_functions).');
            }

            // Return to original directory
            chdir($oldDir);

            // Check if command was successful
            if ($returnVar !== 0) {
                $errorOutput = implode("\n", array_slice($output, -10)); // Last 10 lines

                // Show error in UI
                $this->pushProgress($task, [
                    'progress' => 0,
                    'message' => __('sTask::global.artisan_command_failed') . ' (exit code: ' . $returnVar . ')',
                ]);

                Log::error('Artisan command failed', [
                    'task_id' => $task->id,
                    'command' => $command,
                    'exit_code' => $returnVar,
                    'error' => $errorOutput,
                ]);

                throw new \RuntimeException('Artisan command failed (exit code: ' . $returnVar . '): ' . $errorOutput);
            }

            $totalTime = microtime(true) - $startTime;
            $outputText = implode("\n", $output);

            // Show finalizing message
            $this->pushProgress($task, [
                'progress' => 98,
                'message' => '_' . __('sTask::global.finalizing_artisan') . '..._',
            ]);

            // Done
            $commandInfo = !empty($command) ? ': ' . $command : '';
            $finalMessage = '**' . __('sTask::global.artisan_executed_successfully') . $commandInfo . ' (' . round($totalTime, 2) . ' s)**';

            $task->update([
                'status' => sTaskModel::TASK_STATUS_FINISHED,
                'progress' => 100,
                'message' => $finalMessage,
                'result' => $outputText,
                'finished_at' => now(),
            ]);

            // Write final progress with completed status
            $this->pushProgress($task, [
                'status' => $task->status_text,
                'progress' => 100,
                'message' => $finalMessage,
            ]);
        } catch (\Throwable $e) {
            $where = basename($e->getFile()) . ':' . $e->getLine();
            $message = 'Failed @ ' . $where . ' — ' . $e->getMessage();

            // Detailed error logging
            Log::error('=== Artisan Command Failed ===', [
                'task_id' => $task->id,
                'command' => $opt['command'] ?? 'unknown',
                'error_location' => $where,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $task->update([
                'status' => sTaskModel::TASK_STATUS_FAILED,
                'message' => $message,
                'finished_at' => now(),
            ]);

            $this->pushProgress($task);
            throw $e;
        }
    }

    /**
     * Process output line and update progress.
     *
     * @param string $line Output line from artisan
     * @param int $currentProgress Current progress value
     * @param sTaskModel $task Task model
     * @return int Updated progress value
     */
    protected function processOutputLine(string $line, int $currentProgress, sTaskModel $task): int
    {
        $newProgress = $currentProgress;
        $cleanLine = trim($line);

        // Skip empty lines and single characters
        if (empty($cleanLine) || strlen($cleanLine) < 2) {
            return $newProgress;
        }

        // Update progress based on key phrases
        if (stripos($line, 'migrating') !== false) {
            $newProgress = max($currentProgress, 30);
        } elseif (stripos($line, 'migrated') !== false) {
            $newProgress = max($currentProgress, 60);
        } elseif (stripos($line, 'seeding') !== false) {
            $newProgress = max($currentProgress, 40);
        } elseif (stripos($line, 'seeded') !== false) {
            $newProgress = max($currentProgress, 70);
        } elseif (stripos($line, 'caching') !== false || stripos($line, 'cache') !== false) {
            $newProgress = max($currentProgress, 50);
        } elseif (stripos($line, 'clearing') !== false || stripos($line, 'cleared') !== false) {
            $newProgress = max($currentProgress, 60);
        } elseif (stripos($line, 'optimizing') !== false || stripos($line, 'optimized') !== false) {
            $newProgress = max($currentProgress, 70);
        } elseif (stripos($line, 'done') !== false || stripos($line, 'completed') !== false) {
            $newProgress = max($currentProgress, 85);
        } elseif (stripos($line, 'processing') !== false) {
            $newProgress = max($currentProgress, 45);
        }

        // Add raw line to UI (as is, like in console)
        $this->pushProgress($task, [
            'progress' => min($newProgress, 95),
            'message' => $cleanLine,
        ]);

        return $newProgress;
    }
    
    /**
     * Validate command for security
     *
     * @param string $command Command to validate
     * @param array $opt Options including confirmation flag
     * @return void
     * @throws \RuntimeException If command is not allowed
     */
    protected function validateCommand(string $command, array $opt = []): void
    {
        // Skip validation if security is disabled (not recommended)
        if (!config('artisan_security.enabled', true)) {
            return;
        }
        
        // Prevent command injection through arguments
        if (preg_match('/[;&|`$<>]/', $command)) {
            throw new \RuntimeException(
                __('sTask::global.command_contains_forbidden_characters')
            );
        }
        
        // Check blacklist
        $blacklist = config('artisan_security.blacklist', []);
        foreach ($blacklist as $pattern) {
            if ($this->matchesPattern($command, $pattern)) {
                throw new \RuntimeException(
                    __('sTask::global.command_forbidden_in_production', ['command' => $command])
                );
            }
        }
        
        // Check if command is in dangerous list
        if (in_array($command, $this->getDangerousCommands())) {
            // Check environment - forbid in production
            if (config('app.env') === 'production') {
                throw new \RuntimeException(
                    __('sTask::global.command_forbidden_in_production', ['command' => $command])
                );
            }
            
            // Require explicit confirmation
            if (empty($opt['confirm'])) {
                throw new \RuntimeException(
                    __('sTask::global.command_requires_confirmation', ['command' => $command])
                );
            }
        }
        
        // Check if command requires confirmation
        if (in_array($command, $this->getConfirmationRequired())) {
            if (empty($opt['confirm'])) {
                Log::warning('Command executed without confirmation', [
                    'command' => $command,
                    'user_id' => request()->user()->id ?? 'unknown',
                ]);
            }
        }
        
        // Check whitelist (if configured)
        $whitelist = config('artisan_security.whitelist', []);
        if (!empty($whitelist)) {
            $allowed = false;
            foreach ($whitelist as $pattern) {
                if ($this->matchesPattern($command, $pattern)) {
                    $allowed = true;
                    break;
                }
            }
            
            if (!$allowed) {
                throw new \RuntimeException(
                    __('sTask::global.command_not_in_whitelist', ['command' => $command])
                );
            }
        }
    }
    
    /**
     * Check if command matches pattern (supports wildcards)
     *
     * @param string $command
     * @param string $pattern
     * @return bool
     */
    protected function matchesPattern(string $command, string $pattern): bool
    {
        $pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
        return (bool)preg_match('/^' . $pattern . '$/', $command);
    }
}


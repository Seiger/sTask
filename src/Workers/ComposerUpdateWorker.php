<?php namespace Seiger\sTask\Workers;

use Illuminate\Support\Facades\Log;
use Seiger\sTask\Models\sTaskModel;

/**
 * ComposerUpdateWorker - Worker for updating Composer dependencies
 *
 * This class implements worker functionality for updating Composer dependencies
 * in the Evolution CMS environment. It extends BaseWorker and provides comprehensive
 * functionality for running composer update with progress tracking.
 *
 * Features:
 * - Execute composer update command
 * - Real-time progress tracking
 * - Output logging and error handling
 * - Memory and timeout management
 * - Support for various composer options
 * - Works without proc_open (uses exec instead)
 *
 * Composer Update Process:
 * 1. Validates composer installation and composer.json
 * 2. Executes composer update with specified options
 * 3. Tracks command output in real-time
 * 4. Logs all output for debugging
 * 5. Handles errors and provides detailed feedback
 *
 * @package Seiger\sTask\Workers
 * @author Seiger IT Team
 * @since 1.0.2
 */
class ComposerUpdateWorker extends BaseWorker
{
    /**
     * Get the unique identifier for this worker.
     *
     * @return string The worker identifier
     */
    public function identifier(): string
    {
        return 'composer_update';
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
        return '<i class="fas fa-sync-alt"></i>';
    }

    /**
     * Get the title for this worker.
     *
     * @return string The worker title
     */
    public function title(): string
    {
        return __('sTask::global.composer_update');
    }

    /**
     * Get the description for this worker.
     *
     * @return string The worker description
     */
    public function description(): string
    {
        return __('sTask::global.composer_update_desc');
    }

    /**
     * Render custom widget for Composer Update worker.
     *
     * @return string The rendered widget HTML
     */
    public function renderWidget(): string
    {
        return view('sTask::widgets.composerUpdateWorkerWidget', [
            'identifier' => $this->identifier(),
            'description' => $this->description(),
        ])->render();
    }

    /**
     * Execute the composer update action.
     *
     * Runs composer update command with proper error handling and progress tracking.
     *
     * @param sTaskModel $task The task model for progress tracking
     * @param array $opt Action parameters:
     *                   - timeout: Command timeout in seconds (default: 600)
     *                   - no_dev: Skip dev dependencies (default: false)
     *                   - optimize: Optimize autoloader (default: true)
     *                   - prefer_stable: Prefer stable versions (default: true)
     *                   - with_dependencies: Update with dependencies (default: true)
     * @return void
     * @throws \RuntimeException If composer is not found or command fails
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

            // Set working directory to core (where composer.json is located)
            $projectRoot = base_path(); // In Evolution CMS this is the core/ directory

            $this->pushProgress($task, [
                'progress' => 2,
                'message' => __('sTask::global.checking_working_directory') . ': ' . basename($projectRoot),
            ]);

            // Get composer path
            $composerPath = $this->findComposerExecutable();

            $this->pushProgress($task, [
                'progress' => 4,
                'message' => __('sTask::global.found_composer') . ': ' . basename($composerPath),
            ]);

            // Verify composer.json exists in working directory
            if (!file_exists($projectRoot . '/composer.json')) {
                throw new \RuntimeException('composer.json not found in: ' . $projectRoot . '. Make sure base_path() points to core/ directory.');
            }

            $this->pushProgress($task, [
                'progress' => 6,
                'message' => __('sTask::global.found_composer_json_in') . ': ' . basename($projectRoot),
            ]);

            // Prepare composer update command
            $command = [
                $composerPath,
                'update',
                '--no-interaction',
                '--verbose',
                '--no-scripts', // Skip scripts to avoid proc_open issues
            ];

            // Add optional parameters
            if (!empty($opt['no_dev'])) {
                $command[] = '--no-dev';
            }

            if (!empty($opt['optimize']) || !isset($opt['optimize'])) {
                $command[] = '--optimize-autoloader';
            }

            if (!empty($opt['prefer_stable']) || !isset($opt['prefer_stable'])) {
                $command[] = '--prefer-stable';
            }

            if (!empty($opt['with_dependencies']) || !isset($opt['with_dependencies'])) {
                $command[] = '--with-dependencies';
            }

            $this->pushProgress($task, [
                'progress' => 8,
                'message' => __('sTask::global.preparing_command_options') . '...',
            ]);

            $task->update([
                'status' => sTaskModel::TASK_STATUS_RUNNING,
                'message' => '_' . __('sTask::global.composer_updating') . '..._',
            ]);

            $this->pushProgress($task, ['progress' => 10]);

            // Execute composer update
            $startTime = microtime(true);
            $output = [];
            $progress = 10;

            // Change to project directory (core/) FIRST
            $oldDir = getcwd();

            if (!chdir($projectRoot)) {
                throw new \RuntimeException('Failed to change directory to: ' . $projectRoot);
            }

            // Set up environment variables in PHP process
            $composerEnv = $this->setupComposerEnvironment();
            $composerHome = $composerEnv['home'];
            $composerCacheDir = $composerEnv['cache'];

            $this->pushProgress($task, [
                'progress' => 12,
                'message' => __('sTask::global.working_directory') . ': ' . getcwd(),
            ]);

            // Build command strings
            // For proc_open we use $env array, for others we need explicit env prefix
            $commandString = implode(' ', $command) . ' 2>&1';
            $envPrefix = sprintf(
                'COMPOSER_HOME=%s COMPOSER_CACHE_DIR=%s COMPOSER_NO_INTERACTION=1 COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_DISABLE_XDEBUG_WARN=1 HOME=%s ',
                escapeshellarg($composerHome),
                escapeshellarg($composerCacheDir),
                escapeshellarg($composerHome)
            );

            // Execute command and capture output line by line
            $descriptors = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w'],  // stderr
            ];

            $pipes = [];
            $process = null;
            $stderrOutput = '';

            // Prepare environment variables array for proc_open
            // Start with current environment to preserve PATH and other system vars
            // Composer env vars already set via setupComposerEnvironment()
            $env = getenv() ?: $_ENV ?: $_SERVER;

            // Try proc_open first (most reliable for environment variables)
            if (function_exists('proc_open')) {
                $this->pushProgress($task, [
                    'progress' => 15,
                    'message' => __('sTask::global.executing_via_proc_open') . '...',
                ]);

                // Pass full environment array (getenv() + our overrides)
                $process = proc_open($commandString, $descriptors, $pipes, $projectRoot, $env);

                if (is_resource($process)) {
                    // Close stdin
                    fclose($pipes[0]);

                    // Read output line by line
                    while (!feof($pipes[1])) {
                        $line = fgets($pipes[1]);
                        if ($line === false) break;

                        $cleanData = trim($line);
                        if (empty($cleanData)) continue;

                        $output[] = $cleanData;
                        $progress = $this->processOutputLine($cleanData, $progress, $task);
                    }

                    // Read any error output
                    $stderrOutput = stream_get_contents($pipes[2]) ?: '';

                    fclose($pipes[1]);
                    fclose($pipes[2]);

                    $returnVar = proc_close($process);
                } else {
                    throw new \RuntimeException('Failed to execute composer using proc_open');
                }
            } elseif (function_exists('popen')) {
                // Fallback to popen with explicit env prefix (in case putenv doesn't work)
                $handle = popen($envPrefix . $commandString, 'r');

                if ($handle) {
                    while (!feof($handle)) {
                        $line = fgets($handle);
                        if ($line === false) break;

                        $cleanData = trim($line);
                        if (empty($cleanData)) continue;

                        $output[] = $cleanData;

                        // Process line and update progress
                        $progress = $this->processOutputLine($cleanData, $progress, $task);
                    }

                    $returnVar = pclose($handle);
                } else {
                    throw new \RuntimeException('Failed to execute composer command using popen');
                }
            } elseif (function_exists('shell_exec')) {
                // Try shell_exec with explicit env prefix
                $fullOutput = shell_exec($envPrefix . $commandString);

                if ($fullOutput === null) {
                    throw new \RuntimeException('Failed to execute composer command using shell_exec');
                }

                $outputLines = explode("\n", $fullOutput);
                $totalLines = count($outputLines);
                $returnVar = 0; // shell_exec doesn't return exit code

                foreach ($outputLines as $index => $line) {
                    $cleanData = trim($line);
                    if (empty($cleanData)) continue;

                    $output[] = $cleanData;

                    // Process line and update progress
                    $progress = $this->processOutputLine($cleanData, $progress, $task);

                    // Also update based on line position
                    $lineProgress = (int)(($index + 1) / $totalLines * 85) + 10; // 10-95%
                    $progress = max($progress, $lineProgress);
                }

                // Check for errors in output
                foreach ($output as $line) {
                    if (stripos($line, 'error') !== false || stripos($line, 'failed') !== false) {
                        $returnVar = 1;
                        break;
                    }
                }
            } elseif (function_exists('passthru')) {
                // Try passthru with output buffering
                ob_start();
                passthru($commandString, $returnVar);
                $fullOutput = ob_get_clean();

                $outputLines = explode("\n", $fullOutput);
                $totalLines = count($outputLines);

                foreach ($outputLines as $index => $line) {
                    $cleanData = trim($line);
                    if (empty($cleanData)) continue;

                    $output[] = $cleanData;

                    // Process line and update progress
                    $progress = $this->processOutputLine($cleanData, $progress, $task);

                    // Also update based on line position
                    $lineProgress = (int)(($index + 1) / $totalLines * 85) + 10; // 10-95%
                    $progress = max($progress, $lineProgress);
                }
            } elseif (function_exists('system')) {
                // Try system with output buffering
                ob_start();
                $lastLine = system($commandString, $returnVar);
                $fullOutput = ob_get_clean();

                $outputLines = explode("\n", $fullOutput);
                if ($lastLine) {
                    $outputLines[] = $lastLine;
                }

                $totalLines = count($outputLines);

                foreach ($outputLines as $index => $line) {
                    $cleanData = trim($line);
                    if (empty($cleanData)) continue;

                    $output[] = $cleanData;

                    // Process line and update progress
                    $progress = $this->processOutputLine($cleanData, $progress, $task);

                    // Also update based on line position
                    $lineProgress = (int)(($index + 1) / $totalLines * 85) + 10; // 10-95%
                    $progress = max($progress, $lineProgress);
                }
            } elseif (function_exists('exec')) {
                // Fallback to exec with explicit env prefix
                $outputLines = [];
                $returnVar = 0;

                exec($envPrefix . $commandString, $outputLines, $returnVar);

                $totalLines = count($outputLines);
                foreach ($outputLines as $index => $line) {
                    $cleanData = trim($line);
                    if (empty($cleanData)) continue;

                    $output[] = $cleanData;

                    // Process line and update progress
                    $progress = $this->processOutputLine($cleanData, $progress, $task);

                    // Also update based on line position
                    $lineProgress = (int)(($index + 1) / $totalLines * 85) + 10; // 10-95%
                    $progress = max($progress, $lineProgress);
                }
            } else {
                // This should never happen because we checked above
                throw new \RuntimeException('No suitable PHP function available for executing commands. Please enable popen, shell_exec, exec, passthru, or system in php.ini (disable_functions).');
            }

            // Return to original directory
            chdir($oldDir);

            // Check if command was successful
            if ($returnVar !== 0) {
                $combinedError = trim($stderrOutput);
                if ($combinedError === '') {
                    $combinedError = implode("\n", array_slice($output, -10));
                }

                // Show error in UI
                $this->pushProgress($task, [
                    'progress' => 0,
                    'message' => __('sTask::global.composer_update_failed') . ': ' . $returnVar . ')',
                ]);

                Log::error('Composer update failed', [
                    'task_id' => $task->id,
                    'exit_code' => $returnVar,
                    'error' => $combinedError,
                ]);

                throw new \RuntimeException('Composer update failed (exit code: ' . $returnVar . '): ' . $combinedError);
            }

            $totalTime = microtime(true) - $startTime;
            $outputText = implode("\n", $output);

            // Show finalizing message
            $this->pushProgress($task, [
                'progress' => 98,
                'message' => '_' . __('sTask::global.finalizing_composer_update') . '..._',
            ]);

            // Parse output for summary information
            $installCount = 0;
            $updateCount = 0;
            $removeCount = 0;

            foreach ($output as $line) {
                if (preg_match('/Package operations:\s*(\d+)\s*installs?,\s*(\d+)\s*updates?,\s*(\d+)\s*removals?/', $line, $matches)) {
                    $installCount = (int)$matches[1];
                    $updateCount = (int)$matches[2];
                    $removeCount = (int)$matches[3];
                }
            }

            $summaryParts = [];
            if ($installCount > 0) $summaryParts[] = "$installCount installed";
            if ($updateCount > 0) $summaryParts[] = "$updateCount updated";
            if ($removeCount > 0) $summaryParts[] = "$removeCount removed";
            $summary = !empty($summaryParts) ? ' (' . implode(', ', $summaryParts) . ')' : '';

            // Done
            $finalMessage = '**' . __('sTask::global.composer_updated_successfully') . $summary . ' (' . round($totalTime, 2) . ' s)**';

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
            Log::error('=== Composer Update Failed ===', [
                'task_id' => $task->id,
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
     * @param string $line Output line from composer
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

        // Update progress based on key phrases (but don't change the message)
        if (strpos($line, 'Loading composer repositories') !== false) {
            $newProgress = 20;
        } elseif (strpos($line, 'Updating dependencies') !== false) {
            $newProgress = 30;
        } elseif (strpos($line, 'Writing lock file') !== false) {
            $newProgress = 35;
        } elseif (strpos($line, 'Lock file operations:') !== false) {
            $newProgress = 40;
        } elseif (strpos($line, 'Package operations:') !== false) {
            $newProgress = 50;
        } elseif (strpos($line, 'Downloading') !== false) {
            $newProgress = max($currentProgress, 60);
        } elseif (strpos($line, 'Installing') !== false) {
            $newProgress = max($currentProgress, 65);
        } elseif (strpos($line, 'Upgrading') !== false || strpos($line, 'Removing') !== false) {
            $newProgress = max($currentProgress, 70);
        } elseif (strpos($line, 'Extracting archive') !== false) {
            $newProgress = max($currentProgress, 75);
        } elseif (strpos($line, 'Generating') !== false && strpos($line, 'autoload') !== false) {
            $newProgress = 80;
        } elseif (strpos($line, 'Running composer scripts') !== false) {
            $newProgress = 90;
        }

        // Add raw line to UI (as is, like in console)
        $this->pushProgress($task, [
            'progress' => min($newProgress, 95),
            'message' => $cleanLine,
        ]);

        return $newProgress;
    }

    /**
     * Run Composer directly as PHP code without exec functions.
     *
     * This method executes composer by loading it as PHP code directly,
     * bypassing the need for shell execution functions.
     *
     * @param sTaskModel $task Task model
     * @param string $composerPath Path to composer
     * @param array $command Command parts
     * @param string $projectRoot Working directory
     * @param array $opt Options
     * @return void
     * @throws \Exception If direct execution fails
     */
    protected function runComposerDirectly(sTaskModel $task, string $composerPath, array $command, string $projectRoot, array $opt): void
    {
        $startTime = microtime(true);

        // Change to project directory
        $oldDir = getcwd();
        chdir($projectRoot);

        try {
            $this->pushProgress($task, [
                'progress' => 20,
                'message' => '_' . __('sTask::global.preparing_direct_execution') . '..._',
            ]);

            // Composer API has issues in this context, use direct script execution
            // This method loads composer.phar directly and executes it
            $this->runComposerViaScript($task, $composerPath, $command, $projectRoot, $opt);
            chdir($oldDir);
        } catch (\Throwable $e) {
            chdir($oldDir);
            throw $e;
        }
    }

    /**
     * Run Composer using its API directly (if available).
     */
    protected function runComposerViaAPI(sTaskModel $task, array $command, string $projectRoot, array $opt): void
    {
        $startTime = microtime(true);

        $this->pushProgress($task, [
            'progress' => 30,
            'message' => '_' . __('sTask::global.using_composer_api') . '..._',
        ]);

        // Build command string for StringInput (more reliable than ArrayInput)
        $commandParts = ['update', '--no-interaction', '-v', '--no-scripts'];

        if (!empty($opt['optimize']) || !isset($opt['optimize'])) {
            $commandParts[] = '--optimize-autoloader';
        }

        if (!empty($opt['prefer_stable']) || !isset($opt['prefer_stable'])) {
            $commandParts[] = '--prefer-stable';
        }

        if (!empty($opt['with_dependencies']) || !isset($opt['with_dependencies'])) {
            $commandParts[] = '--with-dependencies';
        }

        if (!empty($opt['no_dev'])) {
            $commandParts[] = '--no-dev';
        }

        $commandString = implode(' ', $commandParts);

        $this->pushProgress($task, [
            'progress' => 40,
            'message' => __('sTask::global.running_composer') . ': composer ' . $commandString,
        ]);

        // Create Composer Application
        try {
            // Set up Composer environment variables
            $this->setupComposerEnvironment();

            $input = new \Symfony\Component\Console\Input\StringInput($commandString);
            $output = new \Symfony\Component\Console\Output\BufferedOutput();

            $application = new \Composer\Console\Application();
            $application->setAutoExit(false);

            $this->pushProgress($task, [
                'progress' => 50,
                'message' => '_' . __('sTask::global.executing_composer') . '..._',
            ]);

            $exitCode = $application->run($input, $output);
            $outputText = $output->fetch();
        } catch (\Throwable $e) {
            Log::error('Composer API threw exception', [
                'task_id' => $task->id,
                'exception_class' => get_class($e),
                'exception' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            // Try to get any output that was captured
            $partialOutput = isset($output) ? $output->fetch() : '';
            if ($partialOutput) {
                Log::error('Partial output before exception', [
                    'task_id' => $task->id,
                    'output' => $partialOutput,
                ]);
            }

            throw new \Exception('Composer threw exception: ' . $e->getMessage());
        }

        // Process output lines for UI
        if ($outputText) {
            $lines = explode("\n", $outputText);
            $progress = 50;
            foreach ($lines as $line) {
                $cleanLine = trim($line);
                if (!empty($cleanLine)) {
                    $progress = $this->processOutputLine($cleanLine, $progress, $task);
                }
            }
        }

        if ($exitCode === 0) {
            $totalTime = microtime(true) - $startTime;
            $finalMessage = '**' . __('sTask::global.composer_updated_successfully') . ' (' . round($totalTime, 2) . ' s)**';

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
        } else {
            // Log full output for debugging
            Log::error('Composer update failed via API', [
                'task_id' => $task->id,
                'exit_code' => $exitCode,
                'full_output' => $outputText,
            ]);

            // Show last 20 lines of output in error message
            $errorLines = array_slice(explode("\n", $outputText), -20);
            $errorPreview = implode("\n", $errorLines);

            throw new \Exception("Composer update failed with exit code: {$exitCode}\n\nLast output:\n{$errorPreview}");
        }
    }

    /**
     * Run Composer by creating and executing a temporary PHP script.
     */
    protected function runComposerViaScript(sTaskModel $task, string $composerPath, array $command, string $projectRoot, array $opt): void
    {
        $startTime = \microtime(true);

        $this->pushProgress($task, [
            'progress' => 30,
            'message' => '_' . __('sTask::global.preparing_phar_execution') . '..._',
        ]);

        // Clean the path - remove php prefix if present
        $pharPath = str_replace(['php ', 'php"', '"', "'"], '', $composerPath);

        // If it's a symlink, resolve it
        if (is_link($pharPath)) {
            $realPath = readlink($pharPath);
            // If relative path, make it absolute
            if ($realPath && $realPath[0] !== '/') {
                $realPath = dirname($pharPath) . '/' . $realPath;
            }
            if ($realPath && file_exists($realPath)) {
                $pharPath = $realPath;
            }
        }

        // Build arguments for Composer
        $args = ['update', '--no-interaction', '--verbose'];

        if (!empty($opt['optimize']) || !isset($opt['optimize'])) {
            $args[] = '--optimize-autoloader';
        }
        if (!empty($opt['prefer_stable']) || !isset($opt['prefer_stable'])) {
            $args[] = '--prefer-stable';
        }
        if (!empty($opt['with_dependencies']) || !isset($opt['with_dependencies'])) {
            $args[] = '--with-dependencies';
        }

        $this->pushProgress($task, [
            'progress' => 40,
            'message' => __('sTask::global.loading_composer_phar') . ': ' . basename($pharPath),
        ]);

        // Set environment for Composer execution
        $oldArgv = $_SERVER['argv'] ?? [];
        $oldArgc = $_SERVER['argc'] ?? 0;

        $_SERVER['argv'] = array_merge(['composer'], $args);
        $_SERVER['argc'] = count($_SERVER['argv']);

        // Set up Composer environment variables
        $this->setupComposerEnvironment();

        // Execute Composer PHAR
        ob_start();
        $exitCode = 1;

        try {
            $this->pushProgress($task, [
                'progress' => 50,
                'message' => __('sTask::global.executing') . ': composer ' . implode(' ', $args),
            ]);

            // Check if it's a PHAR file
            if (str_ends_with($pharPath, '.phar')) {
                if (!file_exists($pharPath)) {
                    throw new \Exception("PHAR file not found: {$pharPath}");
                }

                // Include the PHAR file directly
                \Phar::loadPhar($pharPath, 'composer.phar');
                include 'phar://composer.phar/bin/composer';

                $exitCode = 0; // If we got here without exception, assume success
            } else {
                // Not a PHAR - try to use vendor Composer classes directly
                $this->pushProgress($task, [
                    'progress' => 55,
                    'message' => '_' . __('sTask::global.using_composer_from_vendor') . '..._',
                ]);

                // Use Composer Application class from vendor
                if (!class_exists('\Composer\Console\Application')) {
                    throw new \Exception('Composer Application class not found in vendor. Please run: composer install');
                }

                // Create a temporary stream to capture output
                $stream = fopen('php://temp', 'w+');
                $output = new \Symfony\Component\Console\Output\StreamOutput($stream);

                $input = new \Symfony\Component\Console\Input\ArrayInput([
                    'command' => 'update',
                    '--no-interaction' => true,
                    '--verbose' => true,
                    '--no-scripts' => true,
                    '--optimize-autoloader' => true,
                    '--prefer-stable' => true,
                    '--with-dependencies' => true,
                ]);
                $input->setInteractive(false);

                $application = new \Composer\Console\Application();
                $application->setAutoExit(false);

                $exitCode = $application->run($input, $output);

                // Get output from stream
                rewind($stream);
                $vendorOutput = stream_get_contents($stream);
                fclose($stream);

                // Process output line by line for UI display
                if ($vendorOutput) {
                    $lines = explode("\n", $vendorOutput);
                    $progress = 60;

                    foreach ($lines as $line) {
                        $cleanLine = trim($line);
                        if (!empty($cleanLine)) {
                            // Process each line to update progress and UI
                            $progress = $this->processOutputLine($cleanLine, $progress, $task);
                        }
                    }

                    // Replace ob buffer content with vendor output
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    ob_start();
                    echo $vendorOutput;
                }

                if ($exitCode !== 0) {
                    throw new \Exception("Composer update failed with exit code: {$exitCode}");
                }
            }

            $outputText = ob_get_clean();
        } catch (\Throwable $e) {
            $outputText = ob_get_clean();

            Log::error('Composer PHAR execution failed', [
                'task_id' => $task->id,
                'exception' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'output' => $outputText,
            ]);

            // Restore environment
            $_SERVER['argv'] = $oldArgv;
            $_SERVER['argc'] = $oldArgc;

            throw new \Exception('Composer execution failed: ' . $e->getMessage() . "\nOutput: " . $outputText);
        }

        // Restore environment
        $_SERVER['argv'] = $oldArgv;
        $_SERVER['argc'] = $oldArgc;

        // Process output
        if ($outputText) {
            $lines = explode("\n", $outputText);
            $progress = 60;
            foreach ($lines as $line) {
                $cleanLine = trim($line);
                if (!empty($cleanLine)) {
                    $progress = $this->processOutputLine($cleanLine, $progress, $task);
                }
            }
        }

        $totalTime = microtime(true) - $startTime;
        $finalMessage = '**' . __('sTask::global.composer_updated_successfully') . ' (' . round($totalTime, 2) . ' s)**';

        $task->update([
            'status' => sTaskModel::TASK_STATUS_FINISHED,
            'progress' => 100,
            'message' => $finalMessage,
            'result' => $outputText,
            'finished_at' => now(),
        ]);

        $this->pushProgress($task, [
            'progress' => 100,
            'message' => $finalMessage,
        ]);
    }

    /**
     * Ensure the given directory exists and is writable.
     *
     * @param string $path Absolute directory path
     * @return void
     */
    protected function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException('Unable to create directory for Composer: ' . $path);
        }
    }

    /**
     * Setup Composer environment variables
     *
     * Sets up COMPOSER_HOME, COMPOSER_CACHE_DIR and related environment variables
     * in all places where Composer might look (putenv, $_ENV, $_SERVER)
     *
     * @return array Array with 'home' and 'cache' paths
     */
    protected function setupComposerEnvironment(): array
    {
        $composerHome = rtrim(storage_path('composer'), DIRECTORY_SEPARATOR);
        $composerCacheDir = $composerHome . DIRECTORY_SEPARATOR . 'cache';

        // Ensure directories exist
        $this->ensureDirectory($composerHome);
        $this->ensureDirectory($composerCacheDir);

        // Set environment variables in all places Composer might look
        $envVars = [
            'COMPOSER_HOME' => $composerHome,
            'COMPOSER_CACHE_DIR' => $composerCacheDir,
            'COMPOSER_NO_INTERACTION' => '1',
            'COMPOSER_ALLOW_SUPERUSER' => '1',
            'COMPOSER_DISABLE_XDEBUG_WARN' => '1',
            'HOME' => $composerHome,
        ];

        foreach ($envVars as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return [
            'home' => $composerHome,
            'cache' => $composerCacheDir,
        ];
    }

    /**
     * Find the composer executable in the system.
     *
     * This method searches for composer executable in multiple locations.
     * In Evolution CMS, composer.phar is typically located in the core/ directory.
     *
     * @return string Path to composer executable
     */
    protected function findComposerExecutable(): string
    {
        $coreDir = base_path(); // In Evolution CMS this is the core/ directory
        $searchPaths = [];

        // Build list of paths to search (priority order)
        $possiblePaths = [
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            '/usr/local/bin/composer.phar',
            '/usr/bin/composer.phar',
            $coreDir . '/composer.phar',
            dirname($coreDir) . '/composer.phar',
            $coreDir . '/composer',
            dirname($coreDir) . '/composer',
            $coreDir . '/vendor/bin/composer',
        ];

        // Search for composer
        foreach ($possiblePaths as $path) {
            $searchPaths[] = $path;
            if (file_exists($path) && is_readable($path)) {
                // Skip directories
                if (is_dir($path)) {
                    continue;
                }

                // For non-PHAR files, check if executable
                if (!str_ends_with($path, '.phar')) {
                    // Non-PHAR files must be executable
                    if (!is_executable($path)) {
                        continue; // Skip this file
                    }

                    // Check if it's a symlink and resolve it
                    if (is_link($path)) {
                        $realPath = realpath($path);
                        if ($realPath && str_ends_with($realPath, '.phar')) {
                            // Symlink points to a .phar file - use with php
                            return PHP_OS_FAMILY === 'Windows' ?
                                'php "' . $realPath . '"' :
                                'php ' . escapeshellarg($realPath);
                        }
                    }

                    return $path;
                }

                // If it's a .phar file, prepend with php
                return PHP_OS_FAMILY === 'Windows' ?
                    'php "' . $path . '"' :
                    'php ' . escapeshellarg($path);
            }
        }

        // Log all searched paths for debugging
        Log::warning('Composer not found in any location', [
            'searched_paths' => $searchPaths,
            'base_path' => $coreDir,
            'php_binary' => PHP_BINARY,
        ]);

        // Try standard composer command as last resort
        // This might work if composer is in PATH but we couldn't verify it
        return 'composer';
    }
}

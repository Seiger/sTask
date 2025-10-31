<?php namespace Seiger\sTask\Workers;

use Illuminate\Support\Facades\Log;
use Seiger\sTask\Models\sTaskModel;
use Symfony\Component\Process\Process;

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
 * @since 1.0.0
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
     * Render the worker widget for the administrative interface.
     *
     * This method renders a custom widget for the ComposerUpdateWorker
     * that includes worker-specific controls and information.
     *
     * @return string HTML content for the worker widget
     */
    public function renderWidget(): string
    {
        return '';
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

        try {
            // Preparing
            $task->update([
                'status' => sTaskModel::TASK_STATUS_PREPARING,
                'message' => __('sTask::global.task_preparing') . '...',
            ]);

            $this->pushProgress($task, [
                'progress' => 0,
                'status' => 'preparing',
            ]);

            // Get composer path
            $composerPath = $this->findComposerExecutable();
            if (!$composerPath) {
                throw new \RuntimeException('Composer executable not found. Please install Composer.');
            }

            // Check if composer.json exists
            $projectRoot = base_path();
            if (!file_exists($projectRoot . '/composer.json')) {
                throw new \RuntimeException('composer.json not found in project root.');
            }

            // Prepare composer update command
            $command = [
                $composerPath,
                'update',
                '--no-interaction',
                '--verbose',
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

            // Set working directory to project root
            $timeout = $opt['timeout'] ?? 600;

            $task->update([
                'status' => sTaskModel::TASK_STATUS_RUNNING,
                'message' => __('sTask::global.composer_updating') . '...',
            ]);

            $this->pushProgress($task, [
                'progress' => 10,
                'status' => 'running',
                'message' => __('sTask::global.composer_updating') . '...',
            ]);

            // Execute composer update
            $startTime = microtime(true);
            $output = [];
            $progress = 10;

            // Create process
            $process = new Process($command, $projectRoot, null, null, $timeout);
            $process->start();

            // Track output in real-time
            foreach ($process as $type => $data) {
                $output[] = $data;

                // Update progress based on output patterns
                if (strpos($data, 'Loading composer repositories') !== false) {
                    $progress = 20;
                } elseif (strpos($data, 'Updating dependencies') !== false) {
                    $progress = 30;
                } elseif (strpos($data, 'Package operations:') !== false) {
                    $progress = 50;
                } elseif (strpos($data, 'Generating autoload files') !== false) {
                    $progress = 80;
                } elseif (strpos($data, 'Running composer scripts') !== false) {
                    $progress = 90;
                }

                $this->pushProgress($task, [
                    'progress' => min($progress, 95),
                    'message' => trim($data),
                ]);

                // Log output
                Log::info('Composer update output: ' . trim($data), [
                    'task_id' => $task->id,
                ]);
            }

            // Check if process was successful
            if (!$process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                throw new \RuntimeException('Composer update failed: ' . $errorOutput);
            }

            $totalTime = microtime(true) - $startTime;
            $outputText = implode("\n", $output);

            // Done
            $task->update([
                'status' => sTaskModel::TASK_STATUS_FINISHED,
                'progress' => 100,
                'message' => '**' . __('sTask::global.done') . '. ' .
                    __('sTask::global.composer_updated_successfully') .
                    ' (' . round($totalTime, 2) . 's)**',
                'result' => $outputText,
                'finished_at' => now(),
            ]);

            $this->pushProgress($task, [
                'status' => 'finished',
                'progress' => 100,
                'message' => '**' . __('sTask::global.done') . '. ' .
                    __('sTask::global.composer_updated_successfully') .
                    ' (' . round($totalTime, 2) . 's)**',
            ]);

            Log::info('Composer update completed successfully', [
                'task_id' => $task->id,
                'duration' => round($totalTime, 2),
            ]);
        } catch (\Throwable $e) {
            $where = basename($e->getFile()) . ':' . $e->getLine();
            $message = 'Failed @ ' . $where . ' — ' . $e->getMessage();

            $task->update([
                'status' => sTaskModel::TASK_STATUS_FAILED,
                'message' => $message,
                'finished_at' => now(),
            ]);

            $this->pushProgress($task, [
                'status' => 'failed',
                'message' => $message,
            ]);

            Log::error('Composer update failed: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * Find the composer executable in the system.
     *
     * This method searches for composer executable in multiple locations:
     * 1. composer.phar in project root
     * 2. composer in system PATH
     * 3. composer.phar in system PATH
     *
     * @return string|null Path to composer executable or null if not found
     */
    protected function findComposerExecutable(): ?string
    {
        // Check for composer.phar in project root
        $projectComposer = base_path('composer.phar');
        if (file_exists($projectComposer)) {
            return 'php ' . $projectComposer;
        }

        // Check for composer in PATH
        if ($this->commandExists('composer')) {
            return 'composer';
        }

        // Check for composer.phar in PATH
        if ($this->commandExists('composer.phar')) {
            return 'composer.phar';
        }

        return null;
    }

    /**
     * Check if a command exists in the system PATH.
     *
     * @param string $command Command to check
     * @return bool True if command exists, false otherwise
     */
    protected function commandExists(string $command): bool
    {
        $process = Process::fromShellCommandline(
            PHP_OS_FAMILY === 'Windows' ? "where $command" : "which $command"
        );

        $process->run();

        return $process->isSuccessful();
    }
}

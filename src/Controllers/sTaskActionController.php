<?php namespace Seiger\sTask\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Seiger\sTask\Contracts\TaskInterface;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Models\sWorker;
use Seiger\sTask\Services\TaskProgress;
use Seiger\sTask\Services\WorkerService;

/**
 * sTaskActionController - Controller for task management
 *
 * This controller handles the complete lifecycle of tasks from creation
 * to completion, providing RESTful endpoints for task management, progress tracking,
 * and file downloads. It serves as the primary interface between the frontend and
 * the background task processing system.
 *
 * Key Features:
 * - Task creation and initialization through worker resolution
 * - Asynchronous task execution with background worker launching
 * - Real-time progress tracking via filesystem-based snapshots
 * - File download handling for completed export tasks
 * - Comprehensive error handling and logging
 * - Support for multiple worker types
 *
 * API Endpoints:
 * - POST /stask/workers/{identifier}/run/{action} - Start a new task
 * - GET /stask/tasks/{id}/progress - Get task progress
 * - GET /stask/tasks/{id}/download - Download task result
 *
 * Task Lifecycle:
 * 1. Task creation via start() method
 * 2. Background worker launching for asynchronous execution
 * 3. Progress tracking through progress() method
 * 4. File download via download() method upon completion
 *
 * @package Seiger\sTask\Controllers
 * @author Seiger IT Team
 * @since 1.0.0
 */
class sTaskActionController extends BaseController
{
    /**
     * Start a task for a given worker identifier and action.
     *
     * This method creates a new task and attempts to launch it asynchronously.
     * It resolves the worker from the database, creates the task with proper
     * initialization, and launches the background worker to process the task.
     *
     * This method accepts ALL request parameters and passes them as options to the
     * worker task, providing maximum flexibility for third-party developers.
     * Security is ensured through admin middleware protection.
     *
     * The method handles:
     * - Worker resolution and validation
     * - Task creation with all request parameters as options
     * - Asynchronous worker launching (exec/shell_exec or fallback)
     * - Error handling and logging
     *
     * Route: POST /stask/workers/{identifier}/run/{action}
     *
     * @param string $identifier The worker identifier (e.g., 's_products_listing_cache')
     * @param string $action The action to perform (e.g., 'make')
     * @return JsonResponse JSON response with task ID and status
     *
     * @example
     * // Run products listing cache
     * POST /stask/workers/s_products_listing_cache/run/make
     * Body: {"force": true}
     *
     * @example
     * // Custom worker with parameters
     * POST /stask/workers/custom_worker/run/sync
     * Body: {"api_key": "xxx", "endpoint": "https://api.example.com", "settings": {...}}
     */
    public function run(string $identifier, string $action): JsonResponse
    {
        try {
            $options = request()->all();
            $worker = $this->resolveWorkerOrFail($identifier);
            $task = $worker->createTask($action, $options);

            if ($task && Carbon::parse($task->created_at) <= Carbon::now()) {
                // Launch worker (will use fastcgi_finish_request if available)
                $this->launchTaskWorker();
            }
        } catch (\Throwable $e) {
            Log::warning('sTaskActionController launch failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'id' => (int)$task->id, 'message' => 'Task created successfully']);
    }

    /**
     * Get task progress snapshot from filesystem-based tracking.
     *
     * This method retrieves the current progress state for a task from the TaskProgress
     * filesystem-based tracking system. It provides real-time updates on task status,
     * progress percentage, processing statistics, and current messages.
     *
     * The method handles:
     * - Progress file existence validation
     * - JSON data parsing and validation
     * - Error state detection and reporting
     * - Comprehensive error handling and logging
     *
     * Route: GET /stask/tasks/{id}/progress
     *
     * @param int $id The task ID to get progress for
     * @return JsonResponse JSON response with progress data or error information
     */
    public function progress(int $id): JsonResponse
    {
        try {
            // Check if ID is valid
            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'code' => 400,
                    'error' => 'Invalid task ID',
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Task ID must be greater than 0'
                ], 400);
            }

            $file = TaskProgress::file($id);

            if (!is_file($file)) {
                return response()->json([
                    'success' => false,
                    'code' => 404,
                    'error' => 'Progress file not found',
                    'id' => $id,
                    'status' => 'not_found',
                    'message' => 'Progress tracking not available'
                ], 404);
            }

            $json = file_get_contents($file);
            $data = json_decode($json, true);

            if (!is_array($data)) {
                return response()->json([
                    'success' => false,
                    'code' => 500,
                    'error' => 'Invalid progress data',
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Invalid progress data'
                ], 500);
            }

            return response()->json(array_merge([
                'success' => (isset($data['code']) && $data['code'] == 500 ? false : true),
                'code' => 200,
            ], $data));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::channel('stask')->warning('Task not found', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'code' => 404,
                'error' => 'Task not found',
                'id' => $id,
                'status' => 'not_found',
                'message' => 'Task with ID ' . $id . ' does not exist'
            ], 404);
        } catch (\Throwable $e) {
            Log::channel('stask')->error('Failed to get task progress', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'code' => 500,
                'error' => 'Failed to get progress',
                'id' => $id,
                'status' => 'error',
                'progress' => 0,
                'message' => 'Failed to get progress'
            ], 500);
        }
    }

    /**
     * Download exported file for completed task.
     *
     * This method serves exported files as downloadable responses for tasks that have
     * completed successfully. It validates task completion status, checks file existence,
     * generates appropriate filenames, and returns the file with proper MIME types.
     *
     * The method handles:
     * - Task completion status validation
     * - File existence and accessibility checks
     * - Dynamic filename generation with timestamps
     * - MIME type detection and proper headers
     * - Error handling for missing or inaccessible files
     *
     * Route: GET /stask/tasks/{id}/download
     *
     * @param int $id The task ID to download the result file for
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse File download response or error JSON
     */
    public function download(int $id)
    {
        try {
            $task = sTaskModel::findOrFail($id);

            // Check if task is finished
            if ((int)$task->status !== sTaskModel::TASK_STATUS_FINISHED) {
                return response()->json([
                    'success' => false,
                    'code' => 400,
                    'error' => 'Task not completed',
                    'message' => 'Task must be completed before downloading'
                ], 400);
            }

            // Check if result file exists
            if (!$task->result || !is_file($task->result)) {
                return response()->json([
                    'success' => false,
                    'code' => 404,
                    'error' => 'Export file not found',
                    'message' => 'Export file is not available'
                ], 404);
            }

            // Return file download response
            $filename = basename($task->result);
            return response()->download(
                $task->result,
                $filename,
                [
                    'Content-Type' => $this->getMimeType(pathinfo($filename, PATHINFO_EXTENSION)),
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                ]
            );
        } catch (\Throwable $e) {
            Log::channel('stask')->error('Failed to download task file', [
                'task_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'code' => 500,
                'error' => 'Download failed',
                'message' => 'Failed to download file'
            ], 500);
        }
    }

    /**
     * Upload file for task processing.
     *
     * This method handles file uploads for tasks that require input files,
     * such as CSV import operations. It validates the uploaded file, stores it
     * in a secure location, and returns the file information for task processing.
     *
     * The method handles:
     * - File validation (type, size, security)
     * - Secure file storage in task-specific directories
     * - File metadata extraction and storage
     * - Error handling for invalid or malicious files
     * - Support for chunked uploads for large files
     *
     * Route: POST /stask/tasks/{id}/upload
     *
     * @param int $id The task ID to associate the uploaded file with
     * @return JsonResponse JSON response with file information or error details
     */
    public function upload(int $id): JsonResponse
    {
        try {
            $task = sTaskModel::findOrFail($id);

            // Check if task is in correct status for file upload
            if ((int)$task->status !== sTaskModel::TASK_STATUS_QUEUED &&
                (int)$task->status !== sTaskModel::TASK_STATUS_PREPARING) {
                return response()->json([
                    'success' => false,
                    'code' => 400,
                    'error' => 'Invalid task status',
                    'message' => 'Task must be queued or preparing to accept file uploads'
                ], 400);
            }

            // Validate file upload
            if (!request()->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'code' => 400,
                    'error' => 'No file uploaded',
                    'message' => 'Please select a file to upload'
                ], 400);
            }

            $file = request()->file('file');

            // Get file size before moving
            $fileSize = $file->getSize();

            // Validate file size (max 50MB)
            $maxSize = 50 * 1024 * 1024; // 50MB
            if ($fileSize > $maxSize) {
                return response()->json([
                    'success' => false,
                    'code' => 400,
                    'error' => 'File too large',
                    'message' => 'File size must not exceed 50MB'
                ], 400);
            }

            // Get allowed extensions from worker settings
            $allowedExtensions = $this->getWorkerAllowedExtensions($task->identifier);
            $extension = strtolower($file->getClientOriginalExtension());
            if (!in_array($extension, $allowedExtensions)) {
                return response()->json([
                    'success' => false,
                    'code' => 400,
                    'error' => 'Invalid file type',
                    'message' => 'Allowed file types: ' . implode(', ', $allowedExtensions)
                ], 400);
            }

            // Create upload directory
            $uploadDir = storage_path('stask/uploads');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename with task ID prefix
            $filename = 'task_' . $id . '_' . time() . '_' . uniqid() . '.' . $extension;
            $filePath = $uploadDir . '/' . $filename;

            // Store the file
            $file->move($uploadDir, $filename);

            // Update task with file information
            $task->update([
                'meta' => array_merge($task->meta ?? [], [
                    'uploaded_file' => $filePath,
                    'original_filename' => $file->getClientOriginalName(),
                    'file_size' => $fileSize,
                    'uploaded_at' => now()->toISOString()
                ])
            ]);

            return response()->json([
                'success' => true,
                'code' => 200,
                'message' => 'File uploaded successfully',
                'result' => $filename,
                'original_filename' => $file->getClientOriginalName(),
                'file_size' => $fileSize,
                'file_path' => $filePath
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::channel('stask')->warning('Task not found for upload', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'code' => 404,
                'error' => 'Task not found',
                'message' => 'Task with ID ' . $id . ' does not exist'
            ], 404);
        } catch (\Throwable $e) {
            Log::channel('stask')->error('Failed to upload file for task', [
                'task_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'code' => 500,
                'error' => 'Upload failed',
                'message' => 'Failed to upload file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload file for worker processing.
     *
     * This method handles file uploads for workers that require input files,
     * such as CSV import operations. It validates the uploaded file, stores it
     * in a secure location, and returns the file information for worker processing.
     *
     * The method handles:
     * - File validation (type, size, security)
     * - Secure file storage in worker-specific directories
     * - File metadata extraction and storage
     * - Error handling for invalid or malicious files
     * - Support for chunked uploads for large files
     *
     * Route: POST /stask/workers/{identifier}/upload
     *
     * @param string $identifier The worker identifier
     * @return JsonResponse JSON response with file information or error details
     */
    public function uploadFile(string $identifier): JsonResponse
    {
        try {
            // Validate worker exists and is active
            $worker = sWorker::query()->active()->where('identifier', $identifier)->first();
            if (!$worker) {
                return response()->json([
                    'success' => false,
                    'code' => 404,
                    'error' => 'Worker not found',
                    'message' => 'Worker with identifier "' . $identifier . '" not found or inactive'
                ], 404);
            }

            // Check if this is a chunked upload
            $isChunkedUpload = request()->has('chunk_index') && request()->has('total_chunks');

            if ($isChunkedUpload) {
                return $this->handleChunkedUpload($identifier);
            }

            // Validate file upload
            if (!request()->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'code' => 400,
                    'error' => 'No file uploaded',
                    'message' => 'Please select a file to upload'
                ], 400);
            }

            $file = request()->file('file');

            // Get file size before moving
            $fileSize = $file->getSize();

            // Validate file size (max 50MB)
            $maxSize = 50 * 1024 * 1024; // 50MB
            if ($fileSize > $maxSize) {
                return response()->json([
                    'success' => false,
                    'code' => 400,
                    'error' => 'File too large',
                    'message' => 'File size must not exceed 50MB'
                ], 400);
            }

            // Get allowed extensions from worker settings
            $allowedExtensions = $this->getWorkerAllowedExtensions($identifier);
            $extension = strtolower($file->getClientOriginalExtension());
            if (!in_array($extension, $allowedExtensions)) {
                return response()->json([
                    'success' => false,
                    'code' => 400,
                    'error' => 'Invalid file type',
                    'message' => 'Allowed file types: ' . implode(', ', $allowedExtensions)
                ], 400);
            }

            // Create upload directory
            $uploadDir = storage_path('stask/uploads');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename with identifier prefix
            $filename = $identifier . '_' . time() . '_' . uniqid() . '.' . $extension;
            $filePath = $uploadDir . '/' . $filename;

            // Store the file
            $file->move($uploadDir, $filename);

            return response()->json([
                'success' => true,
                'code' => 200,
                'message' => 'File uploaded successfully',
                'result' => $filename,
                'original_filename' => $file->getClientOriginalName(),
                'file_size' => $fileSize,
                'file_path' => $filePath
            ]);
        } catch (\Throwable $e) {
            Log::channel('stask')->error('Failed to upload file for worker', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'code' => 500,
                'error' => 'Upload failed',
                'message' => 'Failed to upload file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get MIME type for file extension.
     *
     * This utility method maps file extensions to their corresponding MIME types
     * for proper HTTP response headers. It supports common file types used in
     * task exports and falls back to 'application/octet-stream' for unknown types.
     *
     * Supported file types:
     * - CSV files (text/csv)
     * - Excel files (xlsx, xls)
     * - JSON files (application/json)
     * - XML files (application/xml)
     * - Text files (text/plain)
     * - ZIP archives (application/zip)
     *
     * @param string $extension The file extension (without leading dot)
     * @return string The corresponding MIME type
     */
    protected function getMimeType(string $extension): string
    {
        $mimeTypes = [
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
        ];

        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }

    /**
     * Resolve active worker instance by identifier using database lookup.
     *
     * This method retrieves an active worker from the database by its identifier,
     * validates the worker class exists, instantiates it, and ensures it
     * implements the TaskInterface. It provides comprehensive error handling
     * for missing, inactive, or invalid workers.
     *
     * The resolution process:
     * 1. Queries sWorker table for active worker with matching identifier
     * 2. Validates the worker class exists and is loadable
     * 3. Instantiates the worker class via service container
     * 4. Verifies the instance implements TaskInterface
     * 5. Returns the validated worker instance
     *
     * @param string $identifier The worker identifier to resolve
     * @return \Seiger\sTask\Contracts\TaskInterface The resolved worker instance
     * @throws \InvalidArgumentException If worker not found or inactive
     * @throws \RuntimeException If worker class not found or invalid
     */
    protected function resolveWorkerOrFail(string $identifier): TaskInterface
    {
        $worker = sWorker::query()->active()->where('identifier', $identifier)->first();

        if (!$worker) {
            throw new \InvalidArgumentException(__('sTask::global.worker_not_found_or_inactive', ['identifier' => $identifier]));
        }

        $className = $worker->class ?? null;
        if (!$className || !class_exists($className)) {
            throw new \RuntimeException(__('sTask::global.worker_class_not_found', ['className' => $className]));
        }

        $instance = app()->make($className);
        if (!$instance instanceof TaskInterface) {
            throw new \RuntimeException(__('sTask::global.worker_must_implement_TaskInterface', ['className' => $className]));
        }

        return $instance;
    }

    /**
     * Get server upload limits.
     *
     * Returns information about server configuration limits for file uploads,
     * including maximum file size, chunk size, and single upload limit.
     *
     * Route: GET /stask/server-limits
     *
     * @return JsonResponse JSON response with server limits
     *
     * @example
     * Response:
     * {
     *   "maxFileSize": 104857600,    // 100 MB
     *   "chunkSize": 1048576,        // 1 MB
     *   "singleUploadLimit": 2097152 // 2 MB
     * }
     */
    public function serverLimits(): JsonResponse
    {
        return response()->json([
            'maxFileSize' => 100 * 1024 * 1024,     // 100 MB
            'chunkSize' => 1024 * 1024,             // 1 MB
            'singleUploadLimit' => 2 * 1024 * 1024, // 2 MB
        ]);
    }

    /**
     * Launch TaskWorker command in background for asynchronous task processing.
     *
     * This method attempts to execute the stask:worker Artisan command
     * in the background using exec() or shell_exec() if available.
     *
     * If background execution is not available, tasks will be processed
     * by the scheduled cron job instead. No error logging is performed
     * for missing exec functions as this is expected behavior in
     * restricted hosting environments.
     *
     * @return void
     */
    protected function launchTaskWorker(): void
    {
        try {
            $artisanPath = EVO_CORE_PATH . 'artisan';

            // Try exec for true async execution (best option)
            if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')))) {
                $command = "php \"{$artisanPath}\" stask:worker > /dev/null 2>&1 &";
                exec($command);
                return;
            }

            // Try shell_exec for true async execution (second best)
            if (function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')))) {
                $command = "php \"{$artisanPath}\" stask:worker > /dev/null 2>&1 &";
                shell_exec($command);
                return;
            }

            // If fastcgi_finish_request is available, use it for pseudo-async execution
            // This sends response to client and continues script execution
            if (function_exists('fastcgi_finish_request')) {
                register_shutdown_function(function() {
                    try {
                        // Finish request (sends response, but continues execution)
                        fastcgi_finish_request();

                        // Execute worker after response is sent
                        $console = app('Console');
                        $console->call('stask:worker');
                    } catch (\Throwable $e) {
                        // Silent fail - cron will handle task execution
                    }
                });
                return;
            }

            // No async options available - use shutdown function (slowest)
            // This will execute synchronously and block response
            register_shutdown_function(function() {
                try {
                    $console = app('Console');
                    $console->call('stask:worker');
                } catch (\Throwable $e) {
                    // Silent fail - cron will handle task execution
                }
            });
        } catch (\Throwable $e) {
            // Silent fail - cron will handle task execution
        }
    }

    /**
     * Handle chunked file upload
     *
     * @param string $identifier Worker identifier
     * @return JsonResponse
     */
    private function handleChunkedUpload(string $identifier): JsonResponse
    {
        try {
            $chunkIndex = (int) request()->input('chunk_index');
            $totalChunks = (int) request()->input('total_chunks');
            $sessionId = request()->input('session_id');
            $originalFilename = request()->input('original_filename');

            if (!request()->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'code' => 400,
                    'error' => 'No file chunk uploaded',
                    'message' => 'Please upload a file chunk'
                ], 400);
            }

            $file = request()->file('file');

            // Get allowed extensions from worker settings
            $allowedExtensions = $this->getWorkerAllowedExtensions($identifier);
            $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions)) {
                return response()->json([
                    'success' => false,
                    'code' => 400,
                    'error' => 'Invalid file type',
                    'message' => 'Allowed file types: ' . implode(', ', $allowedExtensions)
                ], 400);
            }

            // Create temporary directory for chunks
            $tempDir = storage_path('stask/temp/' . $sessionId);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Store chunk
            $chunkFilename = 'chunk_' . $chunkIndex;
            $file->move($tempDir, $chunkFilename);

            // If this is the last chunk, combine all chunks
            if ($chunkIndex === $totalChunks - 1) {
                $finalFilename = $identifier . '_' . time() . '_' . uniqid() . '.' . $extension;
                $finalPath = storage_path('stask/uploads/' . $finalFilename);

                // Ensure upload directory exists
                $uploadDir = storage_path('stask/uploads');
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Combine chunks
                $finalFile = fopen($finalPath, 'wb');
                for ($i = 0; $i < $totalChunks; $i++) {
                    $chunkPath = $tempDir . '/chunk_' . $i;
                    if (is_file($chunkPath)) {
                        $chunkData = file_get_contents($chunkPath);
                        fwrite($finalFile, $chunkData);
                        unlink($chunkPath); // Clean up chunk
                    }
                }
                fclose($finalFile);

                // Clean up temp directory
                rmdir($tempDir);

                return response()->json([
                    'success' => true,
                    'code' => 200,
                    'message' => 'File uploaded successfully',
                    'filename' => $finalFilename,
                    'file_path' => $finalPath
                ]);
            }

            return response()->json([
                'success' => true,
                'code' => 200,
                'message' => 'Chunk uploaded successfully',
                'chunk_index' => $chunkIndex,
                'total_chunks' => $totalChunks
            ]);
        } catch (\Throwable $e) {
            Log::channel('stask')->error('Failed to handle chunked upload', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'code' => 500,
                'error' => 'Chunked upload failed',
                'message' => 'Failed to handle chunked upload: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get allowed file extensions from worker settings
     *
     * @param string $identifier Worker identifier
     * @return array Allowed file extensions
     */
    private function getWorkerAllowedExtensions(string $identifier): array
    {
        try {
            $worker = app(WorkerService::class)->resolveWorker($identifier);
            $settings = $worker->settings();

            return $settings['allowed_extensions'] ?? ['csv'];
        } catch (Exception $e) {
            Log::warning("Failed to get worker settings for {$identifier}: " . $e->getMessage());
            return ['csv']; // Fallback to CSV
        }
    }
}

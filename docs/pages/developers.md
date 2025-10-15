---
title: Developer Guide
sidebar_label: Developer Guide
sidebar_position: 3
---

# Developer Guide

## Creating Custom Workers

To create a custom worker, extend the `BaseWorker` class which provides all common functionality:

```php
<?php namespace YourNamespace\Workers;

use Seiger\sTask\Workers\BaseWorker;
use Seiger\sTask\Models\sTaskModel;

class ProductWorker extends BaseWorker
{
    /**
     * Unique identifier for this worker
     */
    public function identifier(): string
    {
        return 'product';
    }
    
    /**
     * Module/package scope (for filtering in admin)
     */
    public function scope(): string
    {
        return 'scommerce';
    }
    
    /**
     * Icon for admin interface
     */
    public function icon(): string
    {
        return '<i class="fa fa-cube"></i>';
    }
    
    /**
     * Short human-readable title
     */
    public function title(): string
    {
        return 'Product Management';
    }
    
    /**
     * Detailed description
     */
    public function description(): string
    {
        return 'Import and export products from/to CSV files';
    }
    
    /**
     * Render widget for admin interface
     */
    public function renderWidget(): string
    {
        return view('your-package::widgets.product-worker', [
            'worker' => $this
        ])->render();
    }
    
    /**
     * Worker settings (optional)
     */
    public function settings(): array
    {
        return [
            'batch_size' => 100,
            'timeout' => 3600,
            'retry_on_fail' => true,
        ];
    }
    
    /**
     * Action: Import products from CSV
     */
    public function taskImport(sTaskModel $task, array $options = []): void
    {
        try {
            // Update task status
            $task->update(['status' => 20, 'message' => 'Starting import...']);
            
            // Get file from options
            $file = $options['file'] ?? null;
            if (!$file || !file_exists($file)) {
                throw new \Exception('Import file not found');
            }
            
            // Read CSV
            $handle = fopen($file, 'r');
            $header = fgetcsv($handle);
            
            // Count total rows
            $total = 0;
            while (fgets($handle)) $total++;
            rewind($handle);
            fgetcsv($handle); // Skip header
            
            $processed = 0;
            $startTime = microtime(true);
            
            // Process each row
            while (($row = fgetcsv($handle)) !== false) {
                $data = array_combine($header, $row);
                
                // Import product logic
                $this->importProduct($data);
                
                $processed++;
                
                // Update progress every 10 items
                if ($processed % 10 === 0 || $processed === $total) {
                    $progress = (int)(($processed / $total) * 100);
                    
                    // Calculate ETA
                    $elapsed = microtime(true) - $startTime;
                    $rate = $processed / $elapsed;
                    $remaining = $total - $processed;
                    $etaSeconds = $remaining > 0 ? $remaining / $rate : 0;
                    
                    $this->pushProgress($task, [
                        'progress' => $progress,
                        'processed' => $processed,
                        'total' => $total,
                        'eta' => niceEta($etaSeconds),
                        'message' => "Imported {$processed} of {$total} products"
                    ]);
                }
            }
            
            fclose($handle);
            
            // Mark as finished
            $this->markFinished(
                $task, 
                null, 
                "Successfully imported {$processed} products in " . round(microtime(true) - $startTime, 2) . "s"
            );
            
        } catch (\Exception $e) {
            $this->markFailed($task, $e->getMessage());
        }
    }
    
    /**
     * Action: Export products to CSV
     */
    public function taskExport(sTaskModel $task, array $options = []): void
    {
        try {
            $task->update(['status' => 20, 'message' => 'Starting export...']);
            
            // Prepare export file
            $filename = 'products_' . date('Y-m-d_His') . '.csv';
            $filepath = storage_path('stask/uploads/' . $filename);
            
            if (!is_dir(dirname($filepath))) {
                mkdir(dirname($filepath), 0755, true);
            }
            
            $handle = fopen($filepath, 'w');
            
            // Write header
            fputcsv($handle, ['ID', 'SKU', 'Name', 'Price', 'Stock']);
            
            // Get products
            $products = \DB::table('products')->get();
            $total = count($products);
            $processed = 0;
            
            foreach ($products as $i => $product) {
                fputcsv($handle, [
                    $product->id,
                    $product->sku,
                    $product->name,
                    $product->price,
                    $product->stock,
                ]);
                
                $processed++;
                
                if ($processed % 100 === 0 || $processed === $total) {
                    $progress = (int)(($processed / $total) * 100);
                    
                    $this->pushProgress($task, [
                        'progress' => $progress,
                        'processed' => $processed,
                        'total' => $total,
                        'message' => "Exported {$processed} of {$total} products"
                    ]);
                }
            }
            
            fclose($handle);
            
            $this->markFinished(
                $task,
                $filepath,
                "Exported {$total} products to {$filename}"
            );
            
        } catch (\Exception $e) {
            $this->markFailed($task, $e->getMessage());
        }
    }
    
    /**
     * Action: Sync stock levels
     */
    public function taskSyncStock(sTaskModel $task, array $options = []): void
    {
        try {
            $source = $options['source'] ?? 'api';
            
            $task->update(['status' => 20, 'message' => "Syncing stock from {$source}..."]);
            
            // Your sync logic here
            $items = $this->fetchStockFromSource($source);
            $total = count($items);
            
            foreach ($items as $i => $item) {
                $this->updateProductStock($item['sku'], $item['quantity']);
                
                if (($i + 1) % 50 === 0) {
                    $this->pushProgress($task, [
                        'progress' => (int)((($i + 1) / $total) * 100),
                        'processed' => $i + 1,
                        'total' => $total,
                    ]);
                }
            }
            
            $this->markFinished($task, null, "Synced stock for {$total} products");
            
        } catch (\Exception $e) {
            $this->markFailed($task, $e->getMessage());
        }
    }
    
    // Helper methods
    private function importProduct(array $data): void
    {
        // Your import logic
    }
    
    private function fetchStockFromSource(string $source): array
    {
        // Your API/source fetch logic
        return [];
    }
    
    private function updateProductStock(string $sku, int $quantity): void
    {
        // Update logic
    }
}
```

## Worker Discovery

Workers are automatically discovered if they:
1. Implement the `TaskInterface`
2. Are not abstract classes
3. Can be instantiated
4. Are in your package namespace

The discovery process scans all installed Composer packages and registers workers automatically.

## Task Management API

### Creating Tasks

```php
use Seiger\sTask\Facades\sTask;

// Basic task creation
$task = sTask::create(
    identifier: 'product',
    action: 'import',
    data: ['file' => '/path/to/products.csv'],
    priority: 'high',
    userId: evo()->getLoginUserID()
);

// Create with custom priority
$task = sTask::create(
    identifier: 'product',
    action: 'export',
    data: ['format' => 'csv', 'filters' => ['active' => true]],
    priority: 'normal', // 'low', 'normal', 'high'
    userId: evo()->getLoginUserID()
);

// Programmatic task creation from worker
$worker = new ProductWorker();
$task = $worker->createTask('import', ['file' => 'products.csv']);
```

### Processing Tasks

```php
// Process all pending tasks (default batch size: 10)
$processedCount = sTask::processPendingTasks();

// Process with custom batch size
$processedCount = sTask::processPendingTasks(batchSize: 50);

// Get task statistics
$stats = sTask::getStats();
/* Returns:
[
    'pending' => 5,
    'running' => 2,
    'completed' => 100,
    'failed' => 3,
    'cancelled' => 1,
    'total' => 111,
]
*/

// Get pending tasks
$pending = sTask::getPendingTasks(limit: 20);

foreach ($pending as $task) {
    echo "Task #{$task->id}: {$task->identifier} -> {$task->action}\n";
}
```

### Worker Management

```php
// Discover new workers
$registered = sTask::discoverWorkers();
echo "Registered " . count($registered) . " new workers\n";

// Rescan existing workers (update their metadata)
$updated = sTask::rescanWorkers();
echo "Updated " . count($updated) . " workers\n";

// Clean orphaned workers (classes no longer exist)
$deleted = sTask::cleanOrphanedWorkers();
echo "Deleted {$deleted} orphaned workers\n";

// Get all workers
$workers = sTask::getWorkers(activeOnly: false);

foreach ($workers as $worker) {
    echo "{$worker->identifier} ({$worker->scope}) - ";
    echo $worker->active ? 'Active' : 'Inactive';
    echo "\n";
}

// Get specific worker
$worker = sTask::getWorker('product');
if ($worker) {
    echo "Title: {$worker->title}\n";
    echo "Description: {$worker->description}\n";
    echo "Icon: {$worker->icon}\n";
}

// Activate/deactivate workers
sTask::activateWorker('product');
sTask::deactivateWorker('old_worker');

// Filter workers by scope
$commerceWorkers = \Seiger\sTask\Models\sWorker::byScope('scommerce')->get();
```

### Task Execution

```php
// Execute specific task
$task = \Seiger\sTask\Models\sTaskModel::find(1);
$result = sTask::execute($task);

if ($result) {
    echo "Task completed successfully\n";
} else {
    echo "Task failed: {$task->message}\n";
}

// Retry failed task
if ($task->canRetry()) {
    sTask::retry($task);
}
```

### Cleanup Operations

```php
// Clean tasks older than 30 days
$deletedTasks = sTask::cleanOldTasks(days: 30);
echo "Deleted {$deletedTasks} old tasks\n";

// Clean logs older than 30 days
$deletedLogs = sTask::cleanOldLogs(days: 30);
echo "Deleted {$deletedLogs} old log files\n";

// Custom cleanup
$deleted = \Seiger\sTask\Models\sTaskModel::where('status', 30) // completed
    ->where('finished_at', '<', now()->subDays(7))
    ->delete();
```

## Action Method Naming Convention

Action methods must follow the `task{Action}` convention:

| Action Name | Method Name | Example |
|-------------|-------------|---------|
| `import` | `taskImport()` | Import products |
| `export` | `taskExport()` | Export products |
| `sync_stock` | `taskSyncStock()` | Sync stock levels |
| `generate_report` | `taskGenerateReport()` | Generate reports |
| `send_emails` | `taskSendEmails()` | Send bulk emails |

```php
// Action name conversion examples:
'import' → taskImport()
'export' → taskExport()  
'sync' → taskSync()
'sync_stock' → taskSyncStock()
'send_emails' → taskSendEmails()
'generate_report' → taskGenerateReport()
'cleanup-old-data' → taskCleanupOldData()
```

## Progress Tracking

### Basic Progress Updates

```php
public function taskProcess(sTaskModel $task, array $options = []): void
{
    $items = range(1, 1000);
    $total = count($items);
    
    foreach ($items as $i => $item) {
        // Process item
        sleep(0.01); // Simulate work
        
        // Update progress
        $processed = $i + 1;
        $progress = (int)(($processed / $total) * 100);
        
        $this->pushProgress($task, [
            'progress' => $progress,
            'processed' => $processed,
            'total' => $total,
            'message' => "Processing item {$processed} of {$total}"
        ]);
    }
    
    $this->markFinished($task);
}
```

### Progress with ETA Calculation

```php
public function taskLongRunning(sTaskModel $task, array $options = []): void
{
    $total = 10000;
    $startTime = microtime(true);
    
    for ($i = 0; $i < $total; $i++) {
        // Process item
        $this->processItem($i);
        
        // Update every 100 items
        if ($i > 0 && $i % 100 === 0) {
            $elapsed = microtime(true) - $startTime;
            $rate = $i / $elapsed; // items per second
            $remaining = $total - $i;
            $etaSeconds = $remaining / $rate;
            
            $this->pushProgress($task, [
                'progress' => (int)(($i / $total) * 100),
                'processed' => $i,
                'total' => $total,
                'eta' => niceEta($etaSeconds),
                'message' => "Processing... {$i}/{$total}"
            ]);
        }
    }
    
    $this->markFinished($task, null, "Processed {$total} items");
}
```

### Multi-stage Progress

```php
public function taskMultiStage(sTaskModel $task, array $options = []): void
{
    try {
        // Stage 1: Preparation (0-20%)
        $this->pushProgress($task, [
            'progress' => 5,
            'message' => 'Preparing data...'
        ]);
        
        $data = $this->prepareData();
        
        $this->pushProgress($task, [
            'progress' => 20,
            'message' => 'Data prepared'
        ]);
        
        // Stage 2: Processing (20-80%)
        $total = count($data);
        foreach ($data as $i => $item) {
            $this->processItem($item);
            
            // Progress from 20% to 80%
            $stageProgress = ($i + 1) / $total; // 0.0 to 1.0
            $overallProgress = 20 + ($stageProgress * 60); // 20 to 80
            
            if ($i % 10 === 0) {
                $this->pushProgress($task, [
                    'progress' => (int)$overallProgress,
                    'processed' => $i + 1,
                    'total' => $total,
                    'message' => "Processing: {$i}/{$total}"
                ]);
            }
        }
        
        // Stage 3: Finalization (80-100%)
        $this->pushProgress($task, [
            'progress' => 85,
            'message' => 'Generating report...'
        ]);
        
        $reportPath = $this->generateReport($data);
        
        $this->pushProgress($task, [
            'progress' => 95,
            'message' => 'Saving results...'
        ]);
        
        $this->saveResults($data);
        
        // Done
        $this->markFinished($task, $reportPath, 'All stages completed');
        
    } catch (\Exception $e) {
        $this->markFailed($task, $e->getMessage());
    }
}
```

## Logging

### Automatic Logging

sTask automatically logs:
- Task start/completion
- Task failures
- Progress updates (in progress files)

Log files are stored in `storage/stask/{task_id}.log`

### Custom Logging

```php
use Seiger\sTask\Facades\sTask;

public function taskWithLogging(sTaskModel $task, array $options = []): void
{
    // Info log
    sTask::log($task, 'info', 'Starting import process', [
        'file' => $options['file'],
        'user_id' => $task->started_by
    ]);
    
    try {
        foreach ($items as $item) {
            try {
                $this->processItem($item);
            } catch (\Exception $e) {
                // Warning for non-critical errors
                sTask::log($task, 'warning', "Skipped item {$item->id}", [
                    'reason' => $e->getMessage(),
                    'item' => $item->toArray()
                ]);
                continue;
            }
        }
        
        // Success info
        sTask::log($task, 'info', 'Import completed successfully', [
            'total_processed' => count($items)
        ]);
        
        $this->markFinished($task);
        
    } catch (\Exception $e) {
        // Error log
        sTask::log($task, 'error', 'Import failed', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        $this->markFailed($task, $e->getMessage());
    }
}
```

### Reading Logs

```php
$task = \Seiger\sTask\Models\sTaskModel::find(1);

// Get all logs
$logs = $task->getLogs();
foreach ($logs as $log) {
    echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}\n";
    if (!empty($log['context'])) {
        print_r($log['context']);
    }
}

// Get last 10 logs
$recentLogs = $task->getLastLogs(10);

// Get error logs only
$errorLogs = $task->getErrorLogs();

// Clear task logs
$task->clearLogs();

// Download logs
return $task->logger()->downloadLogs($task);
```

## Error Handling

### Basic Error Handling

```php
public function taskSafe(sTaskModel $task, array $options = []): void
{
    try {
        // Your logic
        $result = $this->doSomething();
        
        if (!$result) {
            throw new \RuntimeException('Operation failed');
        }
        
        $this->markFinished($task);
        
    } catch (\Exception $e) {
        // Log detailed error
        sTask::log($task, 'error', $e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        $this->markFailed($task, $e->getMessage());
    }
}
```

### Retry Logic

```php
public function taskWithRetry(sTaskModel $task, array $options = []): void
{
    $maxRetries = 3;
    $currentAttempt = $task->attempts;
    
    try {
        // Attempt operation
        $this->doUnreliableOperation();
        
        $this->markFinished($task);
        
    } catch (\Exception $e) {
        if ($currentAttempt < $maxRetries) {
            // Will retry
            sTask::log($task, 'warning', 
                "Attempt {$currentAttempt} failed, will retry", 
                ['error' => $e->getMessage()]
            );
            
            // sTask will automatically retry
            throw $e;
        } else {
            // Max retries reached
            sTask::log($task, 'error', 
                "All {$maxRetries} attempts failed", 
                ['last_error' => $e->getMessage()]
            );
            
            $this->markFailed($task, "Failed after {$maxRetries} attempts: " . $e->getMessage());
        }
    }
}
```

### Validation Before Processing

```php
public function taskValidated(sTaskModel $task, array $options = []): void
{
    // Validate options
    $errors = [];
    
    if (empty($options['file'])) {
        $errors[] = 'File path is required';
    } elseif (!file_exists($options['file'])) {
        $errors[] = 'File does not exist: ' . $options['file'];
    }
    
    if (empty($options['user_id'])) {
        $errors[] = 'User ID is required';
    }
    
    if (!empty($errors)) {
        $this->markFailed($task, 'Validation failed: ' . implode('; ', $errors));
        return;
    }
    
    // Process
    try {
        $this->processFile($options['file'], $options['user_id']);
        $this->markFinished($task);
    } catch (\Exception $e) {
        $this->markFailed($task, $e->getMessage());
    }
}
```

## Task Priorities

```php
// High priority - processed first
$urgentTask = sTask::create(
    identifier: 'notification',
    action: 'send_urgent',
    data: ['email' => 'admin@example.com'],
    priority: 'high'
);

// Normal priority - default
$normalTask = sTask::create(
    identifier: 'report',
    action: 'generate',
    data: [],
    priority: 'normal'
);

// Low priority - processed last
$backgroundTask = sTask::create(
    identifier: 'cleanup',
    action: 'archive_old_data',
    data: [],
    priority: 'low'
);

// Tasks are processed in priority order:
// 1. All 'high' priority tasks
// 2. All 'normal' priority tasks  
// 3. All 'low' priority tasks
```

## Task Statuses

```php
use Seiger\sTask\Models\sTaskModel;

// Status constants
// 10 - pending
// 20 - running
// 30 - completed
// 40 - failed
// 50 - cancelled

// Check task status
$task = sTaskModel::find(1);

if ($task->isPending()) {
    echo "Task is waiting to be processed\n";
}

if ($task->isRunning()) {
    echo "Task is currently executing\n";
}

if ($task->isFinished()) {
    echo "Task is done (completed, failed, or cancelled)\n";
}

// Get status text
echo $task->status_text; // 'pending', 'running', 'completed', 'failed', 'cancelled'

// Query by status
$pendingTasks = sTaskModel::pending()->get();
$runningTasks = sTaskModel::running()->get();
$completedTasks = sTaskModel::completed()->get();
$failedTasks = sTaskModel::failed()->get();

// Get incomplete tasks (not finished and not failed)
$incompleteTasks = sTaskModel::incomplete()->get();

// Query by identifier and action
$productImports = sTaskModel::byIdentifier('product')
    ->byAction('import')
    ->get();

// Recent failed tasks
$recentFailures = sTaskModel::failed()
    ->where('created_at', '>', now()->subDays(7))
    ->orderBy('created_at', 'desc')
    ->get();
```

## Advanced Examples

### Batch Processing with Chunking

```php
public function taskBatchProcess(sTaskModel $task, array $options = []): void
{
    $chunkSize = 100;
    $processed = 0;
    
    // Get total count
    $total = \DB::table('products')->count();
    
    \DB::table('products')->orderBy('id')->chunk($chunkSize, function($products) use ($task, &$processed, $total) {
        foreach ($products as $product) {
            $this->processProduct($product);
            $processed++;
        }
        
        // Update progress after each chunk
        $this->pushProgress($task, [
            'progress' => (int)(($processed / $total) * 100),
            'processed' => $processed,
            'total' => $total,
            'message' => "Processed {$processed}/{$total} products"
        ]);
    });
    
    $this->markFinished($task, null, "Processed {$processed} products");
}
```

### File Download Task

```php
public function taskDownload(sTaskModel $task, array $options = []): void
{
    $url = $options['url'];
    $filename = basename($url);
    $destination = storage_path('downloads/' . $filename);
    
    if (!is_dir(dirname($destination))) {
        mkdir(dirname($destination), 0755, true);
    }
    
    $file = fopen($destination, 'w');
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $file);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    
    // Progress callback
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($resource, $downloadSize, $downloaded) use ($task) {
        if ($downloadSize > 0) {
            $progress = (int)(($downloaded / $downloadSize) * 100);
            
            $this->pushProgress($task, [
                'progress' => $progress,
                'processed' => $downloaded,
                'total' => $downloadSize,
                'message' => "Downloaded " . $this->formatBytes($downloaded) . " of " . $this->formatBytes($downloadSize)
            ]);
        }
    });
    
    curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    fclose($file);
    
    if ($error) {
        unlink($destination);
        $this->markFailed($task, "Download failed: {$error}");
    } else {
        $this->markFinished($task, $destination, "Downloaded {$filename}");
    }
}

private function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
```

### API Sync Task

```php
public function taskApiSync(sTaskModel $task, array $options = []): void
{
    $apiUrl = $options['api_url'];
    $apiKey = $this->settings()['api_key'];
    
    try {
        // Fetch from API
        $this->pushProgress($task, [
            'progress' => 10,
            'message' => 'Fetching data from API...'
        ]);
        
        $response = $this->apiRequest($apiUrl, $apiKey);
        $items = json_decode($response, true);
        
        if (!is_array($items)) {
            throw new \RuntimeException('Invalid API response');
        }
        
        $total = count($items);
        
        // Process items
        foreach ($items as $i => $item) {
            $this->syncItem($item);
            
            if (($i + 1) % 10 === 0) {
                $progress = 10 + (int)((($i + 1) / $total) * 80); // 10% to 90%
                
                $this->pushProgress($task, [
                    'progress' => $progress,
                    'processed' => $i + 1,
                    'total' => $total,
                    'message' => "Synced {$i+1}/{$total} items"
                ]);
            }
        }
        
        // Final cleanup
        $this->pushProgress($task, [
            'progress' => 95,
            'message' => 'Cleaning up...'
        ]);
        
        $this->cleanup();
        
        $this->markFinished($task, null, "Synced {$total} items from API");
        
    } catch (\Exception $e) {
        $this->markFailed($task, $e->getMessage());
    }
}
```

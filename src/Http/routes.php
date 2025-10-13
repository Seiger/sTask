<?php

use Illuminate\Support\Facades\Route;
use Seiger\sTask\Controllers\sTaskController;

// Manager routes only
Route::middleware(['mgr'])->group(function () {
    Route::prefix('stask')->name('stask.')->group(function () {
        // Dashboard
        Route::get('/', [sTaskController::class, 'index'])->name('index');
        Route::get('/stats', [sTaskController::class, 'stats'])->name('stats');
        
        // Task management
        Route::post('/tasks', [sTaskController::class, 'create'])->name('tasks.create');
        Route::get('/tasks/{task}', [sTaskController::class, 'show'])->name('tasks.show');
        Route::post('/tasks/{task}/execute', [sTaskController::class, 'execute'])->name('tasks.execute');
        Route::post('/tasks/{task}/cancel', [sTaskController::class, 'cancel'])->name('tasks.cancel');
        Route::post('/tasks/{task}/retry', [sTaskController::class, 'retry'])->name('tasks.retry');
        
        // Task logs
        Route::get('/tasks/{task}/logs', [sTaskController::class, 'logs'])->name('tasks.logs');
        Route::get('/tasks/{task}/logs/download', [sTaskController::class, 'downloadLogs'])->name('tasks.logs.download');
        Route::delete('/tasks/{task}/logs', [sTaskController::class, 'clearLogs'])->name('tasks.logs.clear');
        
        // System operations
        Route::post('/process', [sTaskController::class, 'process'])->name('process');
        Route::post('/clean', [sTaskController::class, 'clean'])->name('clean');
        
        // Workers management
        Route::get('/workers', [sTaskController::class, 'workers'])->name('workers.index');
        Route::post('/workers/discover', [sTaskController::class, 'discoverWorkers'])->name('workers.discover');
        Route::post('/workers/rescan', [sTaskController::class, 'rescanWorkers'])->name('workers.rescan');
        Route::post('/workers/clean-orphaned', [sTaskController::class, 'cleanOrphanedWorkers'])->name('workers.clean');
        Route::post('/workers/activate', [sTaskController::class, 'activateWorker'])->name('workers.activate');
        Route::post('/workers/deactivate', [sTaskController::class, 'deactivateWorker'])->name('workers.deactivate');
    });
});
<?php

use Illuminate\Support\Facades\Route;
use Seiger\sTask\Controllers\sTaskController;
use Seiger\sTask\Controllers\sTaskActionController;

// Manager routes only
Route::middleware(['mgr'])->group(function () {
    Route::prefix('stask')->name('sTask.')->group(function () {
        // Dashboard
        Route::get('/', [sTaskController::class, 'index'])->name('index');
        Route::get('/stats', [sTaskController::class, 'stats'])->name('stats');
        
        // Task management
        Route::post('/tasks', [sTaskController::class, 'create'])->name('tasks.create');
        Route::post('/tasks/store', [sTaskController::class, 'store'])->name('tasks.store');
        
        // Task actions
        Route::post('/workers/{identifier}/run/{action}', [sTaskActionController::class, 'run'])->name('workers.tasks.run');
        Route::get('/tasks/{id}/progress', [sTaskActionController::class, 'progress'])->name('tasks.progress');
        Route::get('/tasks/{id}/download', [sTaskActionController::class, 'download'])->name('tasks.download');
        Route::post('/tasks/{id}/upload', [sTaskActionController::class, 'upload'])->name('tasks.upload');
        
        // File upload for workers (without task ID)
        Route::post('/workers/{identifier}/upload', [sTaskActionController::class, 'uploadFile'])->name('workers.upload');
        
        // System operations
        Route::post('/clean', [sTaskController::class, 'clean'])->name('clean');
        Route::get('/server-limits', [sTaskActionController::class, 'serverLimits'])->name('serverLimits');
        
        // Workers management
        Route::get('/workers', [sTaskController::class, 'workers'])->name('workers.index');
        Route::get('/workers/{identifier}/settings', [sTaskController::class, 'workerSettings'])->name('workers.settings');
        Route::post('/workers/clean-orphaned', [sTaskController::class, 'cleanOrphanedWorkers'])->name('workers.clean');
        Route::post('/workers/activate', [sTaskController::class, 'activateWorker'])->name('workers.activate');
        Route::post('/workers/deactivate', [sTaskController::class, 'deactivateWorker'])->name('workers.deactivate');
        
        // Performance monitoring
        Route::get('/performance/summary', [sTaskController::class, 'getPerformanceSummary'])->name('performance.summary');
        Route::get('/performance/workers', [sTaskController::class, 'getWorkerStats'])->name('performance.workers');
        Route::get('/performance/alerts', [sTaskController::class, 'getPerformanceAlerts'])->name('performance.alerts');
        
        // Cache management
        Route::get('/cache/stats', [sTaskController::class, 'getCacheStats'])->name('cache.stats');
        Route::post('/cache/clear', [sTaskController::class, 'clearCache'])->name('cache.clear');
    });
});
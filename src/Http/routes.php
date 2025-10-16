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
        Route::post('/task', [sTaskController::class, 'create'])->name('task.create');
        Route::post('/task/store', [sTaskController::class, 'store'])->name('task.store');

        // Task actions
        Route::post('/worker/{identifier}/run/{action}', [sTaskActionController::class, 'run'])->name('worker.task.run');
        Route::get('/task/{id}/progress', [sTaskActionController::class, 'progress'])->name('task.progress');
        Route::get('/task/{id}/download', [sTaskActionController::class, 'download'])->name('task.download');
        Route::post('/task/{id}/upload', [sTaskActionController::class, 'upload'])->name('task.upload');

        // File upload for workers (without task ID)
        Route::post('/worker/{identifier}/upload', [sTaskActionController::class, 'uploadFile'])->name('worker.upload');

        // System operations
        Route::post('/clean', [sTaskController::class, 'clean'])->name('clean');
        Route::get('/server-limits', [sTaskActionController::class, 'serverLimits'])->name('serverLimits');

        // Workers management
        Route::get('/workers', [sTaskController::class, 'workers'])->name('workers');
        Route::get('/worker/{identifier}/settings', [sTaskController::class, 'workerSettings'])->name('worker.settings');
        Route::post('/worker/clean-orphaned', [sTaskController::class, 'cleanOrphanedWorkers'])->name('worker.clean');
        Route::post('/worker/activate', [sTaskController::class, 'activateWorker'])->name('worker.activate');
        Route::post('/worker/deactivate', [sTaskController::class, 'deactivateWorker'])->name('worker.deactivate');

        // Performance monitoring
        Route::get('/performance/summary', [sTaskController::class, 'getPerformanceSummary'])->name('performance.summary');
        Route::get('/performance/workers', [sTaskController::class, 'getWorkerStats'])->name('performance.workers');
        Route::get('/performance/alerts', [sTaskController::class, 'getPerformanceAlerts'])->name('performance.alerts');

        // Cache management
        Route::get('/cache/stats', [sTaskController::class, 'getCacheStats'])->name('cache.stats');
        Route::post('/cache/clear', [sTaskController::class, 'clearCache'])->name('cache.clear');
    });
});
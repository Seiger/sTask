<?php

use Illuminate\Support\Facades\Route;
use Seiger\sTask\Controllers\sTaskController;

// Manager routes only
Route::middleware(['mgr'])->group(function () {
    Route::prefix('stask')->name('sTask.')->group(function () {
        // Dashboard
        Route::get('/', [sTaskController::class, 'index'])->name('index');
        Route::get('/stats', [sTaskController::class, 'stats'])->name('stats');
        
        // Task management
        Route::post('/tasks', [sTaskController::class, 'create'])->name('tasks.create');
        Route::get('/tasks/{task}', [sTaskController::class, 'show'])->name('tasks.show');
        
        // System operations
        Route::post('/process', [sTaskController::class, 'process'])->name('process');
        Route::post('/clean', [sTaskController::class, 'clean'])->name('clean');
        
        // Workers management
        Route::get('/workers', [sTaskController::class, 'workers'])->name('workers.index');
        Route::post('/workers/discover', [sTaskController::class, 'discoverWorkers'])->name('workers.discover');
        Route::post('/workers/clean-orphaned', [sTaskController::class, 'cleanOrphanedWorkers'])->name('workers.clean');
        Route::post('/workers/activate', [sTaskController::class, 'activateWorker'])->name('workers.activate');
        Route::post('/workers/deactivate', [sTaskController::class, 'deactivateWorker'])->name('workers.deactivate');
    });
});
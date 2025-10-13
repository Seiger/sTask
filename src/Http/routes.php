<?php

use Illuminate\Support\Facades\Route;
use Seiger\sSeo\Controllers\sTaskController;

Route::middleware('mgr')->prefix('sseo/')->name('sSeo.')->group(function () {
    Route::get('dashboard', [sTaskController::class, 'dashboard'])->name('dashboard');
    Route::get('redirects', [sTaskController::class, 'redirects'])->name('redirects');
    Route::post('aredirect', [sTaskController::class, 'addRedirect'])->name('aredirect');
    Route::delete('dredirect', [sTaskController::class, 'delRedirect'])->name('dredirect');
    Route::get('templates', [sTaskController::class, 'templates'])->name('templates');
    Route::post('templates', [sTaskController::class, 'updateTemplates'])->name('utemplates');
    Route::get('robots', [sTaskController::class, 'robots'])->name('robots');
    Route::post('robots', [sTaskController::class, 'updateRobots'])->name('urobots');
    Route::get('configure', [sTaskController::class, 'configure'])->name('configure');
    Route::post('configure', [sTaskController::class, 'updateConfigure'])->name('uconfigure');
    Route::post('modulesave', [sTaskController::class, 'updateModuleFields'])->name('modulesave');
});

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: sTask tables creation.
 */
return new class extends Migration {
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | The workers table structure
        |--------------------------------------------------------------------------
        */
        Schema::create('s_workers', function (Blueprint $table) {
            $table->comment('Table that stores worker configurations for sTask system');
            $table->id('id')->comment('Primary key - auto-incrementing ID');
            $table->uuid('uuid')->unique()->nullable()->comment('UUID for external system integration');
            $table->string('identifier')->unique()->comment('Unique identifier for the worker (e.g., "product_sync", "sitemap_generation")');
            $table->string('class')->comment('Full PHP class name implementing the worker (e.g., "Seiger\\sCommerce\\Workers\\ProductSyncWorker")');
            $table->boolean('active')->default(false)->comment('Indicates if the worker is currently active and available for use');
            $table->integer('position')->unsigned()->default(0)->comment('Sorting order for display in administrative interface (lower numbers appear first)');
            $table->jsonb('settings')->default(new Expression('(JSON_ARRAY())'))->comment('JSON-encoded settings specific to this worker (configuration options, default parameters)');
            $table->integer('hidden')->unsigned()->default(0)->comment('Visibility flag: 0=visible, 1=hidden from all users, 2=hidden from non-admin users');
            $table->timestamps();

            $table->index('identifier')->comment('Index for worker identifier queries');
            $table->index('active')->comment('Index for active workers filtering');
            $table->index('position')->comment('Index for position-based ordering');
        });

        /*
        |--------------------------------------------------------------------------
        | The tasks table structure
        |--------------------------------------------------------------------------
        */
        Schema::create('s_tasks', function (Blueprint $table) {
            $table->comment('Table that stores asynchronous tasks for sTask system');
            $table->bigIncrements('id')->comment('Primary key - auto-incrementing task ID');
            $table->string('identifier')->comment('Worker identifier reference (matches s_workers.identifier)');
            $table->string('action')->comment('Specific action being performed (e.g., "import", "export", "sync")');
            $table->unsignedSmallInteger('status')->default(10)->comment('Task execution status: 10=pending, 20=running, 30=completed, 40=failed, 50=cancelled');
            $table->string('message', 255)->nullable()->comment('Current status message or error description');
            $table->unsignedInteger('started_by')->nullable()->comment('User ID who initiated the task');
            $table->longText('meta')->nullable()->comment('JSON-encoded task metadata and configuration');
            $table->longText('result')->nullable()->comment('Task result data (file paths, statistics, etc.)');
            $table->timestamp('start_at')->nullable()->comment('Scheduled start time for the task');
            $table->timestamp('finished_at')->nullable()->comment('Actual completion time of the task');
            $table->integer('attempts')->default(0)->comment('Number of execution attempts');
            $table->integer('max_attempts')->default(3)->comment('Maximum number of attempts before giving up');
            $table->string('priority')->default('normal')->comment('Task priority: low, normal, high');
            $table->integer('progress')->default(0)->comment('Task progress percentage (0-100)');
            $table->timestamps();

            $table->index(['identifier', 'action'])->comment('Composite index for worker-specific task queries');
            $table->index('status')->comment('Index for status-based filtering and monitoring');
            $table->index('started_by')->comment('Index for user-specific task queries');
            $table->index('start_at')->comment('Index for scheduled task processing');
            $table->index('created_at')->comment('Index for chronological task ordering');
            $table->index('priority')->comment('Index for priority-based ordering');
        });

    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Delete task tables structure
        |--------------------------------------------------------------------------
        */
        Schema::dropIfExists('s_tasks');
        Schema::dropIfExists('s_workers');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Expression;
use Seiger\sTask\Support\InstallerMigration;

/**
 * Create the queue tables while retaining workers and tasks already stored by Evolution.
 * Permissions and groups are handled by a separate migration.
 */
return new class extends Migration {
    /** Create each missing table independently so a partial installation can be repaired. */
    public function up(): void
    {
        if (!Schema::hasTable('s_workers')) {
            $this->createWorkersTable();
        }

        if (!Schema::hasTable('s_tasks')) {
            $this->createTasksTable();
        }
    }

    /** Drop Artisan-created queue tables while preserving the installer baseline. */
    public function down(): void
    {
        if (InstallerMigration::isBaseline(basename(__FILE__, '.php'))) {
            return;
        }

        Schema::dropIfExists('s_tasks');
        Schema::dropIfExists('s_workers');
    }

    /**
     * Define the original worker registry schema for a site where the table is missing.
     *
     * @since 2.3.2
     */
    private function createWorkersTable(): void
    {
        Schema::create('s_workers', function (Blueprint $table) {
            $table->comment('Workers for sTask');
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('identifier')->unique();
            $table->string('scope')->default('');
            $table->string('class');
            $table->boolean('active')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->json('settings')->default(new Expression('(JSON_ARRAY())'));
            $table->unsignedInteger('hidden')->default(0);
            $table->timestamps();

            $table->index('identifier');
            $table->index('scope');
            $table->index('active');
            $table->index('position');
        });
    }

    /**
     * Define the original task queue schema for a site where the table is missing.
     *
     * @since 2.3.2
     */
    private function createTasksTable(): void
    {
        Schema::create('s_tasks', function (Blueprint $table) {
            $table->comment('Async tasks for sTask');
            $table->bigIncrements('id');
            $table->string('identifier');
            $table->string('action');
            $table->unsignedSmallInteger('status')->default(10);
            $table->text('message')->nullable();
            $table->unsignedInteger('started_by')->nullable();
            $table->longText('meta')->nullable();
            $table->longText('result')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->string('priority')->default('normal');
            $table->integer('progress')->default(0);
            $table->timestamps();

            $table->index(['identifier', 'action']);
            $table->index('status');
            $table->index('started_by');
            $table->index('start_at');
            $table->index('created_at');
            $table->index('priority');
        });
    }
};

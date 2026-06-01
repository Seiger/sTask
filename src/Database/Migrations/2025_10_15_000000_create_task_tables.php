<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Expression;

/**
 * Migration: sTask tables creation.
 * Permissions and groups are handled by a separate migration.
 */
return new class extends Migration {
    public function up(): void
    {
        $this->createWorkersTable();
        $this->createTasksTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('s_tasks');
        Schema::dropIfExists('s_workers');
    }

    private function createWorkersTable(): void
    {
        /*
        |--------------------------------------------------------------------------
        | The workers table structure
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('s_workers')) {
            Schema::table('s_workers', function (Blueprint $table) {
                $this->addWorkersColumns($table, true);
            });

            return;
        }

        Schema::create('s_workers', function (Blueprint $table) {
            $table->comment('Workers for sTask');
            $table->id();
            $this->addWorkersColumns($table);

            $table->index('identifier');
            $table->index('scope');
            $table->index('active');
            $table->index('position');
        });
    }

    private function createTasksTable(): void
    {
        /*
        |--------------------------------------------------------------------------
        | The tasks table structure
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('s_tasks')) {
            Schema::table('s_tasks', function (Blueprint $table) {
                $this->addTasksColumns($table, true);
            });

            return;
        }

        Schema::create('s_tasks', function (Blueprint $table) {
            $table->comment('Async tasks for sTask');
            $table->bigIncrements('id');
            $this->addTasksColumns($table);

            $table->index(['identifier', 'action']);
            $table->index('status');
            $table->index('started_by');
            $table->index('start_at');
            $table->index('created_at');
            $table->index('priority');
        });
    }

    private function addWorkersColumns(Blueprint $table, bool $tableExists = false): void
    {
        if (!$tableExists || !Schema::hasColumn('s_workers', 'uuid')) {
            $table->uuid('uuid')->nullable()->unique();
        }

        if (!$tableExists || !Schema::hasColumn('s_workers', 'identifier')) {
            $table->string('identifier')->unique();
        }

        if (!$tableExists || !Schema::hasColumn('s_workers', 'scope')) {
            $table->string('scope')->default('');
        }

        if (!$tableExists || !Schema::hasColumn('s_workers', 'class')) {
            $table->string('class');
        }

        if (!$tableExists || !Schema::hasColumn('s_workers', 'active')) {
            $table->boolean('active')->default(false);
        }

        if (!$tableExists || !Schema::hasColumn('s_workers', 'position')) {
            $table->unsignedInteger('position')->default(0);
        }

        if (!$tableExists || !Schema::hasColumn('s_workers', 'settings')) {
            $table->json('settings')->default(new Expression('(JSON_ARRAY())'));
        }

        if (!$tableExists || !Schema::hasColumn('s_workers', 'hidden')) {
            $table->unsignedInteger('hidden')->default(0);
        }

        $this->addTimestampsIfMissing($table, 's_workers', $tableExists);
    }

    private function addTasksColumns(Blueprint $table, bool $tableExists = false): void
    {
        if (!$tableExists || !Schema::hasColumn('s_tasks', 'identifier')) {
            $table->string('identifier');
        }

        if (!$tableExists || !Schema::hasColumn('s_tasks', 'action')) {
            $table->string('action');
        }

        if (!$tableExists || !Schema::hasColumn('s_tasks', 'status')) {
            $table->unsignedSmallInteger('status')->default(10);
        }

        if (!$tableExists || !Schema::hasColumn('s_tasks', 'message')) {
            $table->text('message')->nullable();
        }

        if (!$tableExists || !Schema::hasColumn('s_tasks', 'started_by')) {
            $table->unsignedInteger('started_by')->nullable();
        }

        if (!$tableExists || !Schema::hasColumn('s_tasks', 'meta')) {
            $table->longText('meta')->nullable();
        }

        if (!$tableExists || !Schema::hasColumn('s_tasks', 'result')) {
            $table->longText('result')->nullable();
        }

        if (!$tableExists || !Schema::hasColumn('s_tasks', 'start_at')) {
            $table->timestamp('start_at')->nullable();
        }

        if (!$tableExists || !Schema::hasColumn('s_tasks', 'finished_at')) {
            $table->timestamp('finished_at')->nullable();
        }

        if (!$tableExists || !Schema::hasColumn('s_tasks', 'attempts')) {
            $table->integer('attempts')->default(0);
        }

        if (!$tableExists || !Schema::hasColumn('s_tasks', 'max_attempts')) {
            $table->integer('max_attempts')->default(3);
        }

        if (!$tableExists || !Schema::hasColumn('s_tasks', 'priority')) {
            $table->string('priority')->default('normal');
        }

        if (!$tableExists || !Schema::hasColumn('s_tasks', 'progress')) {
            $table->integer('progress')->default(0);
        }

        $this->addTimestampsIfMissing($table, 's_tasks', $tableExists);
    }

    private function addTimestampsIfMissing(Blueprint $table, string $tableName, bool $tableExists = false): void
    {
        if (!$tableExists || !Schema::hasColumn($tableName, 'created_at')) {
            $table->timestamp('created_at')->nullable();
        }

        if (!$tableExists || !Schema::hasColumn($tableName, 'updated_at')) {
            $table->timestamp('updated_at')->nullable();
        }
    }
};

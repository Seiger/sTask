<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use EvolutionCMS\Models\Permissions;
use EvolutionCMS\Models\PermissionsGroups;
use EvolutionCMS\Models\RolePermissions;

/**
 * Migration: sTask tables creation.
 */
return new class extends Migration {
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Create sTask permission group
        |--------------------------------------------------------------------------
        */
        // PostgreSQL-compatible: use raw SQL with ON CONFLICT
        if (DB::connection()->getDriverName() === 'pgsql') {
            // For PostgreSQL: INSERT ... ON CONFLICT DO UPDATE
            DB::statement("
                INSERT INTO " . (new PermissionsGroups())->getTable() . " (name, lang_key, created_at, updated_at) 
                VALUES ('sTask', 'sTask::global.permissions_group', NOW(), NOW())
                ON CONFLICT (name) DO UPDATE SET 
                    lang_key = EXCLUDED.lang_key,
                    updated_at = EXCLUDED.updated_at
            ");
            $staskGroup = PermissionsGroups::where('name', 'sTask')->first();
        } else {
            // For MySQL/MariaDB: use updateOrCreate
            $staskGroup = PermissionsGroups::updateOrCreate(
                ['name' => 'sTask'],
                ['lang_key' => 'sTask::global.permissions_group']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Create sTask permission
        |--------------------------------------------------------------------------
        */
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                INSERT INTO " . (new Permissions())->getTable() . " (name, key, lang_key, group_id, createdon, editedon) 
                VALUES ('Access sTask Interface', 'stask', 'sTask::global.permission_access', ?, ?, ?)
                ON CONFLICT (key) DO UPDATE SET 
                    name = EXCLUDED.name,
                    lang_key = EXCLUDED.lang_key,
                    group_id = EXCLUDED.group_id,
                    editedon = EXCLUDED.editedon
            ", [$staskGroup->id, time(), time()]);
        } else {
            Permissions::updateOrCreate(
                ['key' => 'stask'],
                [
                    'name' => 'Access sTask Interface',
                    'lang_key' => 'sTask::global.permission_access',
                    'group_id' => $staskGroup->id,
                    'createdon' => time(),
                    'editedon' => time(),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Create role permission
        |--------------------------------------------------------------------------
        */
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                INSERT INTO " . (new RolePermissions())->getTable() . " (role_id, permission) 
                VALUES (1, 'stask')
                ON CONFLICT (role_id, permission) DO NOTHING
            ");
        } else {
            RolePermissions::updateOrCreate([
                'role_id' => 1,
                'permission' => 'stask',
            ]);
        }

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
            $table->string('scope')->default('')->comment('Module/package scope this worker belongs to (e.g., "scommerce", "sarticles", "stask")');
            $table->string('class')->comment('Full PHP class name implementing the worker (e.g., "Seiger\\sCommerce\\Workers\\ProductSyncWorker")');
            $table->boolean('active')->default(false)->comment('Indicates if the worker is currently active and available for use');
            $table->integer('position')->unsigned()->default(0)->comment('Sorting order for display in administrative interface (lower numbers appear first)');
            
            // Cross-database compatible JSON column with proper defaults
            if (DB::connection()->getDriverName() === 'pgsql') {
                $table->jsonb('settings')->default('[]')->comment('JSON-encoded settings specific to this worker (configuration options, default parameters)');
            } else {
                $table->json('settings')->default('[]')->comment('JSON-encoded settings specific to this worker (configuration options, default parameters)');
            }
            
            $table->integer('hidden')->unsigned()->default(0)->comment('Visibility flag: 0=visible, 1=hidden from all users, 2=hidden from non-admin users');
            $table->timestamps();

            $table->index('identifier')->comment('Index for worker identifier queries');
            $table->index('scope')->comment('Index for scope-based filtering');
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
            $table->unsignedSmallInteger('status')->default(10)->comment('Task execution status: 10=queued, 30=preparing, 50=running, 80=finished, 100=failed');
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
        
        /*
        |--------------------------------------------------------------------------
        | Remove sTask permission
        |--------------------------------------------------------------------------
        */
        // Fixed: was 'stask_access', should be 'stask' to match up() method
        RolePermissions::where('permission', 'stask')->delete();
        Permissions::where('key', 'stask')->delete();
        PermissionsGroups::where('name', 'sTask')->delete();
    }
};

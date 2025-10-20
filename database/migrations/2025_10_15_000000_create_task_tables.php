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
        | Fix PostgreSQL sequence before migration starts
        |--------------------------------------------------------------------------
        */
        $this->fixPostgresSequences();

        /*
        |--------------------------------------------------------------------------
        | Create sTask permission group
        |--------------------------------------------------------------------------
        */
        // Use firstOrCreate to avoid duplicate key conflicts
        $staskGroup = PermissionsGroups::firstOrCreate(
            ['name' => 'sTask'],
            ['lang_key' => 'sTask::global.permissions_group']
        );
        
        // Update lang_key if group already existed
        if (!$staskGroup->wasRecentlyCreated) {
            $staskGroup->update(['lang_key' => 'sTask::global.permissions_group']);
        }

        /*
        |--------------------------------------------------------------------------
        | Create sTask permission
        |--------------------------------------------------------------------------
        */
        $permission = Permissions::firstOrCreate(
            ['key' => 'stask'],
            [
                'name' => 'Access sTask Interface',
                'lang_key' => 'sTask::global.permission_access',
                'group_id' => $staskGroup->id,
                'createdon' => time(),
                'editedon' => time(),
            ]
        );
        
        // Update if permission already existed
        if (!$permission->wasRecentlyCreated) {
            $permission->update([
                'name' => 'Access sTask Interface',
                'lang_key' => 'sTask::global.permission_access',
                'group_id' => $staskGroup->id,
                'editedon' => time(),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Create role permission
        |--------------------------------------------------------------------------
        */
        RolePermissions::firstOrCreate([
            'role_id' => 1,
            'permission' => 'stask',
        ]);

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
                $table->jsonb('settings')->default(DB::raw("'[]'::jsonb"))->comment('JSON-encoded settings specific to this worker (configuration options, default parameters)');
            } else {
                $table->json('settings')->nullable()->comment('JSON-encoded settings specific to this worker (configuration options, default parameters)');
            }

            $table->integer('hidden')->unsigned()->default(0)->comment('Visibility flag: 0=visible, 1=hidden from all users, 2=hidden from non-admin users');
            $table->timestamps();

            $table->index('identifier');
            $table->index('scope');
            $table->index('active');
            $table->index('position');
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

            $table->index(['identifier', 'action']);
            $table->index('status');
            $table->index('started_by');
            $table->index('start_at');
            $table->index('created_at');
            $table->index('priority');
        });
    }

    /**
     * Fix PostgreSQL sequences to prevent duplicate key violations
     * Handles both serial and identity column types correctly
     */
    private function fixPostgresSequences(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // List of tables that need id sequence synchronization
        $tables = [
            (new PermissionsGroups())->getTable(), // usually daisy_permissions_groups
            (new Permissions())->getTable(),       // usually daisy_permissions
            // Add others if they have PK=serial/identity
        ];

        foreach ($tables as $tableName) {
            // Work carefully with identifiers and handle both serial and identity
            $sql = "
            DO $$
            DECLARE
                seq_name text;
                max_id bigint;
                tbl regclass;
            BEGIN
                -- convert to regclass and avoid quoting issues
                SELECT to_regclass(%L)::regclass INTO tbl;

                IF tbl IS NULL THEN
                    RETURN; -- table doesn't exist yet
                END IF;

                -- try to find associated sequence for serial/identity
                SELECT pg_get_serial_sequence(%L, 'id') INTO seq_name;

                -- if sequence not found (identity may not have seq directly),
                -- try to get it through information views
                IF seq_name IS NULL THEN
                    SELECT
                        (quote_ident(n.nspname) || '.' || quote_ident(s.relname))::text
                    INTO seq_name
                    FROM pg_class c
                    JOIN pg_namespace n ON n.oid = c.relnamespace
                    JOIN pg_attribute a ON a.attrelid = c.oid AND a.attname = 'id'
                    JOIN pg_attrdef d ON d.adrelid = c.oid AND d.adnum = a.attnum
                    JOIN pg_class s ON s.relkind = 'S' AND d.adsrc LIKE '%' || s.relname || '%'
                    WHERE c.oid = tbl
                    LIMIT 1;
                END IF;

                -- if there's really no sequence — do nothing
                IF seq_name IS NULL THEN
                    RETURN;
                END IF;

                EXECUTE format('SELECT COALESCE(MAX(id),0) FROM %s', tbl) INTO max_id;

                -- Set setval: if there are records, next nextval will be max_id+1
                PERFORM setval(seq_name, max_id, max_id > 0);
            EXCEPTION WHEN OTHERS THEN
                -- safely ignore so migration doesn't fail on foreign DBs
                RETURN;
            END $$;
            ";

            DB::unprepared(sprintf($sql, $tableName, $tableName));
        }
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

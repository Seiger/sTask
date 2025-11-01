<?php

namespace Seiger\sTask\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder for sTask permissions.
 * Idempotent - safe to run multiple times.
 */
class STaskPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!Schema::hasTable('permissions_groups') || !Schema::hasTable('permissions')) {
            return;
        }

        $groupId = $this->getOrCreateGroup();
        $this->upsertPermissions($groupId);
        $this->assignPermissionsToAdmin();
    }

    /**
     * Get or create sTask permissions group.
     */
    protected function getOrCreateGroup(): int
    {
        // Try to find existing group
        $group = DB::table('permissions_groups')
            ->where('name', 'sTask')
            ->first();

        if ($group) {
            return $group->id;
        }

        // Try to insert new group
        try {
            return DB::table('permissions_groups')->insertGetId([
                'name' => 'sTask',
                'lang_key' => 'sTask',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            // If failed (duplicate key/sequence issue), fix sequence and retry
            $this->fixPostgresSequence('permissions_groups');
            
            // Try one more time
            try {
                return DB::table('permissions_groups')->insertGetId([
                    'name' => 'sTask',
                    'lang_key' => 'sTask',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e2) {
                // Final attempt - just get the record (might have been created by another process)
                $group = DB::table('permissions_groups')->where('name', 'sTask')->first();
                if ($group) {
                    return $group->id;
                }
                throw $e2;
            }
        }
    }

    /**
     * Upsert permissions for sTask.
     */
    protected function upsertPermissions(int $groupId): void
    {
        $permissions = [
            ['key' => 'stask_view', 'name' => 'View sTask', 'lang_key' => 'stask_view_permission'],
            ['key' => 'stask_manage', 'name' => 'Manage Tasks', 'lang_key' => 'stask_manage_permission'],
            ['key' => 'stask_workers', 'name' => 'Manage Workers', 'lang_key' => 'stask_workers_permission'],
        ];

        foreach ($permissions as $perm) {
            $exists = DB::table('permissions')->where('key', $perm['key'])->first();
            
            if ($exists) {
                DB::table('permissions')
                    ->where('key', $perm['key'])
                    ->update([
                        'name' => $perm['name'],
                        'lang_key' => $perm['lang_key'],
                        'group_id' => $groupId,
                        'updated_at' => now(),
                    ]);
            } else {
                try {
                    DB::table('permissions')->insert([
                        'key' => $perm['key'],
                        'name' => $perm['name'],
                        'lang_key' => $perm['lang_key'],
                        'group_id' => $groupId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (QueryException $e) {
                    // Already exists (race condition), try update
                    DB::table('permissions')
                        ->where('key', $perm['key'])
                        ->update([
                            'name' => $perm['name'],
                            'lang_key' => $perm['lang_key'],
                            'group_id' => $groupId,
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }

    /**
     * Assign sTask permissions to admin role (role_id = 1).
     */
    protected function assignPermissionsToAdmin(): void
    {
        if (!Schema::hasTable('role_permissions')) {
            return;
        }

        $permissionKeys = ['stask_view', 'stask_manage', 'stask_workers'];
        
        foreach ($permissionKeys as $key) {
            $permission = DB::table('permissions')->where('key', $key)->first();
            
            if (!$permission) {
                continue;
            }

            $exists = DB::table('role_permissions')
                ->where('role_id', 1)
                ->where('permission', $permission->id)
                ->exists();

            if (!$exists) {
                try {
                    DB::table('role_permissions')->insert([
                        'role_id' => 1,
                        'permission' => $permission->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (QueryException $e) {
                    // Already exists - ignore
                    continue;
                }
            }
        }
    }

    /**
     * Fix PostgreSQL sequence for a table.
     */
    protected function fixPostgresSequence(string $table): void
    {
        try {
            $fullTable = DB::getTablePrefix() . $table;
            $maxId = DB::table($table)->max('id') ?? 0;
            DB::statement("SELECT setval(pg_get_serial_sequence('{$fullTable}', 'id'), " . ($maxId + 1) . ", false)");
        } catch (\Exception $e) {
            // Silently ignore - might not be PostgreSQL
        }
    }
}

<?php

namespace Seiger\sTask\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder for sTask permissions.
 * Sets up the necessary permissions and groups for sTask functionality.
 */
class STaskPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Idempotent: Safe to run multiple times - uses updateOrInsert.
     * Works correctly with PostgreSQL sequences and existing data.
     *
     * @return void
     */
    public function run(): void
    {
        // Check if permissions tables exist
        if (!Schema::hasTable('permissions_groups') || !Schema::hasTable('permissions')) {
            return;
        }

        // Use createPermissions which now uses updateOrInsert (idempotent)
        // This works for both first-time and subsequent runs
        $this->createPermissions();
    }

    /**
     * Create permissions for the first time.
     * Uses upsert to avoid duplicate key violations.
     *
     * @return void
     */
    protected function createPermissions(): void
    {
        // Check if group already exists (avoid PostgreSQL sequence issues)
        $existingGroup = DB::table('permissions_groups')
            ->where('name', 'sTask')
            ->first();

        if ($existingGroup) {
            // Update existing group
            DB::table('permissions_groups')
                ->where('id', $existingGroup->id)
                ->update([
                    'lang_key' => 'sTask',
                    'updated_at' => now(),
                ]);
            $groupId = $existingGroup->id;
        } else {
            // Insert new group - wrap in try-catch for PostgreSQL sequence issues
            try {
                $groupId = DB::table('permissions_groups')->insertGetId([
                    'name' => 'sTask',
                    'lang_key' => 'sTask',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                // If insert fails due to sequence, try to get existing record
                $existingGroup = DB::table('permissions_groups')
                    ->where('name', 'sTask')
                    ->first();
                
                if ($existingGroup) {
                    $groupId = $existingGroup->id;
                } else {
                    // Re-throw if it's not a duplicate key error
                    throw $e;
                }
            }
        }

        // Define permissions for sTask
        $permissions = [
            [
                'name' => 'View sTask',
                'key' => 'stask_view',
                'lang_key' => 'stask_view_permission',
                'group_id' => $groupId,
            ],
            [
                'name' => 'Manage Tasks',
                'key' => 'stask_manage',
                'lang_key' => 'stask_manage_permission',
                'group_id' => $groupId,
            ],
            [
                'name' => 'Manage Workers',
                'key' => 'stask_workers',
                'lang_key' => 'stask_workers_permission',
                'group_id' => $groupId,
            ],
        ];

        // Insert permissions with explicit check to avoid duplicates
        foreach ($permissions as $permission) {
            $exists = DB::table('permissions')
                ->where('key', $permission['key'])
                ->exists();

            if (!$exists) {
                try {
                    DB::table('permissions')->insert(array_merge($permission, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                } catch (\Exception $e) {
                    // Silently skip if permission already exists (race condition)
                    continue;
                }
            } else {
                // Update existing permission
                DB::table('permissions')
                    ->where('key', $permission['key'])
                    ->update([
                        'name' => $permission['name'],
                        'lang_key' => $permission['lang_key'],
                        'group_id' => $permission['group_id'],
                        'updated_at' => now(),
                    ]);
            }
        }
    }
}


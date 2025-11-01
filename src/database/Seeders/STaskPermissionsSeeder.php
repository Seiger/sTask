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
     * Idempotent: Safe to run multiple times - checks before insert.
     *
     * @return void
     */
    public function run(): void
    {
        // Check if permissions tables exist
        if (!Schema::hasTable('permissions_groups') || !Schema::hasTable('permissions')) {
            return;
        }

        // Check if already seeded
        $groupExists = DB::table('permissions_groups')
            ->where('name', 'sTask')
            ->exists();

        if ($groupExists) {
            // Already seeded - update/add missing permissions if needed
            $this->updatePermissions();
            return;
        }

        // First time seeding - create everything
        $this->createPermissions();
    }

    /**
     * Create permissions for the first time.
     *
     * @return void
     */
    protected function createPermissions(): void
    {
        // Insert permissions group
        $groupId = DB::table('permissions_groups')->insertGetId([
            'name' => 'sTask',
            'lang_key' => 'sTask',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        // Insert all permissions
        foreach ($permissions as $permission) {
            DB::table('permissions')->insert(array_merge($permission, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Update existing permissions - add missing ones if needed.
     *
     * @return void
     */
    protected function updatePermissions(): void
    {
        // Get group ID
        $groupId = DB::table('permissions_groups')
            ->where('name', 'sTask')
            ->value('id');

        if (!$groupId) {
            return;
        }

        // Get existing permission keys
        $existingKeys = DB::table('permissions')
            ->where('group_id', $groupId)
            ->pluck('key')
            ->toArray();

        // Define required permissions
        $requiredPermissions = [
            'stask_view' => 'View sTask',
            'stask_manage' => 'Manage Tasks',
            'stask_workers' => 'Manage Workers',
        ];

        // Add missing permissions
        foreach ($requiredPermissions as $key => $name) {
            if (!in_array($key, $existingKeys)) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'key' => $key,
                    'lang_key' => $key . '_permission',
                    'group_id' => $groupId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}


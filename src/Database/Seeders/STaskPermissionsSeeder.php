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
        // Use updateOrInsert to avoid duplicate key errors with PostgreSQL sequences
        DB::table('permissions_groups')->updateOrInsert(
            ['name' => 'sTask'], // Match condition
            [
                'lang_key' => 'sTask',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Get the group ID
        $groupId = DB::table('permissions_groups')
            ->where('name', 'sTask')
            ->value('id');

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

        // Insert permissions using updateOrInsert to avoid duplicates
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']], // Match by key
                array_merge($permission, [
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ])
            );
        }
    }
}


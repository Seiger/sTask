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
     * @return void
     */
    public function run(): void
    {
        // Check if permissions tables exist
        if (!Schema::hasTable('permissions_groups') || !Schema::hasTable('permissions')) {
            return;
        }

        // Get or create sTask permissions group
        $groupId = DB::table('permissions_groups')
            ->where('name', 'sTask')
            ->value('id');

        if (!$groupId) {
            $groupId = DB::table('permissions_groups')->insertGetId([
                'name' => 'sTask',
                'lang_key' => 'sTask',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Define permissions for sTask
        $permissions = [
            [
                'name' => 'stask_view',
                'key' => 'stask_view',
                'lang_key' => 'stask_view_permission',
                'group_id' => $groupId,
            ],
            [
                'name' => 'stask_manage',
                'key' => 'stask_manage',
                'lang_key' => 'stask_manage_permission',
                'group_id' => $groupId,
            ],
            [
                'name' => 'stask_workers',
                'key' => 'stask_workers',
                'lang_key' => 'stask_workers_permission',
                'group_id' => $groupId,
            ],
        ];

        // Insert permissions if they don't exist
        foreach ($permissions as $permission) {
            $exists = DB::table('permissions')
                ->where('key', $permission['key'])
                ->exists();

            if (!$exists) {
                DB::table('permissions')->insert(array_merge($permission, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }
}


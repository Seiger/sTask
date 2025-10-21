<?php

namespace Seiger\sTask\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder for sTask permissions and groups.
 * Safely handles existing records using updateOrInsert.
 */
class STaskPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create or update permission group
        try {
            DB::table('permissions_groups')->insert([
                'name' => 'sTask',
                'lang_key' => 'sTask::global.permissions_group',
            ]);
        } catch (\Exception $e) {
            // If already exists, update it
            DB::table('permissions_groups')
                ->where('name', 'sTask')
                ->update(['lang_key' => 'sTask::global.permissions_group']);
        }

        // Get group ID
        $groupId = DB::table('permissions_groups')
            ->where('name', 'sTask')
            ->value('id');

        // Create or update permission
        try {
            DB::table('permissions')->insert([
                'name' => 'Access sTask Interface',
                'key' => 'stask',
                'lang_key' => 'sTask::global.permission_access',
                'group_id' => $groupId,
                'disabled' => 0,
            ]);
        } catch (\Exception $e) {
            // If already exists, update it
            DB::table('permissions')
                ->where('key', 'stask')
                ->update([
                    'name' => 'Access sTask Interface',
                    'lang_key' => 'sTask::global.permission_access',
                    'group_id' => $groupId,
                    'disabled' => 0,
                ]);
        }

        // Create role permission binding if not exists
        try {
            DB::table('role_permissions')->insert([
                'role_id' => 1,
                'permission' => 'stask',
            ]);
        } catch (\Exception $e) {
            // Already exists, skip
        }
    }
}


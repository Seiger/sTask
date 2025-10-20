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
        DB::table('daisy_permissions_groups')->updateOrInsert(
            ['name' => 'sTask'],
            ['lang_key' => 'sTask::global.permissions_group']
        );

        // Get group ID
        $groupId = DB::table('daisy_permissions_groups')
            ->where('name', 'sTask')
            ->value('id');

        // Create or update permission
        DB::table('daisy_permissions')->updateOrInsert(
            ['key' => 'stask'],
            [
                'name' => 'Access sTask Interface',
                'lang_key' => 'sTask::global.permission_access',
                'group_id' => $groupId,
                'createdon' => time(),
                'editedon' => time(),
            ]
        );

        // Bind permission to admin role
        DB::table('daisy_role_permissions')->updateOrInsert(
            ['role_id' => 1, 'permission' => 'stask'],
            []
        );
    }
}


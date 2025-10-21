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
        // Check if permission group exists
        $group = DB::table('permissions_groups')
            ->where('name', 'sTask')
            ->first();

        if (!$group) {
            // Create new permission group
            DB::table('permissions_groups')->insert([
                'name' => 'sTask',
                'lang_key' => 'sTask::global.permissions_group',
            ]);
            $groupId = DB::getPdo()->lastInsertId();
        } else {
            $groupId = $group->id;
            // Update existing group
            DB::table('permissions_groups')
                ->where('id', $groupId)
                ->update([
                    'lang_key' => 'sTask::global.permissions_group',
                ]);
        }

        // Check if permission exists
        $permission = DB::table('permissions')
            ->where('key', 'stask')
            ->first();

        if (!$permission) {
            // Create new permission
            DB::table('permissions')->insert([
                'name' => 'Access sTask Interface',
                'key' => 'stask',
                'lang_key' => 'sTask::global.permission_access',
                'group_id' => $groupId,
                'disabled' => 0,
            ]);
        } else {
            // Update existing permission
            DB::table('permissions')
                ->where('key', 'stask')
                ->update([
                    'name' => 'Access sTask Interface',
                    'lang_key' => 'sTask::global.permission_access',
                    'group_id' => $groupId,
                    'disabled' => 0,
                ]);
        }

        // Check if role permission binding exists
        $rolePermission = DB::table('role_permissions')
            ->where('role_id', 1)
            ->where('permission', 'stask')
            ->first();

        if (!$rolePermission) {
            // Create binding between admin role and stask permission
            DB::table('role_permissions')->insert([
                'role_id' => 1,
                'permission' => 'stask',
            ]);
        }
    }
}


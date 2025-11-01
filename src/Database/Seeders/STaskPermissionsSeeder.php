<?php

namespace Seiger\sTask\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder for sTask permissions and groups.
 * Safely handles existing records using updateOrInsert.
 */
class STaskPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if permissions_groups table exists
        if (!Schema::hasTable('permissions_groups')) {
            echo "⚠️  Permissions tables don't exist yet. Skipping seeder.\n";
            return;
        }

        try {
            // Create or update permission group
            $existingGroup = DB::table('permissions_groups')
                ->where('name', 'sTask')
                ->first();

            if (!$existingGroup) {
                DB::table('permissions_groups')->insert([
                    'name' => 'sTask',
                    'lang_key' => 'sTask::global.permissions_group',
                ]);
            } else {
                DB::table('permissions_groups')
                    ->where('name', 'sTask')
                    ->update(['lang_key' => 'sTask::global.permissions_group']);
            }

            // Get group ID
            $groupId = DB::table('permissions_groups')
                ->where('name', 'sTask')
                ->value('id');

            // Create or update permission
            $existingPermission = DB::table('permissions')
                ->where('key', 'stask')
                ->first();

            if (!$existingPermission) {
                DB::table('permissions')->insert([
                    'name' => 'Access sTask Interface',
                    'key' => 'stask',
                    'lang_key' => 'sTask::global.permission_access',
                    'group_id' => $groupId,
                    'disabled' => 0,
                ]);
            } else {
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
            $existingRolePermission = DB::table('role_permissions')
                ->where('role_id', 1)
                ->where('permission', 'stask')
                ->first();

            if (!$existingRolePermission) {
                DB::table('role_permissions')->insert([
                    'role_id' => 1,
                    'permission' => 'stask',
                ]);
            }

            echo "✅ sTask permissions seeded successfully!\n";
        } catch (\Exception $e) {
            echo "❌ Error seeding sTask permissions: " . $e->getMessage() . "\n";
        }
    }
}


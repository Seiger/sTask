<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: sTask permissions and admin assignment.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('permissions_groups') || !Schema::hasTable('permissions')) {
            return;
        }

        $groupId = $this->getOrCreateGroup();
        $this->upsertPermission($groupId);
        $this->assignPermissionToAdmin();
    }

    public function down(): void
    {
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')
                ->where('role_id', 1)
                ->where('permission', 'stask')
                ->delete();
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('key', 'stask')
                ->delete();
        }

        if (Schema::hasTable('permissions_groups')) {
            $group = DB::table('permissions_groups')->where('name', 'sTask')->first();

            if ($group) {
                $hasPermissions = Schema::hasTable('permissions')
                    && DB::table('permissions')->where('group_id', $group->id)->exists();

                if (!$hasPermissions) {
                    DB::table('permissions_groups')->where('id', $group->id)->delete();
                }
            }
        }
    }

    protected function getOrCreateGroup(): int
    {
        $group = DB::table('permissions_groups')
            ->where('name', 'sTask')
            ->first();

        if ($group) {
            return $group->id;
        }

        try {
            return DB::table('permissions_groups')->insertGetId([
                'name' => 'sTask',
                'lang_key' => 'sTask::global.permissions_group',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            $this->fixPostgresSequence('permissions_groups');

            try {
                return DB::table('permissions_groups')->insertGetId([
                    'name' => 'sTask',
                    'lang_key' => 'sTask::global.permissions_group',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e2) {
                $group = DB::table('permissions_groups')->where('name', 'sTask')->first();
                if ($group) {
                    return $group->id;
                }
                throw $e2;
            }
        }
    }

    protected function upsertPermission(int $groupId): void
    {
        $exists = DB::table('permissions')->where('key', 'stask')->first();

        if ($exists) {
            DB::table('permissions')
                ->where('key', 'stask')
                ->update([
                    'name' => 'Access sTask Interface',
                    'lang_key' => 'sTask::global.permission_access',
                    'group_id' => $groupId,
                    'disabled' => 0,
                    'updated_at' => now(),
                ]);
        } else {
            try {
                DB::table('permissions')->insert([
                    'key' => 'stask',
                    'name' => 'Access sTask Interface',
                    'lang_key' => 'sTask::global.permission_access',
                    'group_id' => $groupId,
                    'disabled' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e) {
                DB::table('permissions')
                    ->where('key', 'stask')
                    ->update([
                        'name' => 'Access sTask Interface',
                        'lang_key' => 'sTask::global.permission_access',
                        'group_id' => $groupId,
                        'disabled' => 0,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    protected function assignPermissionToAdmin(): void
    {
        if (!Schema::hasTable('role_permissions')) {
            return;
        }

        $permission = DB::table('permissions')->where('key', 'stask')->first();

        if (!$permission) {
            return;
        }

        $exists = DB::table('role_permissions')
            ->where('role_id', 1)
            ->where('permission', 'stask')
            ->exists();

        if (!$exists) {
            try {
                DB::table('role_permissions')->insert([
                    'role_id' => 1,
                    'permission' => 'stask',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e) {
                // Already exists - ignore.
            }
        }
    }

    protected function fixPostgresSequence(string $table): void
    {
        try {
            $fullTable = DB::getTablePrefix() . $table;
            $maxId = DB::table($table)->max('id') ?? 0;
            DB::statement("SELECT setval(pg_get_serial_sequence('{$fullTable}', 'id'), " . ($maxId + 1) . ", false)");
        } catch (\Exception $e) {
            // Ignore if not PostgreSQL or permissions are missing.
        }
    }
};

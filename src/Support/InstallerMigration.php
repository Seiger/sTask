<?php namespace Seiger\sTask\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recognize migrations that already belong to the Evolution installer history.
 *
 * @since 2.3.2
 */
final class InstallerMigration
{
    /**
     * Check whether an Artisan rollback should preserve the installer's baseline.
     *
     * @param string $migration Migration filename without the PHP extension.
     * @return bool Whether the installer recorded the migration.
     * @since 2.3.2
     */
    public static function isBaseline(string $migration): bool
    {
        return Schema::hasTable('migrations_install') && DB::table('migrations_install')->where('migration', $migration)->exists();
    }
}

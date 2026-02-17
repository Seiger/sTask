<?php namespace Seiger\sTask\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Composer\InstalledVersions;

/**
 * Class PublishAssets
 *
 * Prunes outdated published assets and republishes package files.
 * - Deletes specific target files before publish
 * - Calls vendor:publish for this provider
 * - Updates version in config file
 *
 * @package Seiger\sTask
 * @author Seiger IT Team
 * @since 1.0.0
 */
class PublishAssets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stask:publish {--no-prune : Do not delete existing files before publish}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish sTask assets (with optional prune).';

    /**
     * Execute the console command.
     *
     * @param Filesystem $fs
     * @return int
     */
    public function handle(Filesystem $fs): int
    {
        // 1) Targets to delete before publishing
        $targets = [
            public_path('assets/site/stask.min.css'),
            public_path('assets/site/stask.js'),
            public_path('assets/site/stask.svg'),
            public_path('assets/site/seigerit.tooltip.js'),
        ];

        if (!$this->option('no-prune')) {
            foreach ($targets as $path) {
                $fs->delete($path);
            }
        }

        // 2) Publish (force overwrite)
        $this->call('vendor:publish', [
            '--provider' => 'Seiger\sTask\sTaskServiceProvider',
            '--tag' => 'stask',
            '--force' => true,
        ]);

        // 3) Update version in config file
        try {
            $ver = InstalledVersions::getVersion('seiger/stask');
            $configPath = dirname(__DIR__, 2) . '/config/sTaskCheck.php';
            $fs->put(
                $configPath,
                "<?php\n\nreturn [\n    'check_sTask' => true,\n    'sTaskVer' => '" . $ver . "',\n];\n"
            );
            $this->info("Version updated to: {$ver}");
        } catch (\Throwable $e) {
            $this->warn('Could not detect package version: ' . $e->getMessage());
        }

        $this->info('sTask assets published successfully!');
        return self::SUCCESS;
    }
}

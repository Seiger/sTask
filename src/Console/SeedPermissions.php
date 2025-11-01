<?php

namespace Seiger\sTask\Console;

use Illuminate\Console\Command;
use Seiger\sTask\Database\Seeders\STaskPermissionsSeeder;

/**
 * Command to manually seed sTask permissions.
 * 
 * This command can be run manually if automatic seeding fails:
 * php artisan stask:seed-permissions
 * 
 * @package Seiger\sTask\Console
 */
class SeedPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stask:seed-permissions
                          {--force : Force the operation to run in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed sTask permissions and permission groups';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        // Check if permissions table exists
        if (!\Illuminate\Support\Facades\Schema::hasTable('permissions_groups')) {
            $this->error('Permissions tables do not exist. Please run migrations first.');
            $this->info('Run: php artisan migrate');
            return Command::FAILURE;
        }

        // Confirm in production
        if ($this->getLaravel()->environment('production') && !$this->option('force')) {
            if (!$this->confirm('Do you really want to run this seeder in production?')) {
                $this->info('Seeding cancelled.');
                return Command::SUCCESS;
            }
        }

        $this->info('Seeding sTask permissions...');

        try {
            $seeder = new STaskPermissionsSeeder();
            $seeder->run();
            
            $this->info('✅ sTask permissions seeded successfully!');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to seed permissions: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            
            return Command::FAILURE;
        }
    }
}


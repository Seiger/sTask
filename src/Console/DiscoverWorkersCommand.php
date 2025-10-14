<?php namespace Seiger\sTask\Console;

use Illuminate\Console\Command;
use Seiger\sTask\Facades\sTask;

/**
 * Command to discover and register new workers
 *
 * @package Seiger\sTask\Console
 * @author Seiger IT Team
 * @since 1.0.0
 */
class DiscoverWorkersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stask:discover-workers 
                            {--rescan : Re-scan existing workers}
                            {--clean : Clean orphaned workers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover and register new task workers';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting worker discovery...');

        // Discover new workers
        $registered = sTask::discoverWorkers();

        if (!empty($registered)) {
            $this->info("Discovered and registered " . count($registered) . " new workers:");
            foreach ($registered as $worker) {
                $this->line("  - {$worker->slug} ({$worker->class})");
            }
        } else {
            $this->comment('No new workers found.');
        }

        // Re-scan existing workers if requested
        if ($this->option('rescan')) {
            $this->info('Re-scanning existing workers...');
            $updated = sTask::rescanWorkers();

            if (!empty($updated)) {
                $this->info("Updated " . count($updated) . " workers:");
                foreach ($updated as $worker) {
                    $this->line("  - {$worker->slug} ({$worker->class})");
                }
            } else {
                $this->comment('No workers needed updates.');
            }
        }

        // Clean orphaned workers if requested
        if ($this->option('clean')) {
            $this->info('Cleaning orphaned workers...');
            $deleted = sTask::cleanOrphanedWorkers();

            if ($deleted > 0) {
                $this->info("Removed {$deleted} orphaned workers.");
            } else {
                $this->comment('No orphaned workers found.');
            }
        }

        $this->newLine();
        $this->info('Worker discovery completed successfully!');

        return Command::SUCCESS;
    }
}

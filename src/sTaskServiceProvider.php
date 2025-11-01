<?php namespace Seiger\sTask;

use EvolutionCMS\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Seiger\sTask\Console\PublishAssets;
use Seiger\sTask\Console\TaskWorker;

/**
 * Class sTaskServiceProvider
 *
 * Service provider for sTask package. Handles registration,
 * publishing resources, and managing task functionality.
 *
 * @package Seiger\sTask
 * @author Seiger IT Team
 * @since 1.0.0
 */
class sTaskServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Merge configuration first
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/sTaskCheck.php', 'cms.settings');

        // Register singletons
        $this->app->singleton(sTask::class);
        $this->app->alias(sTask::class, 'sTask');
        
        // Create storage directory for logs
        $this->ensureStorageExists();
        
        // Load migrations, translations, views
        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');
        $this->loadTranslationsFrom(dirname(__DIR__) . '/lang', 'sTask');
        $this->loadViewsFrom(dirname(__DIR__) . '/views', 'sTask');
        
        // Run seeder after migrations to setup permissions
        $this->runPermissionsSeeder();
        
        // Load routes
        $this->loadRoutes();
        
        // Publish resources
        $this->publishResources();

        // Setup console schedule for commands
        $this->app->booted(function () {
            $this->defineConsoleSchedule();
        });
    }

    /**
     * Ensure storage directory exists
     */
    protected function ensureStorageExists(): void
    {
        $logPath = storage_path('stask');
        
        if (!file_exists($logPath)) {
            mkdir($logPath, 0755, true);
        }
    }

    /**
     * Run permissions seeder after migrations.
     * Uses afterMigrating event to ensure tables exist.
     */
    protected function runPermissionsSeeder(): void
    {
        $this->app->make('events')->listen('Illuminate\Database\Events\MigrationsEnded', function() {
            // Run seeder only if permissions table exists
            if (\Illuminate\Support\Facades\Schema::hasTable('permissions_groups')) {
                (new \Seiger\sTask\Database\Seeders\STaskPermissionsSeeder())->run();
            }
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Register services
        $this->registerServices();
        
        // Load plugins
        $this->loadPluginsFrom(dirname(__DIR__) . '/plugins/');

        // Register console commands
        $this->commands([
            TaskWorker::class,
            PublishAssets::class,
        ]);
    }

    /**
     * Register sTask services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Register WorkerService as singleton for performance
        $this->app->singleton(\Seiger\sTask\Services\WorkerService::class);
        
        // Register MetricsService as singleton
        $this->app->singleton(\Seiger\sTask\Services\MetricsService::class);
        
        // Register sTask as singleton
        $this->app->singleton(\Seiger\sTask\sTask::class);
    }

    /**
     * Load custom routes
     *
     * @return void
     */
    protected function loadRoutes()
    {
        if (file_exists(__DIR__ . '/Http/routes.php')) {
            $this->app->router->middlewareGroup('mgr', config('app.middleware.mgr', []));
            include(__DIR__ . '/Http/routes.php');
        }
    }

    /**
     * Publish the necessary resources for the package.
     *
     * @return void
     */
    protected function publishResources()
    {
        $this->publishes([
            dirname(__DIR__) . '/config/sTaskAlias.php' => config_path('app/aliases/sTask.php', true),
            dirname(__DIR__) . '/images/seigerit.svg' => public_path('assets/site/seigerit.svg'),
            dirname(__DIR__) . '/images/logo.svg' => public_path('assets/site/stask.svg'),
            dirname(__DIR__) . '/css/tailwind.min.css' => public_path('assets/site/stask.min.css'),
            dirname(__DIR__) . '/js/main.js' => public_path('assets/site/stask.js'),
            dirname(__DIR__) . '/js/tooltip.js' => public_path('assets/site/seigerit.tooltip.js'),
        ], 'stask');
    }

    /**
     * Define the application's command schedule.
     *
     * Sets up the Laravel scheduler singleton with timezone support.
     * This enables scheduled execution of console commands.
     *
     * @note Check available timezones using timezone_identifiers_list()
     * @return void
     */
    protected function defineConsoleSchedule()
    {
        $this->app->singleton(Schedule::class, function ($app) {
            return tap(new Schedule(now()->timezoneName), function ($schedule) {
                $this->schedule($schedule->useCache('file'));
            });
        });
    }

    /**
     * Define the application's command schedule.
     *
     * Iterates through all registered commands and calls their schedule
     * method to define when each command should be executed.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule)
    {
        // Schedule commands if they have a schedule method
        $commands = [TaskWorker::class];
        
        foreach ($commands as $command) {
            $instance = new $command;
            if (method_exists($instance, 'schedule')) {
                $instance->schedule($schedule);
            }
        }
    }
}
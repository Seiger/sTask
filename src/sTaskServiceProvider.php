<?php namespace Seiger\sTask;

use EvolutionCMS\ServiceProvider;

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
        // Load migrations, translations, views
        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');
        $this->loadTranslationsFrom(dirname(__DIR__) . '/lang', 'sTask');
        $this->loadViewsFrom(dirname(__DIR__) . '/views', 'sTask');
        
        // Load routes
        $this->loadRoutes();
        
        // Publish resources
        $this->publishResources();
        
        // Merge configuration
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/sTaskCheck.php', 'cms.settings');

        // Register sTask as singleton
        $this->app->singleton(sTask::class);
        $this->app->alias(sTask::class, 'sTask');
        
        // Register TaskLogger as singleton
        $this->app->singleton(\Seiger\sTask\Services\TaskLogger::class);
        
        // Create storage directory for logs
        $this->ensureStorageExists();
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
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Seiger\sTask\Console\DiscoverWorkersCommand::class,
            ]);
        }
    }

    /**
     * Load custom routes
     *
     * @return void
     */
    protected function loadRoutes()
    {
        $this->app->router->middlewareGroup('mgr', config('app.middleware.mgr', []));
        
        if (file_exists(__DIR__ . '/Http/routes.php')) {
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
        ], 'stask-assets');
    }
}
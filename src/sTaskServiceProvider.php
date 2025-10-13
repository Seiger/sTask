<?php namespace Seiger\sTask;

use EvolutionCMS\ServiceProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * Class sSeoServiceProvider
 *
 * Service provider for sSeo package. Handles registration,
 * publishing resources, and managing subscriptions for PRO features.
 */
class sTaskServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * Loads migrations, translations, views, and custom routes.
     * Optional checks for PRO subscription to enable additional features.
     *
     * @return void
     */
    public function boot()
    {
        // Add custom routes for package
        $this->app->router->middlewareGroup('mgr', config('app.middleware.mgr', []));
        include(__DIR__.'/Http/routes.php');

        // Load migrations, translations, views
        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');
        $this->loadTranslationsFrom(dirname(__DIR__) . '/lang', 'sTask');
        $this->publishResources();
        $this->loadViewsFrom(dirname(__DIR__) . '/views', 'sTask');
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/sTaskCheck.php', 'cms.settings');

        $this->app->singleton(\Seiger\sTask\sTask::class);
        $this->app->alias(\Seiger\sTask\sTask::class, 'sTask');
    }

    /**
     * Publish the necessary resources for the package.
     *
     * This includes configuration files, images, and view templates.
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
        ]);
    }
}

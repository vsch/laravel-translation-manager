<?php namespace Vsch\TranslationManager;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class ManagerServiceProvider extends ServiceProvider
{
    const PACKAGE = 'laravel-translation-manager';

    // Laravel 5
    const CONTROLLER_PREFIX = '\\';
    const PUBLIC_PREFIX = '/vendor/';

    public static function getLists($query)
    {
        return $query->all();
    }

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Register the config publish path
        $configPath = __DIR__ . '/../config/' . self::PACKAGE . '.php';
        $this->mergeConfigFrom($configPath, self::PACKAGE);
        $this->publishes([$configPath => config_path(self::PACKAGE . '.php')], 'config');

        $this->app->singleton(self::PACKAGE, function ($app) {
            /* @var $manager \Vsch\TranslationManager\Manager */
            $manager = $app->make('Vsch\TranslationManager\Manager');
            return $manager;
        });

        $this->app->singleton('command.translation-manager.reset', function ($app) {
            return new Console\ResetCommand($app[self::PACKAGE]);
        });
        $this->commands('command.translation-manager.reset');

        $this->app->singleton('command.translation-manager.import', function ($app) {
            return new Console\ImportCommand($app[self::PACKAGE]);
        });
        $this->commands('command.translation-manager.import');

        $this->app->singleton('command.translation-manager.find', function ($app) {
            return new Console\FindCommand($app[self::PACKAGE]);
        });
        $this->commands('command.translation-manager.find');

        $this->app->singleton('command.translation-manager.export', function ($app) {
            return new Console\ExportCommand($app[self::PACKAGE]);
        });
        $this->commands('command.translation-manager.export');

        $this->app->singleton('command.translation-manager.clean', function ($app) {
            return new Console\CleanCommand($app[self::PACKAGE]);
        });
        $this->commands('command.translation-manager.clean');
    }

    /**
     * Bootstrap the application events.
     *
     * @param  \Illuminate\Routing\Router $router
     *
     * @return void
     */
    public function boot(Router $router)
    {
        $resources = __DIR__ . '/../resources/';
        $this->loadViewsFrom($resources . 'views', self::PACKAGE);
        $this->loadTranslationsFrom($resources . 'lang', self::PACKAGE);

        $this->publishes([
            $resources . 'views' => base_path('resources/views/vendor/' . self::PACKAGE),
        ], 'views');

        $this->publishes([
            $resources . 'lang' => base_path('resources/lang/vendor/' . self::PACKAGE),
        ], 'lang');

        $this->publishes([
            __DIR__ . '/../public' => public_path('vendor/' . self::PACKAGE),
        ], 'public');

        $migrationPath = __DIR__ . '/../database/migrations';
        $this->publishes([
            $migrationPath => base_path('database/migrations'),
        ], 'migrations');

        $config = $this->app['config']->get(self::PACKAGE . '.route', []);
        $config['namespace'] = 'Vsch\TranslationManager';

        //$router->group($config, function ($router) {
        //    $router->get('view/{group}', 'Controller@getView');
        //    $router->controller('/', 'Controller');
        //});

        // Register Middleware so we can save our cached translations
        $router->pushMiddlewareToGroup('web', 'Vsch\TranslationManager\RouteAfterMiddleware');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            self::PACKAGE,
            'translator',
            'translation.loader',
            'command.translation-manager.reset',
            'command.translation-manager.import',
            'command.translation-manager.find',
            'command.translation-manager.export',
            'command.translation-manager.clean'
        );
    }
}

<?php namespace Vsch\TranslationManager;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class ManagerServiceProvider extends ServiceProvider
{
    const PACKAGE = 'laravel-translation-manager';

    // Laravel 4
    const CONTROLLER_PREFIX = '';
    const PUBLIC_PREFIX = '/packages/vsch/';

    public static function getLists($query) { return $query; }

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;


    /**
     * Register the service provider.
     *
     * @return void
     */
    public
    function register()
    {
        $this->app[self::PACKAGE] = $this->app->share(function ($app)
        {
            /* @var $manager \Vsch\TranslationManager\Manager */
            $manager = $app->make('Vsch\TranslationManager\Manager');
            return $manager;
        });

        $this->app['command.translation-manager.reset'] = $this->app->share(function ($app)
        {
            return new Console\ResetCommand($app[self::PACKAGE]);
        });
        $this->commands('command.translation-manager.reset');

        $this->app['command.translation-manager.import'] = $this->app->share(function ($app)
        {
            return new Console\ImportCommand($app[self::PACKAGE]);
        });
        $this->commands('command.translation-manager.import');

        $this->app['command.translation-manager.find'] = $this->app->share(function ($app)
        {
            return new Console\FindCommand($app[self::PACKAGE]);
        });
        $this->commands('command.translation-manager.find');

        $this->app['command.translation-manager.export'] = $this->app->share(function ($app)
        {
            return new Console\ExportCommand($app[self::PACKAGE]);
        });
        $this->commands('command.translation-manager.export');

        $this->app['command.translation-manager.clean'] = $this->app->share(function ($app)
        {
            return new Console\CleanCommand($app[self::PACKAGE]);
        });
        $this->commands('command.translation-manager.clean');
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public
    function boot()
    {
        $this->package('vsch/'.self::PACKAGE);

        if ($root = Config::get(self::PACKAGE . '::config.web_root')) {
            $before = Config::get(self::PACKAGE . '::config.web_route_before');
            $controller = 'Vsch\TranslationManager\Controller';

            include __DIR__.'/../../routes.php';
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public
    function provides()
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

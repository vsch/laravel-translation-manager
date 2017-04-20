<?php namespace Vsch\TranslationManager;

use Illuminate\Translation\TranslationServiceProvider as BaseTranslationServiceProvider;

class TranslationServiceProvider extends BaseTranslationServiceProvider
{
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
    public function register()
    {
        $this->registerLoader();
        
        $db_driver = config('database.default');

        if ($db_driver === 'pgsql') {
            $translatorRepository = 'Vsch\TranslationManager\Repositories\PostgresTranslatorRepository';
        } else {
            $translatorRepository = 'Vsch\TranslationManager\Repositories\MysqlTranslatorRepository';
        }

        $this->app->bind(
            'Vsch\TranslationManager\Repositories\Interfaces\ITranslatorRepository',
            $translatorRepository
        );

        $this->app->singleton('translator', function ($app) {
            $loader = $app['translation.loader'];

            // When registering the translator component, we'll need to set the default
            // locale as well as the fallback locale. So, we'll grab the application
            // configuration so we can easily get both of these values from there.
            $locale = $app['config']['app.locale'];

            $trans = new \Vsch\TranslationManager\Translator($app, $loader, $locale);

            $trans->setFallback($app['config']['app.fallback_locale']);

            if ($app->bound(\Vsch\TranslationManager\ManagerServiceProvider::PACKAGE)) {
                $trans->setTranslationManager($app[\Vsch\TranslationManager\ManagerServiceProvider::PACKAGE]);
            }

            return $trans;
        });
    }
}

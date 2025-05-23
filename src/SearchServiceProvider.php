<?php

namespace Eduard\Search;

use Illuminate\Support\ServiceProvider;
use Eduard\Search\Events\SearchProccess;
use Eduard\Search\Events\IndexationProccess;
use Eduard\Search\Listeners\AfterSearchProccess;
use Eduard\Search\Listeners\AfterIndexationProccess;
use Eduard\Search\Console\Commands\DisabledIndexProducts;
use Eduard\Search\Console\Commands\JobIndexationProccess;
use Eduard\Search\Console\Commands\JobSearchProccess;
use Eduard\Search\Console\Commands\JobSendMailIndex;

class SearchServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        SearchProccess::class => [
            AfterSearchProccess::class,
        ],
        IndexationProccess::class => [
            AfterIndexationProccess::class,
        ],
    ];

    protected $commands = [
        DisabledIndexProducts::class,
        JobIndexationProccess::class,
        JobSearchProccess::class,
        JobSendMailIndex::class,
    ];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Registra configuraciones adicionales o bindings de servicios aquí si es necesario.
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Registrar eventos y sus listeners
        $this->registerEvents();

        // Cargar migraciones del paquete
        $this->loadMigrations();

        // Publicar configuraciones del paquete
        $this->publishConfigurations();

        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);
        }

        // Cargar rutas específicas del módulo
        $this->loadRoutes();
    }

    /**
     * Registrar eventos del paquete.
     *
     * @return void
     */
    protected function registerEvents()
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                $this->app['events']->listen($event, $listener);
            }
        }
    }

    /**
     * Cargar las migraciones del módulo.
     *
     * @return void
     */
    protected function loadMigrations()
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/./database/migrations');
        }
    }

    /**
     * Publicar las configuraciones del módulo.
     *
     * @return void
     */
    protected function publishConfigurations()
    {
        $this->publishes([
            __DIR__ . '/../config/logging.php' => config_path('search_logging.php'),
        ], 'search-config');
    }

    /**
     * Cargar las rutas específicas del módulo.
     *
     * @return void
     */
    protected function loadRoutes()
    {
        if (file_exists($routesPath = __DIR__ . '/Http/routes/api.php')) {
            $this->loadRoutesFrom($routesPath);
        }
    }
}

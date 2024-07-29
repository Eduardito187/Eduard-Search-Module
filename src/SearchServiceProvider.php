<?php

namespace Eduard\Search;

use Illuminate\Support\ServiceProvider;
use Eduard\Search\Events\IndexationProccess;
use Eduard\Search\Events\SearchProccess;
use Eduard\Search\Listeners\AfterIndexationProccess;
use Eduard\Search\Listeners\AfterSearchProccess;

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
            AfterIndexationProccess::class
        ]
    ];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register package's services here
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Load routes, migrations, etc.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->publishes([
            __DIR__.'/../config/logging.php' => config_path('logging.php'),
        ]);

        // Load API routes
        $this->loadRoutesFrom(__DIR__.'/Http/routes/api.php');
    }
}
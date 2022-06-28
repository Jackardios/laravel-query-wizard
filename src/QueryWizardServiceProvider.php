<?php

namespace Jackardios\QueryWizard;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class QueryWizardServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/query-wizard.php' => config_path('query-wizard.php'),
        ], 'config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/query-wizard.php', 'query-wizard');

        $this->app->bind(QueryParametersManager::class, function ($app) {
            return new QueryParametersManager($app['request']);
        });
    }

    public function provides(): array
    {
        return [
            QueryParametersManager::class,
        ];
    }
}

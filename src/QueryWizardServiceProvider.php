<?php

namespace Jackardios\QueryWizard;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class QueryWizardServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function boot(): void
    {
        if (function_exists('config_path')) {
            $this->publishes([
                __DIR__.'/../config/query-wizard.php' => config_path('query-wizard.php'),
            ], 'config');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/query-wizard.php', 'query-wizard');

        $this->app->bind(QueryWizardRequest::class, function ($app) {
            return QueryWizardRequest::fromRequest($app['request']);
        });
    }

    public function provides(): array
    {
        return [
            QueryWizardRequest::class,
        ];
    }
}

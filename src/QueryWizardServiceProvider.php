<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Jackardios\QueryWizard\Config\QueryWizardConfig;

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

        $this->app->singleton(QueryWizardConfig::class, function () {
            return new QueryWizardConfig;
        });

        // Scoped binding for Octane compatibility
        $this->app->scoped(QueryParametersManager::class, function ($app) {
            return new QueryParametersManager(
                $app['request'],
                $app->make(QueryWizardConfig::class)
            );
        });
    }

    /**
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return [
            QueryParametersManager::class,
            QueryWizardConfig::class,
        ];
    }
}

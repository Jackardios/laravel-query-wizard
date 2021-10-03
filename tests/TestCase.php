<?php

namespace Jackardios\QueryWizard\Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Jackardios\QueryWizard\Tests\Concerns\AssertsModels;
use Jackardios\QueryWizard\Tests\Concerns\AssertsQueryLog;
use Jackardios\QueryWizard\QueryWizardServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use DatabaseMigrations;
    use AssertsQueryLog;
    use AssertsModels;

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            QueryWizardServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('scout.driver', 'elastic');
        $app['config']->set('elastic.migrations.storage_directory', __DIR__ . '/App/data/elastic/migrations');
        $app['config']->set('elastic.scout_driver.refresh_documents', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/App/data/migrations');
        $this->withFactories(__DIR__ . '/App/data/factories');
    }
}

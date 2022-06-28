<?php

namespace Jackardios\QueryWizard\Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Jackardios\QueryWizard\Tests\Concerns\AssertsModels;
use Jackardios\QueryWizard\Tests\Concerns\AssertsQueryLog;
use Jackardios\QueryWizard\QueryWizardServiceProvider;
use Jackardios\QueryWizard\Tests\Concerns\QueryWizardTestingHelpers;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use DatabaseMigrations;
    use AssertsQueryLog;
    use AssertsModels;
    use QueryWizardTestingHelpers;

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/App/data/migrations');
        $this->withFactories(__DIR__ . '/App/data/factories');
    }
}

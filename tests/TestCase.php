<?php

namespace Jackardios\QueryWizard\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Jackardios\QueryWizard\QueryWizardServiceProvider;
use Jackardios\QueryWizard\Tests\Concerns\AssertsModels;
use Jackardios\QueryWizard\Tests\Concerns\AssertsQueryLog;
use Jackardios\QueryWizard\Tests\Concerns\QueryWizardTestingHelpers;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use AssertsModels;
    use AssertsQueryLog;
    use QueryWizardTestingHelpers;
    use RefreshDatabase;

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getPackageProviders($app): array
    {
        return [
            QueryWizardServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/App/data/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();
    }
}

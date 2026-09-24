<?php

namespace Jackardios\QueryWizard\Tests;

use Faker\Generator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Jackardios\QueryWizard\QueryWizardServiceProvider;
use Jackardios\QueryWizard\Tests\Concerns\AssertsQueryLog;
use Jackardios\QueryWizard\Tests\Concerns\QueryWizardTestingHelpers;
use Orchestra\Testbench\TestCase as Orchestra;
use PHPUnit\Runner\ErrorHandler;

abstract class TestCase extends Orchestra
{
    use AssertsQueryLog;
    use QueryWizardTestingHelpers;
    use RefreshDatabase;

    /**
     * @param  Application  $app
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

        $this->app->make(Generator::class)->seed(20260924);
        $this->forwardDeprecationsToPhpunit();
    }

    /**
     * Laravel's exception bootstrapper swallows deprecations (it only logs them),
     * so PHPUnit never sees deprecations triggered by the package. Forward them
     * to PHPUnit's handler, which applies the <source> filter from phpunit.xml.dist.
     */
    private function forwardDeprecationsToPhpunit(): void
    {
        $previous = null;
        $handler = static function (int $level, string $message, string $file = '', int $line = 0) use (&$previous): bool {
            if ($level === E_DEPRECATED || $level === E_USER_DEPRECATED) {
                ErrorHandler::instance()($level, $message, $file, $line);

                return true;
            }

            return $previous !== null && $previous($level, $message, $file, $line) !== false;
        };

        $previous = set_error_handler(\Closure::bind($handler, null, ErrorHandler::class));
    }
}

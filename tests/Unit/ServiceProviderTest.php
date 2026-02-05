<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\QueryWizardServiceProvider;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_config(): void
    {
        $config = config('query-wizard');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('parameters', $config);
        $this->assertArrayHasKey('limits', $config);
    }

    #[Test]
    public function it_registers_query_wizard_config_as_singleton(): void
    {
        $config1 = $this->app->make(QueryWizardConfig::class);
        $config2 = $this->app->make(QueryWizardConfig::class);

        $this->assertInstanceOf(QueryWizardConfig::class, $config1);
        $this->assertSame($config1, $config2);
    }

    #[Test]
    public function it_registers_query_parameters_manager_as_scoped(): void
    {
        $manager1 = $this->app->make(QueryParametersManager::class);
        $manager2 = $this->app->make(QueryParametersManager::class);

        $this->assertInstanceOf(QueryParametersManager::class, $manager1);
        // Scoped binding returns same instance within same request
        $this->assertSame($manager1, $manager2);
    }

    #[Test]
    public function service_provider_provides_correct_classes(): void
    {
        $provider = new QueryWizardServiceProvider($this->app);

        $provides = $provider->provides();

        $this->assertContains(QueryParametersManager::class, $provides);
        $this->assertContains(QueryWizardConfig::class, $provides);
    }

    #[Test]
    public function service_provider_is_deferrable(): void
    {
        $provider = new QueryWizardServiceProvider($this->app);

        $this->assertInstanceOf(\Illuminate\Contracts\Support\DeferrableProvider::class, $provider);
    }

    #[Test]
    public function it_can_publish_config_file(): void
    {
        $provider = new QueryWizardServiceProvider($this->app);

        // Call boot to register publishable paths
        $provider->boot();

        // Check if config is publishable
        $paths = \Illuminate\Support\ServiceProvider::pathsToPublish(QueryWizardServiceProvider::class, 'config');

        $this->assertNotEmpty($paths);
        $this->assertStringContainsString('query-wizard.php', array_key_first($paths));
    }

    #[Test]
    public function query_parameters_manager_receives_request_from_container(): void
    {
        // Set a filter in the request
        $this->app['request']->merge(['filter' => ['name' => 'test']]);

        $manager = $this->app->make(QueryParametersManager::class);

        $this->assertSame($this->app['request'], $manager->getRequest());
        $this->assertEquals(['name' => 'test'], $manager->getFilters()->all());
    }

    #[Test]
    public function query_parameters_manager_receives_config_from_container(): void
    {
        $manager = $this->app->make(QueryParametersManager::class);

        $this->assertInstanceOf(QueryWizardConfig::class, $manager->getConfig());
        $this->assertSame(
            $this->app->make(QueryWizardConfig::class),
            $manager->getConfig()
        );
    }
}

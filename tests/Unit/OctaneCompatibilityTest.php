<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Eloquent\Filters\ScopeFilter;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for Laravel Octane compatibility.
 *
 * These tests verify that the package handles state correctly
 * across multiple requests in a long-running process.
 */
#[Group('octane')]
class OctaneCompatibilityTest extends TestCase
{
    // ========== QueryParametersManager Reset Tests ==========

    #[Test]
    public function query_parameters_manager_reset_clears_all_cached_values(): void
    {
        $request = new Request([
            'filter' => ['name' => 'John'],
            'include' => 'posts',
            'sort' => '-created_at',
            'fields' => ['user' => 'id,name'],
            'append' => 'full_name',
        ]);

        $manager = new QueryParametersManager($request);

        // Access all values to cache them
        $manager->getFilters();
        $manager->getIncludes();
        $manager->getSorts();
        $manager->getFields();
        $manager->getAppends();

        // Reset
        $manager->reset();

        // After reset with same request, values should be re-parsed
        $this->assertEquals('John', $manager->getFilters()->get('name'));
        $this->assertContains('posts', $manager->getIncludes()->all());
    }

    #[Test]
    public function query_parameters_manager_set_request_resets_and_updates(): void
    {
        $request1 = new Request([
            'filter' => ['name' => 'John'],
            'include' => 'posts',
        ]);

        $request2 = new Request([
            'filter' => ['name' => 'Jane'],
            'include' => 'comments',
        ]);

        $manager = new QueryParametersManager($request1);

        // Access values from first request
        $this->assertEquals('John', $manager->getFilters()->get('name'));
        $this->assertContains('posts', $manager->getIncludes()->all());

        // Switch to second request (simulates new Octane request)
        $manager->setRequest($request2);

        // Should now see second request's values
        $this->assertEquals('Jane', $manager->getFilters()->get('name'));
        $this->assertContains('comments', $manager->getIncludes()->all());
        $this->assertNotContains('posts', $manager->getIncludes()->all());
    }

    #[Test]
    public function query_parameters_manager_handles_multiple_request_switches(): void
    {
        $manager = new QueryParametersManager;

        // Simulate multiple requests in sequence (like Octane would do)
        for ($i = 1; $i <= 5; $i++) {
            $request = new Request([
                'filter' => ['iteration' => (string) $i],
            ]);

            $manager->setRequest($request);

            // Each iteration should see its own values, not leaked from previous
            $this->assertEquals((string) $i, $manager->getFilters()->get('iteration'));
        }
    }

    #[Test]
    public function query_parameters_manager_reset_allows_manual_parameter_setting(): void
    {
        $request = new Request([
            'filter' => ['name' => 'FromRequest'],
        ]);

        $manager = new QueryParametersManager($request);

        // Access from request
        $this->assertEquals('FromRequest', $manager->getFilters()->get('name'));

        // Reset and set manually
        $manager->reset();
        $manager->setFiltersParameter(['name' => 'Manual']);

        // Should see manual value
        $this->assertEquals('Manual', $manager->getFilters()->get('name'));
    }

    // ========== ScopeFilter Cache Tests ==========

    #[Test]
    public function scope_filter_clear_reflection_cache_resets_static_cache(): void
    {
        // Create a model and apply scope filter to populate cache
        TestModel::factory()->create(['name' => 'Test']);

        $request = new Request(['filter' => ['named' => 'Test']]);
        $this->app->instance('request', $request);

        // This should populate the reflection cache
        EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters(ScopeFilter::make('named'))
            ->get();

        // Clear the cache
        ScopeFilter::clearReflectionCache();

        // Cache should be cleared - running again should work without issues
        $result = EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters(ScopeFilter::make('named'))
            ->get();

        $this->assertCount(1, $result);
    }

    #[Test]
    public function scope_filter_cache_does_not_leak_between_cleared_requests(): void
    {
        TestModel::factory()->create(['name' => 'First']);
        TestModel::factory()->create(['name' => 'Second']);

        // First "request"
        $request1 = new Request(['filter' => ['named' => 'First']]);
        $this->app->instance('request', $request1);

        $result1 = EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters(ScopeFilter::make('named'))
            ->get();

        $this->assertCount(1, $result1);
        $this->assertEquals('First', $result1->first()->name);

        // Simulate Octane request flush
        ScopeFilter::clearReflectionCache();
        $this->app->forgetScopedInstances();

        // Second "request"
        $request2 = new Request(['filter' => ['named' => 'Second']]);
        $this->app->instance('request', $request2);

        $result2 = EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters(ScopeFilter::make('named'))
            ->get();

        $this->assertCount(1, $result2);
        $this->assertEquals('Second', $result2->first()->name);
    }

    // ========== Service Container Binding Tests ==========

    #[Test]
    public function scoped_binding_provides_fresh_instance_per_resolution(): void
    {
        // In Octane, scoped bindings are flushed between requests
        // Simulate this by manually resolving and checking independence

        $request1 = new Request(['filter' => ['name' => 'Request1']]);
        $this->app->instance('request', $request1);

        // First resolution
        $manager1 = $this->app->make(QueryParametersManager::class);
        $filters1 = $manager1->getFilters()->get('name');

        // Simulate Octane request flush by forgetting scoped instances
        $this->app->forgetScopedInstances();

        // New request
        $request2 = new Request(['filter' => ['name' => 'Request2']]);
        $this->app->instance('request', $request2);

        // Second resolution should get fresh instance
        $manager2 = $this->app->make(QueryParametersManager::class);
        $filters2 = $manager2->getFilters()->get('name');

        $this->assertEquals('Request1', $filters1);
        $this->assertEquals('Request2', $filters2);
        $this->assertNotSame($manager1, $manager2);
    }

    // ========== Wizard Instance Independence Tests ==========

    #[Test]
    public function wizard_instances_are_independent_between_requests(): void
    {
        TestModel::factory()->count(5)->create();

        // First "request" with filters
        $request1 = new Request(['filter' => ['name' => 'NonExistent']]);
        $this->app->instance('request', $request1);

        $wizard1 = EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters('name');
        $result1 = $wizard1->get();

        // Simulate Octane request flush
        $this->app->forgetScopedInstances();

        // Second "request" without filters (should get all)
        $request2 = new Request([]);
        $this->app->instance('request', $request2);

        $wizard2 = EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters('name');
        $result2 = $wizard2->get();

        // First request filtered to 0, second should get all 5
        $this->assertCount(0, $result1);
        $this->assertCount(5, $result2);
    }

    #[Test]
    public function cloned_wizards_do_not_share_state(): void
    {
        TestModel::factory()->count(3)->create();

        $request = new Request(['sort' => 'name']);
        $this->app->instance('request', $request);

        $wizard1 = EloquentQueryWizard::for(TestModel::class)
            ->allowedSorts('name', 'id');

        // Clone before building
        $wizard2 = clone $wizard1;

        // Modify original
        $wizard1->defaultSorts('-id');

        // Build both - they should be independent
        $result1 = $wizard1->get();
        $result2 = $wizard2->get();

        // Both should work independently
        $this->assertCount(3, $result1);
        $this->assertCount(3, $result2);
    }

    // ========== Config Independence Tests ==========

    #[Test]
    public function config_singleton_is_safe_for_octane(): void
    {
        // QueryWizardConfig is a singleton, but it only reads from Laravel config
        // which is immutable during request lifecycle

        $config1 = $this->app->make(QueryWizardConfig::class);
        $config2 = $this->app->make(QueryWizardConfig::class);

        // Should be same instance (singleton)
        $this->assertSame($config1, $config2);

        // Both should have same values
        $this->assertEquals($config1->getCountSuffix(), $config2->getCountSuffix());
        $this->assertEquals($config1->getMaxIncludeDepth(), $config2->getMaxIncludeDepth());
    }

    // ========== Memory Leak Prevention Tests ==========

    #[Test]
    public function wizard_does_not_retain_references_after_execution(): void
    {
        TestModel::factory()->create();

        $request = new Request(['include' => 'relatedModels']);
        $this->app->instance('request', $request);

        // Create wizard and execute
        $wizard = EloquentQueryWizard::for(TestModel::class)
            ->allowedIncludes('relatedModels');

        $result = $wizard->get();

        // Result should be a collection, not tied to wizard
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $result);

        // Wizard can be unset without affecting result
        unset($wizard);

        $this->assertCount(1, $result);
    }
}

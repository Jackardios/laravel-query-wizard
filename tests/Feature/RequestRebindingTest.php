<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The scoped parameters manager follows the container's request when it is
 * rebound without a scope reset (feature tests, sub-requests).
 */
class RequestRebindingTest extends TestCase
{
    #[Test]
    public function the_scoped_manager_follows_a_rebound_request(): void
    {
        $this->app->instance('request', new Request(['filter' => ['name' => 'first']]));
        $first = $this->app->make(QueryParametersManager::class);

        $this->app->instance('request', new Request(['filter' => ['name' => 'second']]));
        $second = $this->app->make(QueryParametersManager::class);

        $this->assertSame('first', $first->getFilters()->get('name'));
        $this->assertSame('second', $second->getFilters()->get('name'));
    }

    #[Test]
    public function a_wizard_rebuilds_for_a_rebound_request(): void
    {
        TestModel::factory()->create(['name' => 'first']);
        TestModel::factory()->create(['name' => 'second']);

        $this->app->instance('request', new Request(['filter' => ['name' => 'first']]));
        $wizard = EloquentQueryWizard::for(TestModel::class)->allowedFilters('name');
        $first = $wizard->get()->pluck('name')->all();

        $this->app->instance('request', new Request(['filter' => ['name' => 'second']]));
        $second = $wizard->get()->pluck('name')->all();

        $this->assertSame(['first'], $first);
        $this->assertSame(['second'], $second);
    }

    #[Test]
    public function each_http_request_in_a_test_sees_its_own_parameters(): void
    {
        TestModel::factory()->create(['name' => 'a']);
        TestModel::factory()->create(['name' => 'b']);

        Route::get('/rebinding-models', fn () => EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters('name')
            ->allowedSorts('name')
            ->get()
            ->pluck('name'));

        $this->getJson('/rebinding-models?filter[name]=a')->assertExactJson(['a']);
        $this->getJson('/rebinding-models?filter[name]=b')->assertExactJson(['b']);
        $this->getJson('/rebinding-models?sort=name')->assertExactJson(['a', 'b']);
    }
}

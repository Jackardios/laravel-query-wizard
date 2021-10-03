<?php

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Jackardios\QueryWizard\EloquentQueryWizard;
use Jackardios\QueryWizard\Handlers\Eloquent\EloquentQueryHandler;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\FiltersCallback;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;

/**
 * @group eloquent
 * @group filter
 * @group eloquent-filter
 */
class FiltersCallbackTest extends TestCase
{
    /** @var \Illuminate\Support\Collection */
    protected $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 3)->create();
    }

    /** @test */
    public function it_should_filter_by_closure()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'callback' => $this->models->first()->name,
            ])
            ->setAllowedFilters(
                new FiltersCallback('callback', function (EloquentQueryHandler $queryHandler, Builder $query, $value) {
                    $query->where('name', $value);
                })
            )
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_should_filter_by_array_callback()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'callback' => $this->models->first()->name,
            ])
            ->setAllowedFilters(new FiltersCallback('callback', [$this, 'filterCallback']))
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    public function filterCallback(EloquentQueryHandler $queryHandler, Builder $query, $value): void
    {
        $query->where('name', $value);
    }

    protected function createQueryFromFilterRequest(array $filters): EloquentQueryWizard
    {
        $request = new Request([
            'filter' => $filters,
        ]);

        return EloquentQueryWizard::for(TestModel::class, $request);
    }
}

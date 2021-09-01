<?php

namespace Jackardios\QueryWizard\Tests\Scout;

use Jackardios\QueryWizard\Tests\TestCase;
use Laravel\Scout\Builder;
use Illuminate\Http\Request;
use Jackardios\QueryWizard\ScoutQueryWizard;
use Jackardios\QueryWizard\Handlers\Scout\ScoutQueryHandler;
use Jackardios\QueryWizard\Handlers\Scout\Filters\FiltersCallback;
use Jackardios\QueryWizard\Tests\TestClasses\Models\TestModel;

/**
 * @group scout
 * @group filter
 * @group scout-filter
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
                new FiltersCallback('callback', function (ScoutQueryHandler $queryHandler, Builder $query, $value) {
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

    public function filterCallback(ScoutQueryHandler $queryHandler, Builder $query, $value): void
    {
        $query->where('name', $value);
    }

    protected function createQueryFromFilterRequest(array $filters): ScoutQueryWizard
    {
        $request = new Request([
            'filter' => $filters,
        ]);

        return ScoutQueryWizard::for(TestModel::search(), $request);
    }
}

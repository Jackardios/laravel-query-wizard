<?php

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Eloquent\Filters\CallbackFilter;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;

/**
 * @group eloquent
 * @group filter
 * @group eloquent-filter
 */
class CallbackFilterTest extends TestCase
{
    protected Collection $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 3)->create();
    }

    /** @test */
    public function it_should_filter_by_closure(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters([
                'callback' => $this->models->first()->name,
            ])
            ->setAllowedFilters(
                new CallbackFilter('callback', function (EloquentQueryWizard $queryWizard, Builder $queryBuilder, $value) {
                    $queryBuilder->where('name', $value);
                })
            )
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_should_filter_by_array_callback(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters([
                'callback' => $this->models->first()->name,
            ])
            ->setAllowedFilters(new CallbackFilter('callback', [$this, 'filterCallback']))
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    public function filterCallback(EloquentQueryWizard $queryWizard, Builder $queryBuilder, $value): void
    {
        $queryBuilder->where('name', $value);
    }
}

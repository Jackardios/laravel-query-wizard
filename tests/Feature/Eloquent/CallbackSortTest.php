<?php

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Eloquent\Sorts\CallbackSort;
use Jackardios\QueryWizard\Tests\Concerns\AssertsCollectionSorting;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;

/**
 * @group eloquent
 * @group sort
 * @group eloquent-sort
 */
class CallbackSortTest extends TestCase
{
    use AssertsCollectionSorting;

    protected Collection $models;

    public function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        $this->models = factory(TestModel::class, 5)->create();
    }

    /** @test */
    public function it_should_sort_by_closure(): void
    {
        $sortedModels = $this
            ->createEloquentWizardWithSorts('-callbackSort')
            ->setAllowedSorts(
                new CallbackSort('callbackSort', function (EloquentQueryWizard $queryWizard, Builder $queryBuilder, string $direction) {
                    $queryBuilder->orderBy('name', $direction);
                })
            )
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` desc');
        $this->assertSortedDescending($sortedModels, 'name');
    }

    /** @test */
    public function it_should_sort_by_array_callback(): void
    {
        $sortedModels = $this
            ->createEloquentWizardWithSorts('callbackSort')
            ->setAllowedSorts(new CallbackSort('callbackSort', [$this, 'sortCallback']))
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` asc');
        $this->assertSortedAscending($sortedModels, 'name');
    }

    public function sortCallback(EloquentQueryWizard $queryWizard, Builder $queryBuilder, string $direction): void
    {
        $queryBuilder->orderBy('name', $direction);
    }
}

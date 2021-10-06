<?php

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\EloquentQueryWizard;
use Jackardios\QueryWizard\Handlers\Eloquent\EloquentQueryHandler;
use Jackardios\QueryWizard\Handlers\Eloquent\Sorts\CallbackSort;
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

    /** @var \Illuminate\Support\Collection */
    protected $models;

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
            ->createWizardFromSortRequest('-callbackSort')
            ->setAllowedSorts(
                new CallbackSort('callbackSort', function (EloquentQueryHandler $queryHandler, Builder $query, string $direction) {
                    $query->orderBy('name', $direction);
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
            ->createWizardFromSortRequest('callbackSort')
            ->setAllowedSorts(new CallbackSort('callbackSort', [$this, 'sortCallback']))
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` asc');
        $this->assertSortedAscending($sortedModels, 'name');
    }

    public function sortCallback(EloquentQueryHandler $queryHandler, Builder $query, string $direction): void
    {
        $query->orderBy('name', $direction);
    }

    protected function createWizardFromSortRequest(string $sort): EloquentQueryWizard
    {
        $request = new Request([
            'sort' => $sort,
        ]);

        return EloquentQueryWizard::for(TestModel::class, $request);
    }
}

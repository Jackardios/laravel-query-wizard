<?php

namespace Jackardios\QueryWizard\Tests\Eloquent;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\EloquentQueryWizard;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\FiltersScope;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\FiltersTrashed;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Tests\TestClasses\Models\SoftDeleteModel;

/**
 * @group eloquent
 * @group filter
 */
class FiltersTrashedTest extends TestCase
{
    /** @var \Illuminate\Support\Collection */
    protected $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(SoftDeleteModel::class, 2)->create()
            ->merge(factory(SoftDeleteModel::class, 1)->create(['deleted_at' => now()]));
    }

    /** @test */
    public function it_should_filter_not_trashed_by_default()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'trashed' => '',
            ])
            ->setAllowedFilters(new FiltersTrashed())
            ->build()
            ->get();

        $this->assertCount(2, $models);
    }

    /** @test */
    public function it_can_filter_only_trashed()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'trashed' => 'only',
            ])
            ->setAllowedFilters(new FiltersTrashed())
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_can_filter_only_trashed_by_scope_directly()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'only_trashed' => true,
            ])
            ->setAllowedFilters(new FiltersScope('only_trashed'))
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_can_filter_with_trashed()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'trashed' => 'with',
            ])
            ->setAllowedFilters(new FiltersTrashed())
            ->build()
            ->get();

        $this->assertCount(3, $models);
    }

    protected function createQueryFromFilterRequest(array $filters): EloquentQueryWizard
    {
        $request = new Request([
            'filter' => $filters,
        ]);

        return EloquentQueryWizard::for(SoftDeleteModel::class, $request);
    }
}

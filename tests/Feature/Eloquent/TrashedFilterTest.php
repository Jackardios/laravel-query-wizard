<?php

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\EloquentQueryWizard;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\ScopeFilter;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\TrashedFilter;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Tests\App\Models\SoftDeleteModel;

/**
 * @group eloquent
 * @group filter
 * @group eloquent-filter
 */
class TrashedFilterTest extends TestCase
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
    public function it_should_filter_not_trashed_by_default(): void
    {
        $models = $this
            ->createWizardFromFilterRequest([
                'trashed' => '',
            ])
            ->setAllowedFilters(new TrashedFilter())
            ->build()
            ->get();

        $this->assertCount(2, $models);
    }

    /** @test */
    public function it_can_filter_only_trashed(): void
    {
        $models = $this
            ->createWizardFromFilterRequest([
                'trashed' => 'only',
            ])
            ->setAllowedFilters(new TrashedFilter())
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_can_filter_only_trashed_by_scope_directly(): void
    {
        $models = $this
            ->createWizardFromFilterRequest([
                'only_trashed' => true,
            ])
            ->setAllowedFilters(new ScopeFilter('only_trashed'))
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_can_filter_with_trashed(): void
    {
        $models = $this
            ->createWizardFromFilterRequest([
                'trashed' => 'with',
            ])
            ->setAllowedFilters(new TrashedFilter())
            ->build()
            ->get();

        $this->assertCount(3, $models);
    }

    protected function createWizardFromFilterRequest(array $filters): EloquentQueryWizard
    {
        $request = new Request([
            'filter' => $filters,
        ]);

        return EloquentQueryWizard::for(SoftDeleteModel::class, $request);
    }
}

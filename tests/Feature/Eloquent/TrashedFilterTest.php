<?php

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Eloquent\Filters\ScopeFilter;
use Jackardios\QueryWizard\Eloquent\Filters\TrashedFilter;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Tests\App\Models\SoftDeleteModel;

/**
 * @group eloquent
 * @group filter
 * @group eloquent-filter
 */
class TrashedFilterTest extends TestCase
{
    protected Collection $models;

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
            ->createEloquentWizardWithFilters([
                'trashed' => '',
            ], SoftDeleteModel::class)
            ->setAllowedFilters(new TrashedFilter())
            ->build()
            ->get();

        $this->assertCount(2, $models);
    }

    /** @test */
    public function it_can_filter_only_trashed(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters([
                'trashed' => 'only',
            ], SoftDeleteModel::class)
            ->setAllowedFilters(new TrashedFilter())
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_can_filter_only_trashed_by_scope_directly(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters([
                'only_trashed' => true,
            ], SoftDeleteModel::class)
            ->setAllowedFilters(new ScopeFilter('only_trashed'))
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_can_filter_with_trashed(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters([
                'trashed' => 'with',
            ], SoftDeleteModel::class)
            ->setAllowedFilters(new TrashedFilter())
            ->build()
            ->get();

        $this->assertCount(3, $models);
    }
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Tests\App\Models\SoftDeleteModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('filter')]
#[Group('trashed')]
class TrashedFilterTest extends TestCase
{
    protected Collection $models;

    protected Collection $trashedModels;

    protected function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        // Create regular models
        $this->models = SoftDeleteModel::factory()->count(3)->create();

        // Create and soft-delete models
        $this->trashedModels = SoftDeleteModel::factory()->count(2)->create();
        $this->trashedModels->each->delete();
    }

    // ========== Basic Trashed Filter Tests ==========
    #[Test]
    public function it_returns_only_non_trashed_by_default(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([], SoftDeleteModel::class)
            ->get();

        $this->assertCount(3, $models);
    }

    #[Test]
    public function it_can_filter_only_trashed(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'only'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(2, $models);
        $this->assertTrue($models->every(fn ($m) => $m->trashed()));
    }

    #[Test]
    public function it_can_include_trashed_with_all(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'with'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function it_leaves_query_unmodified_for_empty_filter_value(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => ''], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        // Empty string is not 'with', 'only', or 'without' — query unmodified
        $this->assertCount(3, $models);
    }

    #[Test]
    public function it_excludes_trashed_with_explicit_value(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'without'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    // ========== Case-Insensitive Tests ==========
    #[Test]
    public function it_accepts_uppercase_only(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'ONLY'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(2, $models);
        $this->assertTrue($models->every(fn ($m) => $m->trashed()));
    }

    #[Test]
    public function it_accepts_uppercase_with(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'WITH'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function it_accepts_mixed_case_only(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'Only'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(2, $models);
    }

    #[Test]
    public function it_accepts_uppercase_without(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'WITHOUT'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    // ========== Alias Tests ==========
    #[Test]
    public function it_works_with_custom_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['deleted' => 'only'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed('deleted'))
            ->get();

        $this->assertCount(2, $models);
    }

    #[Test]
    public function it_works_with_is_deleted_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['is_deleted' => 'with'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed('is_deleted'))
            ->get();

        $this->assertCount(5, $models);
    }

    // ========== Boolean Value Tests ==========
    #[Test]
    public function it_treats_boolean_true_as_with_trashed(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => true], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function it_treats_string_true_as_with_trashed(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'true'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function it_treats_boolean_false_as_without_trashed(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => false], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    #[Test]
    public function it_treats_string_false_as_without_trashed(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'false'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    #[Test]
    public function it_leaves_query_unmodified_for_numeric_values(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => '1'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    #[Test]
    public function it_leaves_query_unmodified_for_zero(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => '0'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    #[Test]
    public function it_handles_invalid_trashed_value_gracefully(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'invalid_value'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        // Invalid value leaves query unmodified — default soft delete scope still applies
        $this->assertCount(3, $models);
    }

    #[Test]
    public function it_handles_null_trashed_value(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => null], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    // ========== Combination Tests ==========
    #[Test]
    public function it_can_combine_with_other_filters(): void
    {
        $targetName = $this->trashedModels->first()->name;

        $models = $this
            ->createEloquentWizardWithFilters([
                'name' => $targetName,
                'trashed' => 'only',
            ], SoftDeleteModel::class)
            ->allowedFilters(
                EloquentFilter::exact('name'),
                EloquentFilter::trashed()
            )
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($targetName, $models->first()->name);
    }

    #[Test]
    public function it_can_combine_with_partial_filter(): void
    {
        $firstTrashed = $this->trashedModels->first();

        $models = $this
            ->createEloquentWizardWithFilters([
                'name' => $firstTrashed->name,
                'trashed' => 'with',
            ], SoftDeleteModel::class)
            ->allowedFilters(
                EloquentFilter::partial('name'),
                EloquentFilter::trashed()
            )
            ->get();

        $this->assertTrue($models->contains('id', $firstTrashed->id));
        $this->assertNotEmpty($models);
    }

    // ========== SQL Query Verification Tests ==========
    #[Test]
    public function it_uses_only_trashed_scope_correctly(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['trashed' => 'only'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->build()
            ->toSql();

        $this->assertStringContainsString('deleted_at', $sql);
        $this->assertStringContainsString('is not null', strtolower($sql));
    }

    #[Test]
    public function it_uses_with_trashed_scope_correctly(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['trashed' => 'with'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->build()
            ->toSql();

        // withTrashed should NOT have deleted_at is null condition
        $deletedAtNullPattern = '/deleted_at.*is null/i';
        $this->assertDoesNotMatchRegularExpression($deletedAtNullPattern, $sql);
    }

    // ========== Edge Cases ==========
    #[Test]
    public function it_works_without_filter_value_provided(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    #[Test]
    public function it_can_filter_trashed_with_pagination(): void
    {
        $result = $this
            ->createEloquentWizardWithFilters(['trashed' => 'with'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->build()
            ->paginate(10);

        $this->assertEquals(5, $result->total());
    }

    #[Test]
    public function it_can_filter_only_trashed_with_first(): void
    {
        $model = $this
            ->createEloquentWizardWithFilters(['trashed' => 'only'], SoftDeleteModel::class)
            ->allowedFilters(EloquentFilter::trashed())
            ->build()
            ->first();

        $this->assertNotNull($model);
        $this->assertTrue($model->trashed());
    }

    // ========== Integration with Query Builder ==========
    #[Test]
    public function it_works_with_custom_base_query(): void
    {
        $targetName = $this->trashedModels->first()->name;

        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'only'], SoftDeleteModel::where('name', $targetName))
            ->allowedFilters(EloquentFilter::trashed())
            ->get();

        $this->assertCount(1, $models);
    }
}

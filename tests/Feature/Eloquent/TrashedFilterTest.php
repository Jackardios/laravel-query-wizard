<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\FilterDefinition;
use Jackardios\QueryWizard\Tests\App\Models\SoftDeleteModel;
use Jackardios\QueryWizard\Tests\TestCase;

/**
 * @group eloquent
 * @group filter
 * @group trashed
 */
class TrashedFilterTest extends TestCase
{
    protected Collection $models;
    protected Collection $trashedModels;

    public function setUp(): void
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

    /** @test */
    public function it_returns_only_non_trashed_by_default(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([], SoftDeleteModel::class)
            ->get();

        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_can_filter_only_trashed(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'only'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        $this->assertCount(2, $models);
        $this->assertTrue($models->every(fn($m) => $m->trashed()));
    }

    /** @test */
    public function it_can_include_trashed_with_all(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'with'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        $this->assertCount(5, $models);
    }

    /** @test */
    public function it_excludes_trashed_when_filter_is_empty(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => ''], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        $this->assertCount(3, $models);
        $this->assertTrue($models->every(fn($m) => !$m->trashed()));
    }

    /** @test */
    public function it_excludes_trashed_with_explicit_value(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'without'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    // ========== Alias Tests ==========

    /** @test */
    public function it_works_with_custom_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['deleted' => 'only'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed('deleted'))
            ->get();

        $this->assertCount(2, $models);
    }

    /** @test */
    public function it_works_with_is_deleted_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['is_deleted' => 'with'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed('is_deleted'))
            ->get();

        $this->assertCount(5, $models);
    }

    // ========== Value Tests ==========
    // Note: TrashedFilterStrategy only matches exact strings 'with' and 'only' (lowercase)
    // Any other value (including booleans, uppercase, etc.) results in 'withoutTrashed()'

    /** @test */
    public function it_defaults_to_without_for_non_matching_values(): void
    {
        // Boolean true is not 'with' or 'only', so it results in withoutTrashed
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => true], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_defaults_to_without_for_string_true(): void
    {
        // Note: 'true' string is converted to boolean true by QueryParametersManager
        // which is not 'with' or 'only', so it results in withoutTrashed
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'true'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_defaults_to_without_for_boolean_false(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => false], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_defaults_to_without_for_string_false(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'false'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    // ========== Numeric Value Tests ==========

    /** @test */
    public function it_defaults_to_without_for_numeric_values(): void
    {
        // '1' is not 'with' or 'only', so it results in withoutTrashed
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => '1'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_defaults_to_without_for_zero(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => '0'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    // ========== Combination Tests ==========

    /** @test */
    public function it_can_combine_with_other_filters(): void
    {
        $targetName = $this->trashedModels->first()->name;

        $models = $this
            ->createEloquentWizardWithFilters([
                'name' => $targetName,
                'trashed' => 'only',
            ], SoftDeleteModel::class)
            ->setAllowedFilters(
                FilterDefinition::exact('name'),
                FilterDefinition::trashed()
            )
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($targetName, $models->first()->name);
    }

    /** @test */
    public function it_can_combine_with_partial_filter(): void
    {
        $firstTrashed = $this->trashedModels->first();
        $partialName = substr($firstTrashed->name, 0, 3);

        $models = $this
            ->createEloquentWizardWithFilters([
                'name' => $partialName,
                'trashed' => 'with',
            ], SoftDeleteModel::class)
            ->setAllowedFilters(
                FilterDefinition::partial('name'),
                FilterDefinition::trashed()
            )
            ->get();

        $this->assertGreaterThanOrEqual(1, $models->count());
    }

    // ========== SQL Query Verification Tests ==========

    /** @test */
    public function it_uses_only_trashed_scope_correctly(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['trashed' => 'only'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->build()
            ->toSql();

        $this->assertStringContainsString('deleted_at', $sql);
        $this->assertStringContainsString('is not null', strtolower($sql));
    }

    /** @test */
    public function it_uses_with_trashed_scope_correctly(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['trashed' => 'with'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->build()
            ->toSql();

        // withTrashed should NOT have deleted_at is null condition
        $deletedAtNullPattern = '/deleted_at.*is null/i';
        $this->assertDoesNotMatchRegularExpression($deletedAtNullPattern, $sql);
    }

    // ========== Edge Cases ==========

    /** @test */
    public function it_handles_invalid_trashed_value_gracefully(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'invalid_value'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        // Invalid value should be treated as 'without' (default behavior)
        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_handles_null_trashed_value(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => null], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        // Null should be treated as 'without'
        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_works_without_filter_value_provided(): void
    {
        // When trashed filter is allowed but not in request
        $models = $this
            ->createEloquentWizardFromQuery([], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_can_filter_trashed_with_pagination(): void
    {
        $result = $this
            ->createEloquentWizardWithFilters(['trashed' => 'with'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->build()
            ->paginate(10);

        $this->assertEquals(5, $result->total());
    }

    /** @test */
    public function it_can_filter_only_trashed_with_first(): void
    {
        $model = $this
            ->createEloquentWizardWithFilters(['trashed' => 'only'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->build()
            ->first();

        $this->assertNotNull($model);
        $this->assertTrue($model->trashed());
    }

    // ========== Case Sensitivity Tests ==========
    // Note: TrashedFilterStrategy is case-sensitive, only exact 'with' and 'only' work

    /** @test */
    public function it_is_case_sensitive_for_only(): void
    {
        // 'ONLY' is not 'only', so it results in withoutTrashed
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'ONLY'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_is_case_sensitive_for_with(): void
    {
        // 'WITH' is not 'with', so it results in withoutTrashed
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'WITH'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_is_case_sensitive_mixed_case(): void
    {
        // 'Only' is not 'only', so it results in withoutTrashed
        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'Only'], SoftDeleteModel::class)
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        $this->assertCount(3, $models);
    }

    // ========== Integration with Query Builder ==========

    /** @test */
    public function it_works_with_custom_base_query(): void
    {
        $targetName = $this->trashedModels->first()->name;

        $models = $this
            ->createEloquentWizardWithFilters(['trashed' => 'only'], SoftDeleteModel::where('name', $targetName))
            ->setAllowedFilters(FilterDefinition::trashed())
            ->get();

        $this->assertCount(1, $models);
    }
}

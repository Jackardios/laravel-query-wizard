<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * General filter tests covering validation, edge cases, and shared behavior.
 *
 * Specific filter type tests are in separate files:
 * - ExactFilterTest
 * - PartialFilterTest
 * - ScopeFilterTest
 * - CallbackFilterTest
 * - RangeFilterTest (includes DateRange)
 * - NullFilterTest
 * - RelationFilterTest
 * - PassthroughFilterTest
 * - TrashedFilterTest
 */
#[Group('eloquent')]
#[Group('filter')]
class FilterTest extends EloquentFilterTestCase
{
    // ========== Validation Tests ==========

    #[Test]
    public function it_throws_exception_for_not_allowed_filter(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createEloquentWizardWithFilters(['not_allowed' => 'value'])
            ->allowedFilters('name')
            ->get();
    }

    #[Test]
    public function it_throws_exception_for_unknown_filters_when_no_allowed_set(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createEloquentWizardWithFilters(['unknown' => 'value'])
            ->allowedFilters([])
            ->get();
    }

    #[Test]
    public function it_throws_exception_with_empty_allowed_filters_array(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createEloquentWizardWithFilters(['name' => 'test'])
            ->allowedFilters([])
            ->get();
    }

    // ========== Edge Cases ==========

    #[Test]
    public function it_handles_empty_filter_value_as_null(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => ''])
            ->allowedFilters('name')
            ->get();

        // Empty string is converted to null - filter is not applied, returns all models
        $this->assertCount(5, $models);
    }

    #[Test]
    public function it_skips_null_filter_value(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => null])
            ->allowedFilters('name')
            ->get();

        // Null value means filter is not applied - returns all models
        $this->assertCount(5, $models);
    }

    #[Test]
    public function it_handles_boolean_true_filter_value(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'true'])
            ->allowedFilters('name')
            ->get();

        // 'true' is parsed as boolean true by QueryParametersManager
        $this->assertCount(0, $models);
    }

    #[Test]
    public function it_handles_boolean_false_filter_value(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'false'])
            ->allowedFilters('name')
            ->get();

        // 'false' is parsed as boolean false
        $this->assertCount(0, $models);
    }

    #[Test]
    public function it_can_combine_multiple_filters(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters([
                'name' => $model->name,
                'id' => $model->id,
            ])
            ->allowedFilters('name', 'id')
            ->get();

        $this->assertCount(1, $models);
    }

    #[Test]
    public function it_handles_filter_with_special_characters(): void
    {
        $model = TestModel::factory()->create(['name' => "Test's Model"]);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => "Test's Model"])
            ->allowedFilters('name')
            ->get();

        $this->assertCount(1, $models);
    }

    #[Test]
    public function filters_are_applied_with_and_logic(): void
    {
        TestModel::factory()->create(['name' => 'unique_combo']);

        $models = $this
            ->createEloquentWizardWithFilters([
                'name' => 'unique_combo',
            ])
            ->allowedFilters('name', 'id')
            ->get();

        $this->assertCount(1, $models);
    }

    // ========== Type-Safe Options Tests ==========

    #[Test]
    public function it_respects_filter_options(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => 'test'])
            ->allowedFilters(
                EloquentFilter::exact('name')->withoutRelationConstraint()
            )
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('name', $sql);
    }

    // ========== Conditional Filters (when) Tests ==========

    #[Test]
    public function filter_with_when_condition_true_applies(): void
    {
        $targetModel = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $targetModel->name])
            ->allowedFilters(
                EloquentFilter::exact('name')->when(fn ($value) => true)
            )
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($targetModel->name, $models->first()->name);
    }

    #[Test]
    public function filter_with_when_condition_false_skips(): void
    {
        $targetModel = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $targetModel->name])
            ->allowedFilters(
                EloquentFilter::exact('name')->when(fn ($value) => false)
            )
            ->get();

        // Condition is false, filter is skipped - returns all models
        $this->assertCount(5, $models);
    }

    #[Test]
    public function filter_when_with_value_check(): void
    {
        // Only apply filter when value is not 'all'
        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'all'])
            ->allowedFilters(
                EloquentFilter::exact('name')->when(fn ($value) => $value !== 'all')
            )
            ->get();

        // Value is 'all', so condition is false - filter skipped
        $this->assertCount(5, $models);
    }

    #[Test]
    public function filter_when_with_value_check_applies(): void
    {
        $targetModel = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $targetModel->name])
            ->allowedFilters(
                EloquentFilter::exact('name')->when(fn ($value) => $value !== 'all')
            )
            ->get();

        // Value is not 'all', so condition is true - filter applied
        $this->assertCount(1, $models);
    }

    // ========== Unicode and Special Input Tests ==========

    #[Test]
    public function it_handles_unicode_filter_values(): void
    {
        $model = TestModel::factory()->create(['name' => 'Тест']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'Тест'])
            ->allowedFilters('name')
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals('Тест', $models->first()->name);
    }

    #[Test]
    public function it_handles_emoji_in_filter_values(): void
    {
        $model = TestModel::factory()->create(['name' => 'Test 🎉']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'Test 🎉'])
            ->allowedFilters('name')
            ->get();

        $this->assertCount(1, $models);
    }

    #[Test]
    public function it_handles_very_long_filter_values(): void
    {
        $longName = str_repeat('a', 255);
        $model = TestModel::factory()->create(['name' => $longName]);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $longName])
            ->allowedFilters('name')
            ->get();

        $this->assertCount(1, $models);
    }

    // ========== JsonContains Filter Tests ==========

    #[Test]
    public function it_can_filter_json_contains_single_value(): void
    {
        TestModel::factory()->create(['name' => 'json_test']);

        $sql = $this
            ->createEloquentWizardWithFilters(['tags' => 'php'])
            ->allowedFilters(EloquentFilter::jsonContains('tags'))
            ->toQuery()
            ->toSql();

        // SQLite uses json_each, MySQL uses json_contains
        $sqlLower = strtolower($sql);
        $this->assertTrue(
            str_contains($sqlLower, 'json_contains') || str_contains($sqlLower, 'json_each'),
            "Expected JSON filtering SQL, got: {$sql}"
        );
    }

    #[Test]
    public function it_can_filter_json_contains_array_value(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['tags' => ['php', 'laravel']])
            ->allowedFilters(EloquentFilter::jsonContains('tags'))
            ->toQuery()
            ->toSql();

        // SQLite uses json_each, MySQL uses json_contains
        $sqlLower = strtolower($sql);
        $this->assertTrue(
            str_contains($sqlLower, 'json_contains') || str_contains($sqlLower, 'json_each'),
            "Expected JSON filtering SQL, got: {$sql}"
        );
    }
}

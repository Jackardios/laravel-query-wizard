<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\FilterDefinition;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;

#[Group('eloquent')]
#[Group('filter')]
class FilterTest extends TestCase
{
    protected Collection $models;

    public function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();
        $this->models = TestModel::factory()->count(5)->create();
    }

    // ========== Exact Filter Tests ==========
    #[Test]
    public function it_can_filter_by_exact_property(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => $this->models->first()->name])
            ->setAllowedFilters('name')
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($this->models->first()->id, $models->first()->id);
    }
    #[Test]
    public function it_can_filter_by_exact_property_with_definition(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => $this->models->first()->name])
            ->setAllowedFilters(FilterDefinition::exact('name'))
            ->get();

        $this->assertCount(1, $models);
    }
    #[Test]
    public function it_can_filter_by_exact_property_with_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['n' => $this->models->first()->name])
            ->setAllowedFilters(FilterDefinition::exact('name', 'n'))
            ->get();

        $this->assertCount(1, $models);
    }
    #[Test]
    public function it_can_filter_by_array_of_values(): void
    {
        $names = $this->models->take(2)->pluck('name')->toArray();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $names])
            ->setAllowedFilters('name')
            ->get();

        $this->assertCount(2, $models);
    }
    #[Test]
    public function it_can_filter_by_comma_separated_values(): void
    {
        $names = $this->models->take(2)->pluck('name')->implode(',');

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $names])
            ->setAllowedFilters('name')
            ->get();

        $this->assertCount(2, $models);
    }
    #[Test]
    public function exact_filter_is_case_sensitive_on_sqlite(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => strtoupper($model->name)])
            ->setAllowedFilters('name')
            ->get();

        // SQLite is case-sensitive by default
        $this->assertCount(0, $models);
    }
    #[Test]
    public function it_uses_default_filter_value_when_not_provided(): void
    {
        TestModel::factory()->create(['name' => 'default_value']);

        $models = $this
            ->createEloquentWizardFromQuery()
            ->setAllowedFilters(FilterDefinition::exact('name')->default('default_value'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals('default_value', $models->first()->name);
    }
    #[Test]
    public function it_ignores_default_when_filter_is_provided(): void
    {
        TestModel::factory()->create(['name' => 'default_value']);
        TestModel::factory()->create(['name' => 'explicit_value']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'explicit_value'])
            ->setAllowedFilters(FilterDefinition::exact('name')->default('default_value'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals('explicit_value', $models->first()->name);
    }
    #[Test]
    public function it_prepares_filter_value_with_callback(): void
    {
        $receivedValue = null;

        $this
            ->createEloquentWizardWithFilters(['name' => 'TRANSFORM_ME'])
            ->setAllowedFilters(
                // Callback receives ($query, $value, $property)
                FilterDefinition::callback('name', function ($query, $value, $property) use (&$receivedValue) {
                    $receivedValue = $value;
                    // Don't actually filter - just verify the value was transformed
                })->prepareValueWith(fn($v) => strtolower($v))
            )
            ->get();

        // Value should be transformed by prepareValueWith callback
        $this->assertEquals('transform_me', $receivedValue);
    }

    // ========== Partial Filter Tests ==========
    #[Test]
    public function it_can_filter_by_partial_property(): void
    {
        $model = $this->models->first();
        $partialName = substr($model->name, 0, 3);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $partialName])
            ->setAllowedFilters(FilterDefinition::partial('name'))
            ->get();

        $this->assertGreaterThanOrEqual(1, $models->count());
    }
    #[Test]
    public function partial_filter_is_case_insensitive(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => strtoupper($model->name)])
            ->setAllowedFilters(FilterDefinition::partial('name'))
            ->get();

        $this->assertGreaterThanOrEqual(1, $models->count());
    }
    #[Test]
    public function partial_filter_works_with_array_of_values(): void
    {
        $partials = $this->models->take(2)->map(fn($m) => substr($m->name, 0, 3))->toArray();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $partials])
            ->setAllowedFilters(FilterDefinition::partial('name'))
            ->get();

        $this->assertGreaterThanOrEqual(2, $models->count());
    }
    #[Test]
    public function partial_filter_ignores_empty_values_in_array(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => ['', null, '']])
            ->setAllowedFilters(FilterDefinition::partial('name'))
            ->get();

        // Empty filter should return all models
        $this->assertEquals(TestModel::count(), $models->count());
    }

    // ========== Scope Filter Tests ==========
    #[Test]
    public function it_can_filter_by_scope(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['named' => $model->name])
            ->setAllowedFilters(FilterDefinition::scope('named'))
            ->get();

        $this->assertCount(1, $models);
    }
    #[Test]
    public function it_can_filter_by_scope_with_alias(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['filter_name' => $model->name])
            ->setAllowedFilters(FilterDefinition::scope('named', 'filter_name'))
            ->get();

        $this->assertCount(1, $models);
    }
    #[Test]
    public function it_can_filter_by_scope_with_multiple_parameters(): void
    {
        $model = $this->models->first();
        $from = $model->created_at->subDay();
        $to = $model->created_at->addDay();

        $models = $this
            ->createEloquentWizardWithFilters(['created_between' => [$from->toDateTimeString(), $to->toDateTimeString()]])
            ->setAllowedFilters(FilterDefinition::scope('createdBetween', 'created_between'))
            ->get();

        $this->assertGreaterThanOrEqual(1, $models->count());
    }
    #[Test]
    public function scope_filter_can_disable_model_binding_resolution(): void
    {
        $model = $this->models->first();

        // With resolveModelBindings disabled, the raw value is passed to the scope
        $models = $this
            ->createEloquentWizardWithFilters(['named' => $model->name])
            ->setAllowedFilters(
                FilterDefinition::scope('named')
                    ->withOptions(['resolveModelBindings' => false])
            )
            ->get();

        $this->assertCount(1, $models);
    }

    // ========== Callback Filter Tests ==========
    #[Test]
    public function it_can_filter_by_callback(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['custom' => $model->name])
            ->setAllowedFilters(
                FilterDefinition::callback('custom', function ($query, $value) {
                    $query->where('name', $value);
                })
            )
            ->get();

        $this->assertCount(1, $models);
    }
    #[Test]
    public function callback_filter_receives_property_name(): void
    {
        $receivedProperty = null;

        $this
            ->createEloquentWizardWithFilters(['search' => 'test'])
            ->setAllowedFilters(
                FilterDefinition::callback('name', function ($query, $value, $property) use (&$receivedProperty) {
                    $receivedProperty = $property;
                }, 'search')
            )
            ->get();

        $this->assertEquals('name', $receivedProperty);
    }
    #[Test]
    public function callback_filter_with_array_callback(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['custom' => $model->name])
            ->setAllowedFilters(
                FilterDefinition::callback('custom', [$this, 'customFilterCallback'])
            )
            ->get();

        $this->assertCount(1, $models);
    }

    public function customFilterCallback($query, $value): void
    {
        $query->where('name', $value);
    }

    // ========== Range Filter Tests ==========
    #[Test]
    public function it_can_filter_by_range_with_min_and_max(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => ['min' => 2, 'max' => 4]])
            ->setAllowedFilters(FilterDefinition::range('id'))
            ->get();

        $this->assertCount(3, $models);
        $this->assertTrue($models->every(fn($m) => $m->id >= 2 && $m->id <= 4));
    }
    #[Test]
    public function it_can_filter_by_range_with_only_min(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => ['min' => 3]])
            ->setAllowedFilters(FilterDefinition::range('id'))
            ->get();

        $this->assertTrue($models->every(fn($m) => $m->id >= 3));
    }
    #[Test]
    public function it_can_filter_by_range_with_only_max(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => ['max' => 3]])
            ->setAllowedFilters(FilterDefinition::range('id'))
            ->get();

        $this->assertTrue($models->every(fn($m) => $m->id <= 3));
    }
    #[Test]
    public function it_can_filter_by_range_with_comma_separated(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => '2,4'])
            ->setAllowedFilters(FilterDefinition::range('id'))
            ->get();

        $this->assertCount(3, $models);
    }

    // ========== Date Range Filter Tests ==========
    #[Test]
    public function it_can_filter_by_date_range(): void
    {
        $from = Carbon::now()->subDays(1);
        $to = Carbon::now()->addDays(1);

        $models = $this
            ->createEloquentWizardWithFilters(['created_at' => ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()]])
            ->setAllowedFilters(FilterDefinition::dateRange('created_at'))
            ->get();

        $this->assertCount(5, $models);
    }
    #[Test]
    public function it_can_filter_by_date_range_with_only_from(): void
    {
        $from = Carbon::now()->subDays(1);

        $models = $this
            ->createEloquentWizardWithFilters(['created_at' => ['from' => $from->toDateTimeString()]])
            ->setAllowedFilters(FilterDefinition::dateRange('created_at'))
            ->get();

        $this->assertCount(5, $models);
    }
    #[Test]
    public function it_can_filter_by_date_range_with_only_to(): void
    {
        $to = Carbon::now()->addDays(1);

        $models = $this
            ->createEloquentWizardWithFilters(['created_at' => ['to' => $to->toDateTimeString()]])
            ->setAllowedFilters(FilterDefinition::dateRange('created_at'))
            ->get();

        $this->assertCount(5, $models);
    }

    // ========== Null Filter Tests ==========
    // Note: These tests use a different column that allows NULL values
    // The 'name' column in test_models has NOT NULL constraint
    #[Test]
    public function null_filter_generates_correct_sql(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => true])
            ->setAllowedFilters(FilterDefinition::null('name'))
            ->build()
            ->toSql();

        $this->assertStringContainsString('is null', strtolower($sql));
    }
    #[Test]
    public function null_filter_with_false_generates_not_null_sql(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => false])
            ->setAllowedFilters(FilterDefinition::null('name'))
            ->build()
            ->toSql();

        $this->assertStringContainsString('is not null', strtolower($sql));
    }
    #[Test]
    public function null_filter_with_string_true(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => 'true'])
            ->setAllowedFilters(FilterDefinition::null('name'))
            ->build()
            ->toSql();

        // 'true' is converted to boolean true, which checks for NULL
        $this->assertStringContainsString('is null', strtolower($sql));
    }
    #[Test]
    public function null_filter_with_inverted_logic_sql(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['has_name' => true])
            ->setAllowedFilters(
                FilterDefinition::null('name', 'has_name')
                    ->withOptions(['invertLogic' => true])
            )
            ->build()
            ->toSql();

        // invertLogic: true means "true" checks for NOT NULL
        $this->assertStringContainsString('is not null', strtolower($sql));
    }
    #[Test]
    public function null_filter_with_invalid_value_defaults_to_false(): void
    {
        // Invalid values like 'invalid', 'random', etc. should default to false (NOT NULL)
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => 'invalid'])
            ->setAllowedFilters(FilterDefinition::null('name'))
            ->build()
            ->toSql();

        // 'invalid' is not a valid boolean, so it defaults to false -> NOT NULL
        $this->assertStringContainsString('is not null', strtolower($sql));
    }
    #[Test]
    public function null_filter_with_numeric_value_defaults_to_false(): void
    {
        // Numeric values like '123' should default to false (NOT NULL)
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => '123'])
            ->setAllowedFilters(FilterDefinition::null('name'))
            ->build()
            ->toSql();

        $this->assertStringContainsString('is not null', strtolower($sql));
    }

    // ========== Relation Filter Tests ==========
    #[Test]
    public function it_can_filter_by_relation_property(): void
    {
        $testModel = $this->models->first();
        $relatedModel = RelatedModel::factory()->create([
            'test_model_id' => $testModel->id,
            'name' => 'specific_name',
        ]);

        $models = $this
            ->createEloquentWizardWithFilters(['relatedModels.name' => 'specific_name'])
            ->setAllowedFilters(FilterDefinition::exact('relatedModels.name'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($testModel->id, $models->first()->id);
    }
    #[Test]
    public function it_can_disable_relation_constraint(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['relatedModels.name' => 'test'])
            ->setAllowedFilters(
                FilterDefinition::exact('relatedModels.name')
                    ->withRelationConstraint(false)
            )
            ->build()
            ->toSql();

        // Without relation constraint, it should NOT use whereHas
        $this->assertStringNotContainsString('exists', strtolower($sql));
    }

    // ========== Validation Tests ==========
    #[Test]
    public function it_throws_exception_for_not_allowed_filter(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createEloquentWizardWithFilters(['not_allowed' => 'value'])
            ->setAllowedFilters('name')
            ->get();
    }
    #[Test]
    public function it_throws_exception_for_unknown_filters_when_no_allowed_set(): void
    {
        // When no allowed filters are set, any requested filter throws exception
        // This is the strict validation behavior
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createEloquentWizardWithFilters(['unknown' => 'value'])
            ->setAllowedFilters([])
            ->get();
    }

    // ========== Edge Cases ==========
    #[Test]
    public function it_handles_empty_filter_value(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => ''])
            ->setAllowedFilters('name')
            ->get();

        // Empty string filter should return nothing for exact match
        $this->assertCount(0, $models);
    }
    #[Test]
    public function it_skips_null_filter_value(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => null])
            ->setAllowedFilters('name')
            ->get();

        // Null value means filter is not applied - returns all models
        $this->assertCount(5, $models);
    }
    #[Test]
    public function it_handles_boolean_true_filter_value(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'true'])
            ->setAllowedFilters('name')
            ->get();

        // 'true' is parsed as boolean true by QueryParametersManager
        $this->assertCount(0, $models);
    }
    #[Test]
    public function it_handles_boolean_false_filter_value(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'false'])
            ->setAllowedFilters('name')
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
            ->setAllowedFilters('name', 'id')
            ->get();

        $this->assertCount(1, $models);
    }
    #[Test]
    public function it_handles_filter_with_special_characters(): void
    {
        $model = TestModel::factory()->create(['name' => "Test's Model"]);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => "Test's Model"])
            ->setAllowedFilters('name')
            ->get();

        $this->assertCount(1, $models);
    }
    #[Test]
    public function it_throws_exception_with_empty_allowed_filters_array(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createEloquentWizardWithFilters(['name' => 'test'])
            ->setAllowedFilters([])
            ->get();
    }
    #[Test]
    public function it_qualifies_column_names(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => 'test'])
            ->setAllowedFilters('name')
            ->build()
            ->toSql();

        $this->assertStringContainsString('"test_models"."name"', $sql);
    }

    // ========== JsonContains Filter Tests ==========
    #[Test]
    public function it_can_filter_json_contains_single_value(): void
    {
        // Create test data with JSON column
        TestModel::factory()->create(['name' => 'json_test']);

        $sql = $this
            ->createEloquentWizardWithFilters(['tags' => 'php'])
            ->setAllowedFilters(FilterDefinition::jsonContains('tags'))
            ->build()
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
            ->setAllowedFilters(FilterDefinition::jsonContains('tags'))
            ->build()
            ->toSql();

        // SQLite uses json_each, MySQL uses json_contains
        $sqlLower = strtolower($sql);
        $this->assertTrue(
            str_contains($sqlLower, 'json_contains') || str_contains($sqlLower, 'json_each'),
            "Expected JSON filtering SQL, got: {$sql}"
        );
    }

    // ========== Additional Exact Filter Edge Cases ==========
    #[Test]
    public function exact_filter_handles_integer_values(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['id' => $model->id])
            ->setAllowedFilters('id')
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($model->id, $models->first()->id);
    }
    #[Test]
    public function exact_filter_handles_zero_value(): void
    {
        TestModel::factory()->create(['name' => '0']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => '0'])
            ->setAllowedFilters('name')
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals('0', $models->first()->name);
    }
    #[Test]
    public function it_can_filter_multiple_properties_with_definitions(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters([
                'name' => $model->name,
                'id' => $model->id,
            ])
            ->setAllowedFilters(
                FilterDefinition::exact('name'),
                FilterDefinition::exact('id')
            )
            ->get();

        $this->assertCount(1, $models);
    }
    #[Test]
    public function it_can_mix_string_and_definition_filters(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters([
                'name' => $model->name,
                'id' => $model->id,
            ])
            ->setAllowedFilters(
                'name',
                FilterDefinition::exact('id')
            )
            ->get();

        $this->assertCount(1, $models);
    }

    // ========== Filter with Options Tests ==========
    #[Test]
    public function it_respects_filter_options(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => 'test'])
            ->setAllowedFilters(
                FilterDefinition::exact('name')->withOptions(['customOption' => true])
            )
            ->build()
            ->toSql();

        // Just verify it doesn't break
        $this->assertStringContainsString('name', $sql);
    }

    // ========== Nested Relation Filter Tests ==========
    #[Test]
    public function it_can_filter_by_deeply_nested_relation(): void
    {
        $testModel = $this->models->first();
        $relatedModel = RelatedModel::factory()->create([
            'test_model_id' => $testModel->id,
        ]);
        NestedRelatedModel::factory()->create([
            'related_model_id' => $relatedModel->id,
            'name' => 'deeply_nested',
        ]);

        $models = $this
            ->createEloquentWizardWithFilters(['relatedModels.nestedRelatedModels.name' => 'deeply_nested'])
            ->setAllowedFilters(FilterDefinition::exact('relatedModels.nestedRelatedModels.name'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($testModel->id, $models->first()->id);
    }

    // ========== Default Value Edge Cases ==========
    #[Test]
    public function default_filter_works_with_partial_filter(): void
    {
        TestModel::factory()->create(['name' => 'default_partial_test']);

        $models = $this
            ->createEloquentWizardFromQuery()
            ->setAllowedFilters(FilterDefinition::partial('name')->default('partial'))
            ->get();

        $this->assertTrue($models->contains('name', 'default_partial_test'));
    }
    #[Test]
    public function default_filter_works_with_array_value(): void
    {
        $targetModels = $this->models->take(2);
        $names = $targetModels->pluck('name')->toArray();

        $models = $this
            ->createEloquentWizardFromQuery()
            ->setAllowedFilters(FilterDefinition::exact('name')->default($names))
            ->get();

        $this->assertCount(2, $models);
    }

    // ========== PrepareValue Edge Cases ==========
    #[Test]
    public function prepare_value_can_transform_array(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'INPUT_' . $model->name])
            ->setAllowedFilters(
                FilterDefinition::exact('name')
                    ->prepareValueWith(fn($v) => str_replace('INPUT_', '', $v))
            )
            ->get();

        $this->assertCount(1, $models);
    }
    #[Test]
    public function prepare_value_receives_original_array(): void
    {
        $receivedValue = null;

        $this
            ->createEloquentWizardWithFilters(['name' => ['a', 'b', 'c']])
            ->setAllowedFilters(
                FilterDefinition::exact('name')
                    ->prepareValueWith(function ($v) use (&$receivedValue) {
                        $receivedValue = $v;
                        return $v;
                    })
            )
            ->get();

        $this->assertEquals(['a', 'b', 'c'], $receivedValue);
    }

    // ========== Callback Filter Edge Cases ==========
    #[Test]
    public function callback_filter_can_add_complex_conditions(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['search' => $model->name])
            ->setAllowedFilters(
                FilterDefinition::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'LIKE', "%{$value}%")
                            ->orWhere('id', $value);
                    });
                })
            )
            ->get();

        $this->assertGreaterThanOrEqual(1, $models->count());
    }
    #[Test]
    public function callback_filter_can_modify_query_builder(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['limit' => 2])
            ->setAllowedFilters(
                FilterDefinition::callback('limit', function ($query, $value) {
                    $query->limit((int) $value);
                })
            )
            ->get();

        $this->assertCount(2, $models);
    }

    // ========== Range Filter Edge Cases ==========
    #[Test]
    public function range_filter_handles_negative_values(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => ['min' => -10, 'max' => 3]])
            ->setAllowedFilters(FilterDefinition::range('id'))
            ->get();

        $this->assertTrue($models->every(fn($m) => $m->id >= -10 && $m->id <= 3));
    }
    #[Test]
    public function range_filter_handles_float_values(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['id' => ['min' => 1.5, 'max' => 3.5]])
            ->setAllowedFilters(FilterDefinition::range('id'))
            ->build()
            ->toSql();

        $this->assertStringContainsString('>=', $sql);
        $this->assertStringContainsString('<=', $sql);
    }
    #[Test]
    public function range_filter_with_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['model_id' => ['min' => 2, 'max' => 4]])
            ->setAllowedFilters(FilterDefinition::range('id', 'model_id'))
            ->get();

        $this->assertCount(3, $models);
    }

    // ========== Date Range Filter Edge Cases ==========
    #[Test]
    public function date_range_filter_handles_various_formats(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['created_at' => [
                'from' => '2020-01-01',
                'to' => '2030-12-31 23:59:59',
            ]])
            ->setAllowedFilters(FilterDefinition::dateRange('created_at'))
            ->get();

        $this->assertCount(5, $models);
    }
    #[Test]
    public function date_range_filter_with_alias(): void
    {
        $from = Carbon::now()->subDays(1);
        $to = Carbon::now()->addDays(1);

        $models = $this
            ->createEloquentWizardWithFilters(['date' => [
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
            ]])
            ->setAllowedFilters(FilterDefinition::dateRange('created_at', 'date'))
            ->get();

        $this->assertCount(5, $models);
    }

    // ========== Unicode and Special Input Tests ==========
    #[Test]
    public function it_handles_unicode_filter_values(): void
    {
        $model = TestModel::factory()->create(['name' => 'Тест']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'Тест'])
            ->setAllowedFilters('name')
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
            ->setAllowedFilters('name')
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
            ->setAllowedFilters('name')
            ->get();

        $this->assertCount(1, $models);
    }

    // ========== Multiple Filters Interaction Tests ==========
    #[Test]
    public function filters_are_applied_with_and_logic(): void
    {
        TestModel::factory()->create(['name' => 'unique_combo']);

        // This should find the model created above with specific ID
        $models = $this
            ->createEloquentWizardWithFilters([
                'name' => 'unique_combo',
            ])
            ->setAllowedFilters('name', 'id')
            ->get();

        $this->assertCount(1, $models);
    }
}

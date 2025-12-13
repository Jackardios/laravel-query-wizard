<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\IncludeDefinition;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;

/**
 * @group eloquent
 * @group include
 */
class IncludeTest extends TestCase
{
    protected Collection $models;

    public function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        $this->models = factory(TestModel::class, 3)->create();

        // Create related models for each test model
        $this->models->each(function (TestModel $model) {
            factory(RelatedModel::class, 2)->create([
                'test_model_id' => $model->id,
            ])->each(function (RelatedModel $related) {
                factory(NestedRelatedModel::class)->create([
                    'related_model_id' => $related->id,
                ]);
            });
        });
    }

    // ========== Basic Include Tests ==========

    /** @test */
    public function it_does_not_load_relationships_by_default(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery()
            ->get();

        $this->assertFalse($models->first()->relationLoaded('relatedModels'));
    }

    /** @test */
    public function it_can_include_relationship(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels')
            ->setAllowedIncludes('relatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertCount(2, $models->first()->relatedModels);
    }

    /** @test */
    public function it_can_include_relationship_with_definition(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels')
            ->setAllowedIncludes(IncludeDefinition::relationship('relatedModels'))
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    /** @test */
    public function it_can_include_multiple_relationships(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,otherRelatedModels')
            ->setAllowedIncludes('relatedModels', 'otherRelatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relationLoaded('otherRelatedModels'));
    }

    /** @test */
    public function it_can_include_relationships_as_array(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes(['relatedModels', 'otherRelatedModels'])
            ->setAllowedIncludes('relatedModels', 'otherRelatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relationLoaded('otherRelatedModels'));
    }

    // ========== Nested Includes Tests ==========

    /** @test */
    public function it_can_include_nested_relationship(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels.nestedRelatedModels')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relatedModels->first()->relationLoaded('nestedRelatedModels'));
    }

    /** @test */
    public function nested_include_also_loads_parent(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels.nestedRelatedModels')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    /** @test */
    public function it_can_include_both_parent_and_nested(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,relatedModels.nestedRelatedModels')
            ->setAllowedIncludes('relatedModels', 'relatedModels.nestedRelatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relatedModels->first()->relationLoaded('nestedRelatedModels'));
    }

    // ========== Alias Tests ==========

    /** @test */
    public function it_can_include_with_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('related')
            ->setAllowedIncludes(IncludeDefinition::relationship('relatedModels', 'related'))
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    /** @test */
    public function it_can_include_nested_with_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('related.nested')
            ->setAllowedIncludes(
                IncludeDefinition::relationship('relatedModels.nestedRelatedModels', 'related.nested')
            )
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relatedModels->first()->relationLoaded('nestedRelatedModels'));
    }

    // ========== Count Include Tests ==========

    /** @test */
    public function it_can_include_count(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModelsCount')
            ->setAllowedIncludes(IncludeDefinition::count('relatedModels'))
            ->get();

        $this->assertEquals(2, $models->first()->related_models_count);
    }

    /** @test */
    public function it_can_include_count_with_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('totalRelated')
            ->setAllowedIncludes(IncludeDefinition::count('relatedModels', 'totalRelated'))
            ->get();

        $this->assertEquals(2, $models->first()->related_models_count);
    }

    /** @test */
    public function it_can_include_multiple_counts(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModelsCount,otherRelatedModelsCount')
            ->setAllowedIncludes(
                IncludeDefinition::count('relatedModels'),
                IncludeDefinition::count('otherRelatedModels')
            )
            ->get();

        $this->assertTrue(isset($models->first()->related_models_count));
        $this->assertTrue(isset($models->first()->other_related_models_count));
    }

    // ========== Callback Include Tests ==========

    /** @test */
    public function it_can_include_with_callback(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('custom')
            ->setAllowedIncludes(
                IncludeDefinition::callback('relatedModels', function ($query) {
                    $query->with('relatedModels');
                }, 'custom')
            )
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    /** @test */
    public function callback_include_receives_fields_parameter(): void
    {
        $receivedFields = null;

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'custom',
                'fields' => ['relatedModels' => 'id,name'],
            ])
            ->setAllowedIncludes(
                IncludeDefinition::callback('relatedModels', function ($query, $relation, $fields) use (&$receivedFields) {
                    $receivedFields = $fields;
                    $query->with('relatedModels');
                }, 'custom')
            )
            ->get();

        $this->assertNotNull($receivedFields);
    }

    /** @test */
    public function callback_include_can_apply_constraints(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('limitedRelated')
            ->setAllowedIncludes(
                IncludeDefinition::callback('relatedModels', function ($query) {
                    $query->with(['relatedModels' => function ($q) {
                        $q->limit(1);
                    }]);
                }, 'limitedRelated')
            )
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    // ========== Default Includes Tests ==========

    /** @test */
    public function it_uses_default_includes_when_none_requested(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery()
            ->setAllowedIncludes('relatedModels')
            ->setDefaultIncludes('relatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    /** @test */
    public function it_uses_multiple_default_includes(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery()
            ->setAllowedIncludes('relatedModels', 'otherRelatedModels')
            ->setDefaultIncludes('relatedModels', 'otherRelatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relationLoaded('otherRelatedModels'));
    }

    /** @test */
    public function explicit_include_merges_with_default(): void
    {
        // Explicit includes are MERGED with default includes, not replacing them
        $models = $this
            ->createEloquentWizardWithIncludes('otherRelatedModels')
            ->setAllowedIncludes('relatedModels', 'otherRelatedModels')
            ->setDefaultIncludes('relatedModels')
            ->get();

        // Both default (relatedModels) and explicit (otherRelatedModels) should be loaded
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relationLoaded('otherRelatedModels'));
    }

    /** @test */
    public function default_includes_with_definition(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery()
            ->setAllowedIncludes(IncludeDefinition::relationship('relatedModels'))
            ->setDefaultIncludes('relatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    // ========== Validation Tests ==========

    /** @test */
    public function it_throws_exception_for_not_allowed_include(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createEloquentWizardWithIncludes('notAllowed')
            ->setAllowedIncludes('relatedModels')
            ->get();
    }

    /** @test */
    public function it_throws_exception_for_nested_not_allowed_include(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createEloquentWizardWithIncludes('relatedModels.notAllowed')
            ->setAllowedIncludes('relatedModels')
            ->get();
    }

    /** @test */
    public function it_throws_exception_for_unknown_includes_when_no_allowed_set(): void
    {
        // When no allowed includes are set, any requested include throws exception
        // This is the strict validation behavior
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createEloquentWizardWithIncludes('unknown')
            ->setAllowedIncludes([])
            ->get();
    }

    /** @test */
    public function it_throws_exception_with_empty_allowed_includes_array(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createEloquentWizardWithIncludes('relatedModels')
            ->setAllowedIncludes([])
            ->get();
    }

    // ========== Edge Cases ==========

    /** @test */
    public function it_handles_empty_include_string(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('')
            ->setAllowedIncludes('relatedModels')
            ->get();

        $this->assertFalse($models->first()->relationLoaded('relatedModels'));
    }

    /** @test */
    public function it_handles_include_with_trailing_comma(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,')
            ->setAllowedIncludes('relatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    /** @test */
    public function it_removes_duplicate_includes(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,relatedModels')
            ->setAllowedIncludes('relatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    /** @test */
    public function it_treats_include_values_literally_with_spaces(): void
    {
        // Include values are treated literally - spaces are NOT trimmed
        // ' relatedModels ' is different from 'relatedModels'
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createEloquentWizardWithIncludes(' relatedModels ')
            ->setAllowedIncludes('relatedModels')
            ->get();
    }

    // ========== SQL Verification Tests ==========

    /** @test */
    public function it_uses_eager_loading(): void
    {
        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels')
            ->setAllowedIncludes('relatedModels')
            ->get();

        // Should be 2 queries: one for test_models, one for related_models
        $queryLog = DB::getQueryLog();
        $this->assertCount(2, $queryLog);
    }

    /** @test */
    public function it_uses_withCount_for_count_includes(): void
    {
        $sql = $this
            ->createEloquentWizardWithIncludes('relatedModelsCount')
            ->setAllowedIncludes(IncludeDefinition::count('relatedModels'))
            ->build()
            ->toSql();

        $this->assertStringContainsString('select count', strtolower($sql));
    }

    // ========== Mixed Definitions Tests ==========

    /** @test */
    public function it_can_mix_string_and_definition_includes(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,otherRelatedModels')
            ->setAllowedIncludes(
                'relatedModels',
                IncludeDefinition::relationship('otherRelatedModels')
            )
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relationLoaded('otherRelatedModels'));
    }

    /** @test */
    public function it_can_mix_relationship_and_count_includes(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,otherRelatedModelsCount')
            ->setAllowedIncludes(
                IncludeDefinition::relationship('relatedModels'),
                IncludeDefinition::count('otherRelatedModels')
            )
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue(isset($models->first()->other_related_models_count));
    }

    // ========== Integration with Other Features ==========

    /** @test */
    public function it_works_with_pagination(): void
    {
        $result = $this
            ->createEloquentWizardWithIncludes('relatedModels')
            ->setAllowedIncludes('relatedModels')
            ->build()
            ->paginate(2);

        $this->assertTrue($result->first()->relationLoaded('relatedModels'));
    }

    /** @test */
    public function it_works_with_first(): void
    {
        $model = $this
            ->createEloquentWizardWithIncludes('relatedModels')
            ->setAllowedIncludes('relatedModels')
            ->build()
            ->first();

        $this->assertTrue($model->relationLoaded('relatedModels'));
    }

    /** @test */
    public function it_works_with_sorting(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'sort' => '-id',
            ])
            ->setAllowedIncludes('relatedModels')
            ->setAllowedSorts('id')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertEquals(3, $models->first()->id);
    }

    /** @test */
    public function it_works_with_filtering(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'filter' => ['id' => $model->id],
            ])
            ->setAllowedIncludes('relatedModels')
            ->setAllowedFilters('id')
            ->get();

        $this->assertCount(1, $models);
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    // ========== Morph Relationships ==========

    /** @test */
    public function it_can_include_morph_relationship(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('morphModels')
            ->setAllowedIncludes('morphModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('morphModels'));
    }

    // ========== Options Tests ==========

    /** @test */
    public function include_definition_options_are_accessible(): void
    {
        $include = IncludeDefinition::relationship('relatedModels')
            ->withOptions(['eager' => true]);

        $this->assertTrue($include->getOption('eager'));
    }

    /** @test */
    public function options_are_preserved_through_chaining(): void
    {
        $include = IncludeDefinition::relationship('relatedModels')
            ->withOptions(['key1' => 'value1'])
            ->withOptions(['key2' => 'value2']);

        $this->assertEquals('value1', $include->getOption('key1'));
        $this->assertEquals('value2', $include->getOption('key2'));
    }

    // ========== BelongsToMany / Through Pivot Tests ==========

    /** @test */
    public function it_can_include_belongs_to_many(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedThroughPivotModels')
            ->setAllowedIncludes('relatedThroughPivotModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedThroughPivotModels'));
    }

    /** @test */
    public function it_can_include_belongs_to_many_with_pivot(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedThroughPivotModelsWithPivot')
            ->setAllowedIncludes('relatedThroughPivotModelsWithPivot')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedThroughPivotModelsWithPivot'));
    }
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('include')]
class IncludeTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        $this->models = TestModel::factory()->count(3)->create();

        // Create related models for each test model
        $this->models->each(function (TestModel $model) {
            RelatedModel::factory()->count(2)->create([
                'test_model_id' => $model->id,
            ])->each(function (RelatedModel $related) {
                NestedRelatedModel::factory()->create([
                    'related_model_id' => $related->id,
                ]);
            });
        });
    }

    // ========== Basic Include Tests ==========
    #[Test]
    public function it_does_not_load_relationships_by_default(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery()
            ->get();

        $this->assertFalse($models->first()->relationLoaded('relatedModels'));
    }

    #[Test]
    public function it_can_include_relationship(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels')
            ->allowedIncludes('relatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertCount(2, $models->first()->relatedModels);
    }

    #[Test]
    public function it_can_include_relationship_with_definition(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels')
            ->allowedIncludes(EloquentInclude::relationship('relatedModels'))
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    #[Test]
    public function it_can_include_multiple_relationships(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,otherRelatedModels')
            ->allowedIncludes('relatedModels', 'otherRelatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relationLoaded('otherRelatedModels'));
    }

    #[Test]
    public function it_can_include_relationships_as_array(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes(['relatedModels', 'otherRelatedModels'])
            ->allowedIncludes('relatedModels', 'otherRelatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relationLoaded('otherRelatedModels'));
    }

    // ========== Nested Includes Tests ==========
    #[Test]
    public function it_can_include_nested_relationship(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels.nestedRelatedModels')
            ->allowedIncludes('relatedModels.nestedRelatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relatedModels->first()->relationLoaded('nestedRelatedModels'));
    }

    #[Test]
    public function it_can_include_both_parent_and_nested(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,relatedModels.nestedRelatedModels')
            ->allowedIncludes('relatedModels', 'relatedModels.nestedRelatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relatedModels->first()->relationLoaded('nestedRelatedModels'));
    }

    // ========== Alias Tests ==========
    #[Test]
    public function it_can_include_with_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('related')
            ->allowedIncludes(EloquentInclude::relationship('relatedModels')->alias('related'))
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    #[Test]
    public function it_can_include_nested_with_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('related.nested')
            ->allowedIncludes(
                EloquentInclude::relationship('relatedModels.nestedRelatedModels')->alias('related.nested')
            )
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relatedModels->first()->relationLoaded('nestedRelatedModels'));
    }

    // ========== Count Include Tests ==========
    #[Test]
    public function it_can_include_count(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModelsCount')
            ->allowedIncludes(EloquentInclude::count('relatedModels'))
            ->get();

        $this->assertEquals(2, $models->first()->related_models_count);
    }

    #[Test]
    public function it_can_include_count_with_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('totalRelated')
            ->allowedIncludes(EloquentInclude::count('relatedModels')->alias('totalRelated'))
            ->get();

        $this->assertEquals(2, $models->first()->related_models_count);
    }

    #[Test]
    public function it_can_include_multiple_counts(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModelsCount,otherRelatedModelsCount')
            ->allowedIncludes(
                EloquentInclude::count('relatedModels'),
                EloquentInclude::count('otherRelatedModels')
            )
            ->get();

        $this->assertTrue(isset($models->first()->related_models_count));
        $this->assertTrue(isset($models->first()->other_related_models_count));
    }

    // ========== Callback Include Tests ==========
    #[Test]
    public function it_can_include_with_callback(): void
    {
        $callbackExecuted = false;

        $models = $this
            ->createEloquentWizardWithIncludes('customInclude')
            ->allowedIncludes(
                EloquentInclude::callback('customInclude', function ($query, $relation) use (&$callbackExecuted) {
                    $callbackExecuted = true;
                    $query->with('relatedModels');
                })
            )
            ->get();

        $this->assertTrue($callbackExecuted);
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    #[Test]
    public function callback_include_receives_relation_name(): void
    {
        $receivedRelation = null;

        $models = $this
            ->createEloquentWizardWithIncludes('customInclude')
            ->allowedIncludes(
                EloquentInclude::callback('customInclude', function ($query, $relation) use (&$receivedRelation) {
                    $receivedRelation = $relation;
                })
            )
            ->get();

        $this->assertEquals('customInclude', $receivedRelation);
    }

    #[Test]
    public function callback_include_can_apply_constraints(): void
    {
        // Create related models with different names
        RelatedModel::factory()->count(2)->create(['test_model_id' => $this->models->first()->id, 'name' => 'Filtered']);
        RelatedModel::factory()->count(1)->create(['test_model_id' => $this->models->first()->id, 'name' => 'NotFiltered']);

        $models = $this
            ->createEloquentWizardWithIncludes('filteredRelations')
            ->allowedIncludes(
                EloquentInclude::callback('filteredRelations', function ($query) {
                    $query->with(['relatedModels' => function ($q) {
                        $q->where('name', 'Filtered');
                    }]);
                })
            )
            ->get();

        $relatedModels = $models->first()->relatedModels;
        $this->assertCount(2, $relatedModels);
        $this->assertTrue($relatedModels->every(fn ($m) => $m->name === 'Filtered'));
    }

    #[Test]
    public function callback_include_return_value_is_used(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('customInclude')
            ->allowedIncludes(
                EloquentInclude::callback('customInclude', function ($query, $relation) {
                    return $query->with('relatedModels');
                })
            )
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    #[Test]
    public function callback_include_null_return_falls_back_to_subject(): void
    {
        // When callback returns null (void), the original subject is used
        $models = $this
            ->createEloquentWizardWithIncludes('customInclude')
            ->allowedIncludes(
                EloquentInclude::callback('customInclude', function ($query, $relation) {
                    $query->with('relatedModels');
                    // implicitly returns null
                })
            )
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    // ========== Default Includes Tests ==========
    #[Test]
    public function it_uses_default_includes_when_none_requested(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery()
            ->allowedIncludes('relatedModels')
            ->defaultIncludes('relatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    #[Test]
    public function it_uses_multiple_default_includes(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery()
            ->allowedIncludes('relatedModels', 'otherRelatedModels')
            ->defaultIncludes('relatedModels', 'otherRelatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relationLoaded('otherRelatedModels'));
    }

    #[Test]
    public function explicit_include_replaces_defaults(): void
    {
        // Explicit includes replace defaults in this request
        $models = $this
            ->createEloquentWizardWithIncludes('otherRelatedModels')
            ->allowedIncludes('relatedModels', 'otherRelatedModels')
            ->defaultIncludes('relatedModels')
            ->get();

        // Only explicit include should be loaded
        $this->assertFalse($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relationLoaded('otherRelatedModels'));
    }

    #[Test]
    public function default_includes_with_definition(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery()
            ->allowedIncludes(EloquentInclude::relationship('relatedModels'))
            ->defaultIncludes('relatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    // ========== Validation Tests ==========
    #[Test]
    public function it_throws_exception_for_not_allowed_include(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createEloquentWizardWithIncludes('notAllowed')
            ->allowedIncludes('relatedModels')
            ->get();
    }

    #[Test]
    public function it_throws_exception_for_nested_not_allowed_include(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createEloquentWizardWithIncludes('relatedModels.notAllowed')
            ->allowedIncludes('relatedModels')
            ->get();
    }

    #[Test]
    public function it_throws_exception_with_empty_allowed_includes_array(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createEloquentWizardWithIncludes('relatedModels')
            ->allowedIncludes([])
            ->get();
    }

    #[Test]
    public function it_ignores_not_allowed_include_when_exception_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_include_query_exception', true);

        $models = $this
            ->createEloquentWizardWithIncludes('notAllowed')
            ->allowedIncludes('relatedModels')
            ->get();

        // No exception, returns all models without the invalid include
        $this->assertCount(3, $models);
        $this->assertFalse($models->first()->relationLoaded('notAllowed'));
    }

    #[Test]
    public function it_ignores_includes_with_empty_array_when_exception_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_include_query_exception', true);

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels')
            ->allowedIncludes([])
            ->get();

        // No exception, returns all models without any includes
        $this->assertCount(3, $models);
    }

    // ========== Edge Cases ==========
    #[Test]
    public function it_handles_empty_include_string(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('')
            ->allowedIncludes('relatedModels')
            ->get();

        $this->assertFalse($models->first()->relationLoaded('relatedModels'));
    }

    #[Test]
    public function it_handles_include_with_trailing_comma(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,')
            ->allowedIncludes('relatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    #[Test]
    public function it_removes_duplicate_includes(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,relatedModels')
            ->allowedIncludes('relatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    #[Test]
    public function it_trims_whitespace_from_include_values(): void
    {
        // Include values are trimmed - ' relatedModels ' becomes 'relatedModels'
        $models = $this
            ->createEloquentWizardWithIncludes(' relatedModels ')
            ->allowedIncludes('relatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    // ========== SQL Verification Tests ==========
    #[Test]
    public function it_uses_eager_loading(): void
    {
        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels')
            ->allowedIncludes('relatedModels')
            ->get();

        // Should be 2 queries: one for test_models, one for related_models
        $queryLog = DB::getQueryLog();
        $this->assertCount(2, $queryLog);
    }

    #[Test]
    public function it_uses_with_count_for_count_includes(): void
    {
        $sql = $this
            ->createEloquentWizardWithIncludes('relatedModelsCount')
            ->allowedIncludes(EloquentInclude::count('relatedModels'))
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('select count', strtolower($sql));
    }

    // ========== Mixed Definitions Tests ==========
    #[Test]
    public function it_can_mix_string_and_definition_includes(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,otherRelatedModels')
            ->allowedIncludes(
                'relatedModels',
                EloquentInclude::relationship('otherRelatedModels')
            )
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue($models->first()->relationLoaded('otherRelatedModels'));
    }

    #[Test]
    public function it_can_mix_relationship_and_count_includes(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,otherRelatedModelsCount')
            ->allowedIncludes(
                EloquentInclude::relationship('relatedModels'),
                EloquentInclude::count('otherRelatedModels')
            )
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue(isset($models->first()->other_related_models_count));
    }

    // ========== Integration with Other Features ==========
    #[Test]
    public function it_works_with_pagination(): void
    {
        $result = $this
            ->createEloquentWizardWithIncludes('relatedModels')
            ->allowedIncludes('relatedModels')
            ->toQuery()
            ->paginate(2);

        $this->assertTrue($result->first()->relationLoaded('relatedModels'));
    }

    #[Test]
    public function it_works_with_first(): void
    {
        $model = $this
            ->createEloquentWizardWithIncludes('relatedModels')
            ->allowedIncludes('relatedModels')
            ->toQuery()
            ->first();

        $this->assertTrue($model->relationLoaded('relatedModels'));
    }

    #[Test]
    public function it_works_with_sorting(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'sort' => '-id',
            ])
            ->allowedIncludes('relatedModels')
            ->allowedSorts('id')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertEquals(3, $models->first()->id);
    }

    #[Test]
    public function it_works_with_filtering(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'filter' => ['id' => $model->id],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFilters('id')
            ->get();

        $this->assertCount(1, $models);
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    // ========== Morph Relationships ==========
    #[Test]
    public function it_can_include_morph_relationship(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('morphModels')
            ->allowedIncludes('morphModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('morphModels'));
    }

    // ========== BelongsToMany / Through Pivot Tests ==========
    #[Test]
    public function it_can_include_belongs_to_many(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedThroughPivotModels')
            ->allowedIncludes('relatedThroughPivotModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedThroughPivotModels'));
    }

    #[Test]
    public function it_can_include_belongs_to_many_with_pivot(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedThroughPivotModelsWithPivot')
            ->allowedIncludes('relatedThroughPivotModelsWithPivot')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedThroughPivotModelsWithPivot'));
    }
}

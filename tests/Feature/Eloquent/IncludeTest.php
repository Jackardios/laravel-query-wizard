<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use Jackardios\QueryWizard\Includes\AbstractInclude;
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
    public function it_can_include_belongs_to_through_relation_with_sparse_fields(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'throughTestModel',
                'fields' => [
                    'nestedRelatedModel' => 'id,name',
                    'throughTestModel' => 'id,name',
                ],
            ], NestedRelatedModel::class)
            ->allowedIncludes('throughTestModel')
            ->allowedFields('id', 'name', 'throughTestModel.id', 'throughTestModel.name')
            ->get();

        $model = $models->first();

        $this->assertInstanceOf(NestedRelatedModel::class, $model);
        $this->assertTrue($model->relationLoaded('throughTestModel'));
        $this->assertNotNull($model->throughTestModel);
        $this->assertSame($model->relatedModel->test_model_id, $model->throughTestModel->id);
        $this->assertContains('related_model_id', array_keys($model->getAttributes()));
        $this->assertArrayNotHasKey('related_model_id', $model->toArray());
        $relatedAttributes = array_keys($model->throughTestModel->getAttributes());
        $this->assertContains('id', $relatedAttributes);
        $this->assertContains('name', $relatedAttributes);
        $this->assertNotContains('created_at', $relatedAttributes);
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

    #[Test]
    public function it_matches_includes_after_snake_case_conversion(): void
    {
        config()->set('query-wizard.naming.convert_parameters_to_snake_case', true);

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels')
            ->allowedIncludes('relatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    #[Test]
    public function it_matches_include_aliases_after_snake_case_conversion(): void
    {
        config()->set('query-wizard.naming.convert_parameters_to_snake_case', true);

        $models = $this
            ->createEloquentWizardWithIncludes('relatedItems')
            ->allowedIncludes(EloquentInclude::relationship('relatedModels')->alias('relatedItems'))
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
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
    public function explicit_empty_include_disables_default_includes(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('')
            ->allowedIncludes('relatedModels')
            ->defaultIncludes('relatedModels')
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

    // ========== Exists Include Tests ==========
    #[Test]
    public function it_can_include_exists(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModelsExists')
            ->allowedIncludes(EloquentInclude::exists('relatedModels'))
            ->get();

        $this->assertTrue(isset($models->first()->related_models_exists));
        $this->assertTrue($models->first()->related_models_exists);
    }

    #[Test]
    public function it_can_include_exists_with_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('hasRelated')
            ->allowedIncludes(EloquentInclude::exists('relatedModels')->alias('hasRelated'))
            ->get();

        $this->assertTrue(isset($models->first()->related_models_exists));
    }

    #[Test]
    public function it_can_include_multiple_exists(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModelsExists,otherRelatedModelsExists')
            ->allowedIncludes(
                EloquentInclude::exists('relatedModels'),
                EloquentInclude::exists('otherRelatedModels')
            )
            ->get();

        $this->assertTrue(isset($models->first()->related_models_exists));
        $this->assertTrue(isset($models->first()->other_related_models_exists));
    }

    #[Test]
    public function it_returns_false_exists_for_empty_relations(): void
    {
        // Create a model without related models
        $emptyModel = TestModel::factory()->create();

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModelsExists')
            ->allowedIncludes(EloquentInclude::exists('relatedModels'))
            ->get();

        $modelWithoutRelated = $models->firstWhere('id', $emptyModel->id);
        $this->assertFalse($modelWithoutRelated->related_models_exists);
    }

    #[Test]
    public function exists_include_is_auto_detected_by_suffix(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModelsExists')
            ->allowedIncludes('relatedModelsExists')
            ->get();

        $this->assertTrue(isset($models->first()->related_models_exists));
    }

    #[Test]
    public function it_can_mix_relationship_count_and_exists_includes(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,relatedModelsCount,relatedModelsExists')
            ->allowedIncludes(
                EloquentInclude::relationship('relatedModels'),
                EloquentInclude::count('relatedModels'),
                EloquentInclude::exists('relatedModels')
            )
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue(isset($models->first()->related_models_count));
        $this->assertTrue(isset($models->first()->related_models_exists));
    }

    #[Test]
    public function exists_include_uses_with_exists(): void
    {
        $sql = $this
            ->createEloquentWizardWithIncludes('relatedModelsExists')
            ->allowedIncludes(EloquentInclude::exists('relatedModels'))
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('exists', strtolower($sql));
    }

    // ========== Eager-Load Registration Tests ==========
    #[Test]
    public function client_include_keeps_the_developer_constraint_on_the_same_relation(): void
    {
        $kept = RelatedModel::query()->where('test_model_id', $this->models->first()->id)->firstOrFail();

        $models = $this
            ->createEloquentWizardWithIncludes(
                'relatedModels',
                TestModel::query()->with(['relatedModels' => fn ($query) => $query->whereKey($kept->id)])
            )
            ->allowedIncludes('relatedModels')
            ->get();

        $this->assertSame([[$kept->id], [], []], $models->map(fn ($model) => $model->relatedModels->modelKeys())->all());
    }

    #[Test]
    public function narrowed_client_include_keeps_the_developer_constraint_too(): void
    {
        $kept = RelatedModel::query()->where('test_model_id', $this->models->first()->id)->firstOrFail();

        $models = $this
            ->createEloquentWizardFromQuery(
                ['include' => 'relatedModels', 'fields' => ['relatedModels' => 'id']],
                TestModel::query()->with(['relatedModels' => fn ($query) => $query->whereKey($kept->id)])
            )
            ->allowedIncludes('relatedModels')
            ->allowedFields('relatedModels.id')
            ->get();

        $this->assertSame([[$kept->id], [], []], $models->map(fn ($model) => $model->relatedModels->modelKeys())->all());
        $this->assertSame(['id'], array_keys($models->first()->relatedModels->first()->toArray()));
    }

    #[Test]
    public function nested_include_keeps_the_constraint_of_a_callback_include_on_its_parent(): void
    {
        $kept = RelatedModel::query()->where('test_model_id', $this->models->first()->id)->firstOrFail();

        $models = $this
            ->createEloquentWizardWithIncludes('onlyKept,relatedModels.nestedRelatedModels')
            ->allowedIncludes(
                EloquentInclude::callback('onlyKept', fn ($query) => $query->with([
                    'relatedModels' => fn ($related) => $related->whereKey($kept->id),
                ])),
                'relatedModels.nestedRelatedModels'
            )
            ->get();

        $this->assertSame([[$kept->id], [], []], $models->map(fn ($model) => $model->relatedModels->modelKeys())->all());
        $this->assertTrue($models->first()->relatedModels->first()->relationLoaded('nestedRelatedModels'));
    }

    #[Test]
    public function include_order_does_not_change_the_narrowed_relation_query(): void
    {
        $relatedQueries = [];

        foreach (['relatedModels,relatedModels.nestedRelatedModels', 'relatedModels.nestedRelatedModels,relatedModels'] as $includes) {
            DB::flushQueryLog();

            $models = $this
                ->createEloquentWizardFromQuery(['include' => $includes, 'fields' => ['relatedModels' => 'name']])
                ->allowedIncludes('relatedModels', 'relatedModels.nestedRelatedModels')
                ->allowedFields('relatedModels.name')
                ->get();

            $this->assertCount(1, $models->first()->relatedModels->first()->nestedRelatedModels);
            $relatedQueries[] = collect(DB::getQueryLog())
                ->pluck('query')
                ->first(fn (string $sql) => str_contains($sql, 'from "related_models"'));
        }

        $this->assertStringNotContainsString('select *', $relatedQueries[0]);
        $this->assertSame($relatedQueries[1], $relatedQueries[0]);
    }

    #[Test]
    public function relationship_include_mutates_a_builder_and_falls_back_to_with_for_other_subjects(): void
    {
        $include = RelationshipInclude::make('relatedModels');
        $builder = TestModel::query();

        $this->assertSame($builder, $include->apply($builder));
        $this->assertSame(['relatedModels'], array_keys($builder->getEagerLoads()));

        $fromModel = $include->apply(new TestModel);

        $this->assertInstanceOf(Builder::class, $fromModel);
        $this->assertSame(['relatedModels'], array_keys($fromModel->getEagerLoads()));
    }

    #[Test]
    public function callback_include_loading_a_nested_path_keeps_the_developer_constraint(): void
    {
        $kept = RelatedModel::query()->where('test_model_id', $this->models->first()->id)->firstOrFail();

        $models = $this
            ->createEloquentWizardWithIncludes(
                'deep',
                TestModel::query()->with(['relatedModels' => fn ($query) => $query->whereKey($kept->id)])
            )
            ->allowedIncludes(EloquentInclude::callback('deep', fn ($query) => $query->with('relatedModels.nestedRelatedModels')))
            ->get();

        $this->assertSame([[$kept->id], [], []], $models->map(fn ($model) => $model->relatedModels->modelKeys())->all());
        $this->assertTrue($models->first()->relatedModels->first()->relationLoaded('nestedRelatedModels'));
    }

    #[Test]
    public function callback_include_passing_its_own_constraint_replaces_the_developer_constraint(): void
    {
        $kept = RelatedModel::query()->where('test_model_id', $this->models->first()->id)->firstOrFail();

        $models = $this
            ->createEloquentWizardWithIncludes(
                'all',
                TestModel::query()->with(['relatedModels' => fn ($query) => $query->whereKey($kept->id)])
            )
            ->allowedIncludes(EloquentInclude::callback('all', fn ($query) => $query->with([
                'relatedModels' => fn ($related) => $related->orderBy('id'),
            ])))
            ->get();

        $this->assertSame([2, 2, 2], $models->map(fn ($model) => $model->relatedModels->count())->all());
    }

    #[Test]
    public function custom_relationship_include_keeps_its_constraint_under_sparse_fields(): void
    {
        $kept = RelatedModel::query()->where('test_model_id', $this->models->first()->id)->firstOrFail();
        $include = new class('relatedModels', null, $kept->id) extends AbstractInclude
        {
            public function __construct(string $relation, ?string $alias, private readonly int $keptId)
            {
                parent::__construct($relation, $alias);
            }

            public function getType(): string
            {
                return 'relationship';
            }

            public function apply(mixed $subject): mixed
            {
                return $subject->with([$this->relation => fn ($query) => $query->whereKey($this->keptId)]);
            }
        };

        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardFromQuery(['include' => 'relatedModels', 'fields' => ['relatedModels' => 'name']])
            ->allowedIncludes($include)
            ->allowedFields('relatedModels.name')
            ->get();

        $relatedQuery = collect(DB::getQueryLog())->pluck('query')->first(fn (string $sql) => str_contains($sql, 'from "related_models"'));

        $this->assertSame([1, 0, 0], $models->map(fn ($model) => $model->relatedModels->count())->all());
        $this->assertStringNotContainsString('select *', $relatedQuery);
    }
}

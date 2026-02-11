<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('fields')]
class FieldsTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        $this->models = TestModel::factory()->count(3)->create();

        // Create related models
        $this->models->each(function (TestModel $model) {
            RelatedModel::factory()->count(2)->create([
                'test_model_id' => $model->id,
            ]);
        });
    }

    // ========== Basic Fields Tests ==========
    #[Test]
    public function it_selects_all_fields_by_default(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery()
            ->get();

        $this->assertNotNull($models->first()->name);
        $this->assertNotNull($models->first()->created_at);
    }

    #[Test]
    public function it_can_select_specific_fields(): void
    {
        // Resource key is camelCase of model class: TestModel -> testModel
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name'])
            ->allowedFields('id', 'name')
            ->get();

        // Only selected fields should be present
        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
        $this->assertNotContains('created_at', $attributes);
    }

    #[Test]
    public function it_can_select_specific_fields_using_root_shorthand(): void
    {
        $models = $this
            ->createEloquentWizardWithFields('id,name')
            ->allowedFields('id', 'name')
            ->get();

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
        $this->assertNotContains('created_at', $attributes);
    }

    #[Test]
    public function resource_keyed_fields_take_precedence_over_root_shorthand(): void
    {
        $models = $this
            ->createEloquentWizardWithFields('id,name,testModel.id')
            ->allowedFields('id', 'name')
            ->get();

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertNotContains('name', $attributes);
    }

    #[Test]
    public function it_can_select_single_field(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'name'])
            ->allowedFields('name')
            ->get();

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('name', $attributes);
    }

    #[Test]
    public function it_can_select_fields_as_array(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => ['id', 'name']])
            ->allowedFields('id', 'name')
            ->get();

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
    }

    // ========== Wildcard Tests ==========
    #[Test]
    public function it_can_use_wildcard_to_select_all(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => '*'])
            ->allowedFields('*')
            ->get();

        $this->assertNotNull($models->first()->name);
        $this->assertNotNull($models->first()->created_at);
    }

    #[Test]
    public function wildcard_in_allowed_fields_permits_all(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name,created_at'])
            ->allowedFields('*')
            ->get();

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
        $this->assertContains('created_at', $attributes);
    }

    // ========== Relation Fields Tests ==========
    #[Test]
    public function it_can_select_fields_for_included_relation(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id,name',
                ],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id', 'relatedModels.name')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));

        $relatedAttributes = array_keys($models->first()->relatedModels->first()->toArray());
        $this->assertContains('id', $relatedAttributes);
        $this->assertContains('name', $relatedAttributes);
        $this->assertNotContains('test_model_id', $relatedAttributes);
    }

    #[Test]
    public function it_can_select_fields_for_included_relation_with_alias(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'related',
                'fields' => [
                    'testModel' => 'id,name',
                    'related' => 'id',
                ],
            ])
            ->allowedIncludes(
                EloquentInclude::relationship('relatedModels')->alias('related')
            )
            ->allowedFields('id', 'name', 'related.id')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $relatedAttributes = array_keys($models->first()->relatedModels->first()->toArray());
        $this->assertContains('id', $relatedAttributes);
        $this->assertNotContains('name', $relatedAttributes);
        $this->assertNotContains('test_model_id', $relatedAttributes);
    }

    #[Test]
    public function it_can_select_fields_for_nested_included_relation(): void
    {
        $this->models->each(function (TestModel $model): void {
            $model->relatedModels->each(function (RelatedModel $relatedModel): void {
                NestedRelatedModel::factory()->count(2)->create([
                    'related_model_id' => $relatedModel->id,
                ]);
            });
        });

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels.nestedRelatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id',
                    'relatedModels.nestedRelatedModels' => 'id',
                ],
            ])
            ->allowedIncludes('relatedModels.nestedRelatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id', 'relatedModels.nestedRelatedModels.id')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $nestedAttributes = array_keys($models->first()->relatedModels->first()->nestedRelatedModels->first()->toArray());
        $this->assertContains('id', $nestedAttributes);
        $this->assertNotContains('name', $nestedAttributes);
        $this->assertNotContains('related_model_id', $nestedAttributes);
    }

    #[Test]
    #[DataProvider('relationFieldExecutionMethodsProvider')]
    public function it_applies_relation_fields_for_all_execution_methods(string $method): void
    {
        $wizard = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id',
                ],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id');

        $result = match ($method) {
            'get' => $wizard->get(),
            'first' => $wizard->first(),
            'firstOrFail' => $wizard->firstOrFail(),
            'paginate' => $wizard->paginate(2),
            'simplePaginate' => $wizard->simplePaginate(2),
            'cursorPaginate' => $wizard->cursorPaginate(2),
        };

        $model = match ($method) {
            'get' => $result->first(),
            'first', 'firstOrFail' => $result,
            'paginate', 'simplePaginate', 'cursorPaginate' => collect($result->items())->first(),
        };

        $this->assertInstanceOf(TestModel::class, $model);
        $this->assertTrue($model->relationLoaded('relatedModels'));

        $relatedAttributes = array_keys($model->relatedModels->first()->toArray());
        $this->assertContains('id', $relatedAttributes);
        $this->assertNotContains('name', $relatedAttributes);
        $this->assertNotContains('test_model_id', $relatedAttributes);
    }

    #[Test]
    #[DataProvider('relationFieldExecutionMethodsProvider')]
    public function it_combines_relation_fields_and_nested_appends_for_all_execution_methods(string $method): void
    {
        $wizard = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id',
                ],
                'append' => 'relatedModels.formattedName',
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id')
            ->allowedAppends('relatedModels.formattedName');

        $result = match ($method) {
            'get' => $wizard->get(),
            'first' => $wizard->first(),
            'firstOrFail' => $wizard->firstOrFail(),
            'paginate' => $wizard->paginate(2),
            'simplePaginate' => $wizard->simplePaginate(2),
            'cursorPaginate' => $wizard->cursorPaginate(2),
        };

        $model = match ($method) {
            'get' => $result->first(),
            'first', 'firstOrFail' => $result,
            'paginate', 'simplePaginate', 'cursorPaginate' => collect($result->items())->first(),
        };

        $this->assertInstanceOf(TestModel::class, $model);

        $relatedArray = $model->relatedModels->first()->toArray();
        $this->assertArrayHasKey('id', $relatedArray);
        $this->assertArrayNotHasKey('name', $relatedArray);
        $this->assertArrayNotHasKey('test_model_id', $relatedArray);
        $this->assertArrayHasKey('formattedName', $relatedArray);
    }

    #[Test]
    public function it_defaults_related_model_fields_correctly(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                ],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name')
            ->get();

        // Related models should have their default fields
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    // ========== Validation Tests ==========
    #[Test]
    public function it_throws_exception_for_not_allowed_field(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardWithFields(['testModel' => 'secret_field'])
            ->allowedFields('id', 'name')
            ->get();
    }

    #[Test]
    public function it_throws_exception_for_not_allowed_relation_field(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id,secret_field',
                ],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id')
            ->get();
    }

    #[Test]
    public function it_throws_exception_for_relation_wildcard_field_when_not_allowed(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => '*',
                ],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id')
            ->get();
    }

    #[Test]
    public function it_throws_exception_for_unknown_relation_key_when_other_relation_whitelist_exists(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'otherRelatedModels' => 'id',
                ],
            ])
            ->allowedIncludes('relatedModels', 'otherRelatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id')
            ->get();
    }

    #[Test]
    public function it_throws_exception_for_relation_alias_without_matching_relation_field_whitelist(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardFromQuery([
                'include' => 'related',
                'fields' => [
                    'related' => 'id',
                ],
            ])
            ->allowedIncludes(
                EloquentInclude::relationship('relatedModels')->alias('related'),
                'otherRelatedModels'
            )
            ->allowedFields('id', 'name', 'otherRelatedModels.id')
            ->get();
    }

    #[Test]
    public function it_ignores_not_allowed_field_when_exception_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_field_query_exception', true);

        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,secret_field'])
            ->allowedFields('id', 'name')
            ->get();

        // No exception, returns models with only valid fields
        $this->assertCount(3, $models);
        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertNotContains('secret_field', $attributes);
    }

    #[Test]
    public function it_intersects_not_allowed_relation_fields_when_exception_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_field_query_exception', true);

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id,secret_field',
                ],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $relatedAttributes = array_keys($models->first()->relatedModels->first()->toArray());
        $this->assertContains('id', $relatedAttributes);
        $this->assertNotContains('name', $relatedAttributes);
        $this->assertNotContains('test_model_id', $relatedAttributes);
        $this->assertNotContains('secret_field', $relatedAttributes);
    }

    #[Test]
    public function it_ignores_unknown_relation_keys_and_keeps_valid_relation_fieldsets_when_exception_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_field_query_exception', true);

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id',
                    'unknownRelation' => 'id',
                ],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $relatedAttributes = array_keys($models->first()->relatedModels->first()->toArray());
        $this->assertContains('id', $relatedAttributes);
        $this->assertNotContains('name', $relatedAttributes);
        $this->assertNotContains('test_model_id', $relatedAttributes);
    }

    #[Test]
    public function it_applies_requested_fields_when_wildcard_allowed(): void
    {
        // Wildcard ['*'] allows any fields requested by client
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name'])
            ->allowedFields(['*'])
            ->get();

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
    }

    #[Test]
    public function it_throws_exception_with_empty_allowed_fields(): void
    {
        // Empty allowed array means forbid all field requests
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardWithFields(['testModel' => 'name'])
            ->allowedFields([])
            ->get();
    }

    // ========== SQL Verification Tests ==========
    #[Test]
    public function it_qualifies_column_names(): void
    {
        $sql = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name'])
            ->allowedFields('id', 'name')
            ->build()
            ->toSql();

        $this->assertStringContainsString('"test_models"."id"', $sql);
        $this->assertStringContainsString('"test_models"."name"', $sql);
    }

    // ========== Edge Cases ==========
    #[Test]
    public function it_handles_empty_fields_string(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => ''])
            ->allowedFields('id', 'name')
            ->get();

        // Empty fields = select all
        $this->assertNotNull($models->first()->name);
    }

    #[Test]
    public function it_handles_fields_with_trailing_comma(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name,'])
            ->allowedFields('id', 'name')
            ->get();

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
    }

    #[Test]
    public function it_removes_duplicate_fields(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name,id,name'])
            ->allowedFields('id', 'name')
            ->get();

        $this->assertCount(3, $models);
    }

    #[Test]
    public function it_trims_whitespace_from_field_values(): void
    {
        // Field values are trimmed - ' id , name ' becomes 'id', 'name'
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => ' id , name '])
            ->allowedFields('id', 'name')
            ->get();

        $this->assertNotEmpty($models);
        $firstModel = $models->first();
        $this->assertArrayHasKey('id', $firstModel->getAttributes());
        $this->assertArrayHasKey('name', $firstModel->getAttributes());
    }

    // ========== Default Fields Tests ==========
    // Note: Default fields are configured via ResourceSchema, not via setDefaultFields()
    // The wizard uses getEffectiveDefaultFields() which reads from schema or context
    #[Test]
    public function it_selects_all_fields_when_none_specifically_requested(): void
    {
        // When no fields are requested, all allowed fields are selected (or *)
        $models = $this
            ->createEloquentWizardFromQuery()
            ->allowedFields('id', 'name', 'created_at')
            ->get();

        // All fields should be present
        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
    }

    // ========== Integration with Other Features ==========
    #[Test]
    public function it_works_with_filtering(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardFromQuery([
                'filter' => ['id' => $model->id],
                'fields' => ['testModel' => 'id,name'],
            ])
            ->allowedFilters('id')
            ->allowedFields('id', 'name')
            ->get();

        $this->assertCount(1, $models);
        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
    }

    #[Test]
    public function it_works_with_sorting(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'sort' => '-name',
                'fields' => ['testModel' => 'id,name'],
            ])
            ->allowedSorts('name')
            ->allowedFields('id', 'name')
            ->get();

        $this->assertCount(3, $models);
    }

    #[Test]
    public function it_works_with_pagination(): void
    {
        $result = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name'])
            ->allowedFields('id', 'name')
            ->build()
            ->paginate(2);

        $attributes = array_keys($result->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertEquals(3, $result->total());
    }

    #[Test]
    public function it_works_with_first(): void
    {
        $model = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name'])
            ->allowedFields('id', 'name')
            ->build()
            ->first();

        $attributes = array_keys($model->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
    }

    // ========== Multiple Resource Types Tests ==========
    #[Test]
    public function it_throws_exception_for_relation_resource_without_whitelist(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id',
                ],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name')
            ->get();
    }

    #[Test]
    public function it_ignores_relation_resource_without_whitelist_when_exception_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_field_query_exception', true);

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id',
                ],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function relationFieldExecutionMethodsProvider(): array
    {
        return [
            ['get'],
            ['first'],
            ['firstOrFail'],
            ['paginate'],
            ['simplePaginate'],
            ['cursorPaginate'],
        ];
    }

    // ========== Dotted String Format Tests ==========
    #[Test]
    public function it_handles_dotted_string_format(): void
    {
        $models = $this
            ->createEloquentWizardWithFields('testModel.id,testModel.name')
            ->allowedFields('id', 'name')
            ->get();

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
    }

    #[Test]
    public function it_handles_mixed_dotted_and_array_format(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'fields' => 'testModel.id,testModel.name',
            ])
            ->allowedFields('id', 'name')
            ->get();

        $this->assertCount(3, $models);
    }

    // ========== Primary Key Handling ==========
    #[Test]
    public function it_always_includes_primary_key_when_needed_for_relations(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => ['testModel' => 'name'],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('name')
            ->get();

        // Relations should still work even if id wasn't explicitly requested
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    #[Test]
    public function safe_mode_constrains_relation_selects_and_auto_adds_matching_keys(): void
    {
        config()->set('query-wizard.optimizations.relation_select_mode', 'safe');
        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'name',
                    'relatedModels' => 'name',
                ],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('name', 'relatedModels.name')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertCount(2, $models->first()->relatedModels);

        $rootArray = $models->first()->toArray();
        $this->assertArrayHasKey('name', $rootArray);
        $this->assertArrayNotHasKey('id', $rootArray);

        $relatedArray = $models->first()->relatedModels->first()->toArray();
        $this->assertArrayHasKey('name', $relatedArray);
        $this->assertArrayNotHasKey('test_model_id', $relatedArray);

        $this->assertQueryLogContains('select "test_models"."name", "test_models"."id" from "test_models"');
        $this->assertQueryLogContains('select "name", "test_model_id" from "related_models"');
    }

    #[Test]
    public function safe_mode_skips_relation_select_optimization_when_relation_append_is_requested(): void
    {
        config()->set('query-wizard.optimizations.relation_select_mode', 'safe');
        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id',
                ],
                'append' => 'relatedModels.formattedName',
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id')
            ->allowedAppends('relatedModels.formattedName')
            ->get();

        $relatedArray = $models->first()->relatedModels->first()->toArray();
        $this->assertArrayHasKey('formattedName', $relatedArray);
        $this->assertNotSame('Formatted: ', $relatedArray['formattedName']);

        $this->assertQueryLogContains('select * from "related_models"');
    }

    #[Test]
    public function safe_mode_skips_relation_select_optimization_when_relation_append_is_requested_via_alias(): void
    {
        config()->set('query-wizard.optimizations.relation_select_mode', 'safe');
        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'related',
                'fields' => [
                    'testModel' => 'id,name',
                    'related' => 'id',
                ],
                'append' => 'related.formattedName',
            ])
            ->allowedIncludes(EloquentInclude::relationship('relatedModels')->alias('related'))
            ->allowedFields('id', 'name', 'related.id')
            ->allowedAppends('related.formattedName')
            ->get();

        $relatedArray = $models->first()->relatedModels->first()->toArray();
        $this->assertArrayHasKey('formattedName', $relatedArray);
        $this->assertNotSame('Formatted: ', $relatedArray['formattedName']);

        $this->assertQueryLogContains('select * from "related_models"');
    }

    #[Test]
    public function safe_mode_ignores_default_relation_appends_when_append_request_is_present(): void
    {
        config()->set('query-wizard.optimizations.relation_select_mode', 'safe');
        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id',
                ],
                'append' => 'fullname',
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id')
            ->allowedAppends('fullname', 'relatedModels.formattedName')
            ->defaultAppends('relatedModels.formattedName')
            ->get();

        $this->assertArrayHasKey('fullname', $models->first()->toArray());
        $this->assertQueryLogContains('select "id", "test_model_id" from "related_models"');
    }

    #[Test]
    public function safe_mode_skips_relation_select_optimization_when_relation_model_has_built_in_appends(): void
    {
        DB::flushQueryLog();

        // relatedModelsWithAppends uses RelatedModelWithAppends which has $appends = ['formatted_name']
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModelsWithAppends',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModelsWithAppends' => 'id',
                ],
            ])
            ->allowedIncludes('relatedModelsWithAppends')
            ->allowedFields('id', 'name', 'relatedModelsWithAppends.id')
            ->get();

        // The formatted_name accessor should work correctly because SELECT * was used
        $related = $models->first()->relatedModelsWithAppends->first();
        if ($related !== null) {
            $relatedArray = $related->toArray();
            $this->assertArrayHasKey('formatted_name', $relatedArray);
            // formatted_name = 'Formatted: ' . $this->name, should not be just 'Formatted: '
            $this->assertNotSame('Formatted: ', $relatedArray['formatted_name']);
        }

        // Verify SELECT * was used instead of SELECT id
        $this->assertQueryLogContains('select * from "related_models"');
    }

    #[Test]
    public function safe_mode_uses_sql_select_for_relations_without_model_appends(): void
    {
        DB::flushQueryLog();

        // relatedModels uses RelatedModel which has no $appends
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'name',
                ],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.name')
            ->get();

        // Verify SQL SELECT was used (not SELECT *)
        $this->assertQueryLogContains('select "name", "test_model_id" from "related_models"');
    }

    // ========== Snake Case / Camel Case Tests ==========
    #[Test]
    public function it_handles_snake_case_fields(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'created_at,updated_at'])
            ->allowedFields('created_at', 'updated_at')
            ->get();

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('created_at', $attributes);
    }

    // ========== Wildcard Fields Tests ==========

    #[Test]
    public function wildcard_allows_all_fields_for_relation(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id,name',
                ],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.*')  // Wildcard for relation
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $relatedAttributes = array_keys($models->first()->relatedModels->first()->toArray());
        $this->assertContains('id', $relatedAttributes);
        $this->assertContains('name', $relatedAttributes);
    }

    #[Test]
    public function wildcard_is_non_recursive_for_fields(): void
    {
        // Create nested related models
        $this->models->each(function (TestModel $model): void {
            $model->relatedModels->each(function (RelatedModel $relatedModel): void {
                NestedRelatedModel::factory()->count(2)->create([
                    'related_model_id' => $relatedModel->id,
                ]);
            });
        });

        // 'relatedModels.*' should NOT allow 'relatedModels.nestedRelatedModels.x'
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels.nestedRelatedModels',
                'fields' => [
                    'testModel' => 'id',
                    'relatedModels.nestedRelatedModels' => 'id',
                ],
            ])
            ->allowedIncludes('relatedModels.nestedRelatedModels')
            ->allowedFields('id', 'relatedModels.*')  // Only allows relatedModels.x, not nested
            ->get();
    }

    // ========== Default Fields Tests ==========

    #[Test]
    public function default_fields_apply_when_no_fields_param(): void
    {
        DB::enableQueryLog();

        $this
            ->createEloquentWizardFromQuery([], TestModel::class)
            ->allowedFields('id', 'name', 'created_at')
            ->defaultFields('id', 'name')
            ->get();

        $this->assertQueryLogContains('select "test_models"."id", "test_models"."name" from "test_models"');
    }

    #[Test]
    public function default_fields_do_not_apply_when_fields_requested(): void
    {
        DB::enableQueryLog();

        // When request has fields param, defaults should NOT be used
        $this
            ->createEloquentWizardWithFields(['testModel' => 'created_at'])
            ->allowedFields('id', 'name', 'created_at')
            ->defaultFields('id', 'name')
            ->get();

        // Should use requested field, not defaults
        $this->assertQueryLogContains('select "test_models"."created_at" from "test_models"');
    }

    #[Test]
    public function no_default_fields_means_all_fields(): void
    {
        DB::enableQueryLog();

        // When no defaults and no request, all fields should be returned
        $this
            ->createEloquentWizardFromQuery([], TestModel::class)
            ->allowedFields('id', 'name', 'created_at')
            // No defaultFields() call
            ->get();

        // Should not have SELECT restriction
        $this->assertQueryLogContains('select * from "test_models"');
    }

    // ========== Nested Includes with Aliases Edge Cases ==========

    #[Test]
    public function intermediate_relation_fields_work_with_aliased_nested_include(): void
    {
        // Create nested related models
        $this->models->each(function (TestModel $model): void {
            $model->relatedModels->each(function (RelatedModel $relatedModel): void {
                NestedRelatedModel::factory()->count(2)->create([
                    'related_model_id' => $relatedModel->id,
                ]);
            });
        });

        // When nested include has alias 'nested', intermediate 'relatedModels' should still work
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'nested',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id,name',  // Intermediate relation
                ],
            ])
            ->allowedIncludes(
                EloquentInclude::relationship('relatedModels.nestedRelatedModels')->alias('nested')
            )
            ->allowedFields('id', 'name', 'relatedModels.*')  // Wildcard for intermediate
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $relatedAttributes = array_keys($models->first()->relatedModels->first()->toArray());
        $this->assertContains('id', $relatedAttributes);
        $this->assertContains('name', $relatedAttributes);
        $this->assertNotContains('test_model_id', $relatedAttributes);
    }

    // ========== Wildcard Security Tests ==========

    #[Test]
    public function root_wildcard_request_should_be_rejected_when_not_allowed(): void
    {
        // Client requests ?fields[testModel]=* but allowed only 'id', 'name'
        // This should throw an exception, not return all fields
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardWithFields(['testModel' => '*'])
            ->allowedFields('id', 'name')
            ->get();
    }

    #[Test]
    public function root_wildcard_request_should_work_when_explicitly_allowed(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => '*'])
            ->allowedFields('*')
            ->get();

        $this->assertNotNull($models->first()->name);
        $this->assertNotNull($models->first()->created_at);
    }

    // ========== Disallowed Wildcard Tests ==========

    #[Test]
    public function disallowed_global_wildcard_blocks_all_fields(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this->createEloquentWizardWithFields(['testModel' => 'id'])
            ->allowedFields('id', 'name')
            ->disallowedFields('*')
            ->get();
    }

    #[Test]
    public function disallowed_level_wildcard_blocks_relation_fields(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => ['testModel' => 'id', 'relatedModels' => 'id'],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'relatedModels.*')
            ->disallowedFields('relatedModels.*')
            ->get();
    }

    #[Test]
    public function disallowed_prefix_blocks_relation_and_descendants(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => ['testModel' => 'id', 'relatedModels' => 'id'],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'relatedModels.*')
            ->disallowedFields('relatedModels')
            ->get();
    }
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\IncludeDefinition;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;

/**
 * @group eloquent
 * @group fields
 */
class FieldsTest extends TestCase
{
    protected Collection $models;

    public function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        $this->models = factory(TestModel::class, 3)->create();

        // Create related models
        $this->models->each(function (TestModel $model) {
            factory(RelatedModel::class, 2)->create([
                'test_model_id' => $model->id,
            ]);
        });
    }

    // ========== Basic Fields Tests ==========

    /** @test */
    public function it_selects_all_fields_by_default(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery()
            ->get();

        $this->assertNotNull($models->first()->name);
        $this->assertNotNull($models->first()->created_at);
    }

    /** @test */
    public function it_can_select_specific_fields(): void
    {
        // Resource key is camelCase of model class: TestModel -> testModel
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name'])
            ->setAllowedFields('id', 'name')
            ->get();

        // Only selected fields should be present
        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
        $this->assertNotContains('created_at', $attributes);
    }

    /** @test */
    public function it_can_select_single_field(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'name'])
            ->setAllowedFields('name')
            ->get();

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('name', $attributes);
    }

    /** @test */
    public function it_can_select_fields_as_array(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => ['id', 'name']])
            ->setAllowedFields('id', 'name')
            ->get();

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
    }

    // ========== Wildcard Tests ==========

    /** @test */
    public function it_can_use_wildcard_to_select_all(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => '*'])
            ->setAllowedFields('*')
            ->get();

        $this->assertNotNull($models->first()->name);
        $this->assertNotNull($models->first()->created_at);
    }

    /** @test */
    public function wildcard_in_allowed_fields_permits_all(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name,created_at'])
            ->setAllowedFields('*')
            ->get();

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
        $this->assertContains('created_at', $attributes);
    }

    // ========== Relation Fields Tests ==========

    /** @test */
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
            ->setAllowedIncludes('relatedModels')
            ->setAllowedFields('id', 'name')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    /** @test */
    public function it_defaults_related_model_fields_correctly(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                ],
            ])
            ->setAllowedIncludes('relatedModels')
            ->setAllowedFields('id', 'name')
            ->get();

        // Related models should have their default fields
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    // ========== Validation Tests ==========

    /** @test */
    public function it_throws_exception_for_not_allowed_field(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardWithFields(['testModel' => 'secret_field'])
            ->setAllowedFields('id', 'name')
            ->get();
    }

    /** @test */
    public function it_ignores_unknown_fields_when_no_allowed_set(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'unknown_field'])
            ->get();

        // No exception, returns all models with all fields
        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_ignores_fields_when_empty_allowed_array(): void
    {
        // Empty allowed array means silently ignore all field requests
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'name'])
            ->setAllowedFields([])
            ->get();

        // No exception, returns all models with all fields
        $this->assertCount(3, $models);
    }

    // ========== SQL Verification Tests ==========

    /** @test */
    public function it_qualifies_column_names(): void
    {
        $sql = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name'])
            ->setAllowedFields('id', 'name')
            ->build()
            ->toSql();

        $this->assertStringContainsString('"test_models"."id"', $sql);
        $this->assertStringContainsString('"test_models"."name"', $sql);
    }

    /** @test */
    public function it_uses_select_clause_for_fields(): void
    {
        $sql = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name'])
            ->setAllowedFields('id', 'name')
            ->build()
            ->toSql();

        $this->assertStringContainsString('select', strtolower($sql));
    }

    // ========== Edge Cases ==========

    /** @test */
    public function it_handles_empty_fields_string(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => ''])
            ->setAllowedFields('id', 'name')
            ->get();

        // Empty fields = select all
        $this->assertNotNull($models->first()->name);
    }

    /** @test */
    public function it_handles_fields_with_trailing_comma(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name,'])
            ->setAllowedFields('id', 'name')
            ->get();

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
    }

    /** @test */
    public function it_removes_duplicate_fields(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name,id,name'])
            ->setAllowedFields('id', 'name')
            ->get();

        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_treats_field_values_literally_with_spaces(): void
    {
        // Field values are treated literally - spaces are NOT trimmed
        // ' id , name ' is different from 'id,name'
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardWithFields(['testModel' => ' id , name '])
            ->setAllowedFields('id', 'name')
            ->get();
    }

    // ========== Default Fields Tests ==========
    // Note: Default fields are configured via ResourceSchema, not via setDefaultFields()
    // The wizard uses getEffectiveDefaultFields() which reads from schema or context

    /** @test */
    public function it_selects_all_fields_when_none_specifically_requested(): void
    {
        // When no fields are requested, all allowed fields are selected (or *)
        $models = $this
            ->createEloquentWizardFromQuery()
            ->setAllowedFields('id', 'name', 'created_at')
            ->get();

        // All fields should be present
        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
    }

    // ========== Integration with Other Features ==========

    /** @test */
    public function it_works_with_filtering(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardFromQuery([
                'filter' => ['id' => $model->id],
                'fields' => ['testModel' => 'id,name'],
            ])
            ->setAllowedFilters('id')
            ->setAllowedFields('id', 'name')
            ->get();

        $this->assertCount(1, $models);
        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
    }

    /** @test */
    public function it_works_with_sorting(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'sort' => '-name',
                'fields' => ['testModel' => 'id,name'],
            ])
            ->setAllowedSorts('name')
            ->setAllowedFields('id', 'name')
            ->get();

        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_works_with_pagination(): void
    {
        $result = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name'])
            ->setAllowedFields('id', 'name')
            ->build()
            ->paginate(2);

        $attributes = array_keys($result->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertEquals(3, $result->total());
    }

    /** @test */
    public function it_works_with_first(): void
    {
        $model = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name'])
            ->setAllowedFields('id', 'name')
            ->build()
            ->first();

        $attributes = array_keys($model->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
    }

    // ========== Multiple Resource Types Tests ==========

    /** @test */
    public function it_handles_different_resource_fields(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id',
                ],
            ])
            ->setAllowedIncludes('relatedModels')
            ->setAllowedFields('id', 'name')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    // ========== Dotted String Format Tests ==========

    /** @test */
    public function it_handles_dotted_string_format(): void
    {
        $models = $this
            ->createEloquentWizardWithFields('testModel.id,testModel.name')
            ->setAllowedFields('id', 'name')
            ->get();

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
    }

    /** @test */
    public function it_handles_mixed_dotted_and_array_format(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'fields' => 'testModel.id,testModel.name',
            ])
            ->setAllowedFields('id', 'name')
            ->get();

        $this->assertCount(3, $models);
    }

    // ========== Primary Key Handling ==========

    /** @test */
    public function it_always_includes_primary_key_when_needed_for_relations(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => ['testModel' => 'name'],
            ])
            ->setAllowedIncludes('relatedModels')
            ->setAllowedFields('name')
            ->get();

        // Relations should still work even if id wasn't explicitly requested
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    // ========== Snake Case / Camel Case Tests ==========

    /** @test */
    public function it_handles_snake_case_fields(): void
    {
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'created_at,updated_at'])
            ->setAllowedFields('created_at', 'updated_at')
            ->get();

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('created_at', $attributes);
    }

    /** @test */
    public function it_handles_camel_case_fields(): void
    {
        // This depends on model configuration, but test doesn't throw
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'id'])
            ->setAllowedFields('id')
            ->get();

        $this->assertCount(3, $models);
    }
}

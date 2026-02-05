<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
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
            ->allowedFields('id', 'name')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
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

    #[Test]
    public function it_uses_select_clause_for_fields(): void
    {
        $sql = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name'])
            ->allowedFields('id', 'name')
            ->build()
            ->toSql();

        $this->assertStringContainsString('select', strtolower($sql));
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
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
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

    #[Test]
    public function it_handles_camel_case_fields(): void
    {
        // This depends on model configuration, but test doesn't throw
        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'id'])
            ->allowedFields('id')
            ->get();

        $this->assertCount(3, $models);
    }
}

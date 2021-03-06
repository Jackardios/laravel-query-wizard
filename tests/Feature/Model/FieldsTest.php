<?php

namespace Jackardios\QueryWizard\Tests\Feature\Model;

use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Model\ModelQueryWizard;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;

/**
 * @group model
 * @group fields
 * @group model-fields
 */
class FieldsTest extends TestCase
{
    protected TestModel $model;

    /** @var string */
    protected string $modelTableName;

    public function setUp(): void
    {
        parent::setUp();

        $this->model = factory(TestModel::class)->create()->first();
        $this->modelTableName = $this->model->getTable();
    }

    /** @test */
    public function it_fetches_all_columns_if_no_field_was_requested(): void
    {
        $model = $this->createModelWizardWithFields()
            ->build();

        $expectedModel = TestModel::find($this->model->getKey());

        $this->assertModelsAttributesEqual($model, $expectedModel);
    }

    /** @test */
    public function it_fetches_all_columns_if_no_field_was_requested_but_allowed_fields_were_specified(): void
    {
        $model = $this->createModelWizardWithFields()
            ->setAllowedFields('id', 'name')
            ->build();

        $expectedModel = TestModel::find($this->model->getKey());

        $this->assertModelsAttributesEqual($model, $expectedModel);
    }

    /** @test */
    public function it_can_fetch_specific_columns(): void
    {
        $model = $this->createModelWizardWithFields(['test_models' => 'name,id'])
            ->setAllowedFields(['name', 'id'])
            ->build();

        $this->assertModelAttributeKeys(['id', 'name'], $model);
    }

    /** @test */
    public function it_wont_fetch_a_specific_column_if_its_not_allowed(): void
    {
        $model = $this->createModelWizardWithFields(['test_models' => 'random-column'])
            ->build();

        $expectedModel = TestModel::find($this->model->getKey());

        $this->assertModelsAttributesEqual($model, $expectedModel);
    }

    /** @test */
    public function it_guards_against_not_allowed_fields(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this->createModelWizardWithFields(['test_models' => 'random-column'])
            ->setAllowedFields('name')
            ->build();
    }

    /** @test */
    public function it_guards_against_not_allowed_fields_from_an_included_resource(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this->createModelWizardWithFields(['related_models' => 'random_column'])
            ->setAllowedFields('related_models.name')
            ->build();
    }

    /** @test */
    public function it_can_fetch_only_requested_columns_from_an_included_model(): void
    {
        RelatedModel::create([
            'test_model_id' => $this->model->id,
            'name' => 'related',
        ]);

        $model = $this
            ->createModelWizardFromQuery([
                'fields' => [
                    'test_models' => 'id',
                    'related_models' => 'id,test_model_id',
                ],
                'include' => ['relatedModels'],
            ])
            ->setAllowedFields('related_models.id', 'related_models.test_model_id', 'id')
            ->setAllowedIncludes('relatedModels')
            ->build();

        $this->assertModelAttributeKeys(['id'], $model);
        $this->assertModelAttributeKeys(['id', 'test_model_id'], $model->relatedModels[0]);
    }

    /** @test */
    public function it_can_fetch_requested_columns_from_included_models_up_to_two_levels_deep(): void
    {
        RelatedModel::create([
            'test_model_id' => $this->model->id,
            'name' => 'related',
        ]);

        $result = $this
            ->createModelWizardFromQuery([
                'fields' => [
                    'test_models' => 'id,name',
                    'related_models.test_models' => 'id',
                ],
                'include' => ['relatedModels.testModel'],
            ])
            ->setAllowedFields('related_models.test_models.id', 'id', 'name')
            ->setAllowedIncludes('relatedModels.testModel')
            ->build();

        $this->assertArrayHasKey('name', $result);

        $this->assertEquals(['id' => $this->model->id], $result->relatedModels->first()->testModel->toArray());
    }

    protected function assertModelAttributeKeys($attributes, Model $model): void
    {
        $this->assertEqualsCanonicalizing($attributes, array_keys($model->getAttributes()));
    }

    protected function createModelWizardWithFields(array|string $fields = [], $model = null): ModelQueryWizard
    {
        return parent::createModelWizardWithFields($fields, $model ?? $this->model);
    }

    protected function createModelWizardFromQuery(array $query = [], $model = null): ModelQueryWizard
    {
        return parent::createModelWizardFromQuery($query, $model ?? $this->model);
    }
}

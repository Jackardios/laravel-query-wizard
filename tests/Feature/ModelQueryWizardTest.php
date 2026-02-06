<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('model-wizard')]
class ModelQueryWizardTest extends TestCase
{
    protected TestModel $model;

    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = TestModel::factory()->count(3)->create();
        $this->model = $this->models->first();

        // Create related models
        $this->models->each(function (TestModel $model) {
            RelatedModel::factory()->count(2)->create([
                'test_model_id' => $model->id,
            ]);
        });
    }

    // ========== Basic Tests ==========
    #[Test]
    public function it_returns_the_model(): void
    {
        $result = $this
            ->createModelWizardFromQuery([], $this->model)
            ->process();

        $this->assertInstanceOf(TestModel::class, $result);
        $this->assertEquals($this->model->id, $result->id);
    }

    #[Test]
    public function it_can_be_created_with_for_method(): void
    {
        $result = \Jackardios\QueryWizard\ModelQueryWizard::for($this->model)
            ->process();

        $this->assertInstanceOf(TestModel::class, $result);
    }

    // ========== Include Tests ==========
    #[Test]
    public function it_can_load_missing_includes(): void
    {
        $result = $this
            ->createModelWizardWithIncludes('relatedModels', $this->model)
            ->allowedIncludes('relatedModels')
            ->process();

        $this->assertTrue($result->relationLoaded('relatedModels'));
        $this->assertCount(2, $result->relatedModels);
    }

    #[Test]
    public function it_does_not_reload_already_loaded_relations(): void
    {
        $modelWithRelations = TestModel::with('relatedModels')->find($this->model->id);
        $originalRelated = $modelWithRelations->relatedModels;

        $result = $this
            ->createModelWizardWithIncludes('relatedModels', $modelWithRelations)
            ->allowedIncludes('relatedModels')
            ->process();

        $this->assertSame($originalRelated, $result->relatedModels);
    }

    #[Test]
    public function it_can_load_count_includes(): void
    {
        $result = $this
            ->createModelWizardWithIncludes('relatedModelsCount', $this->model)
            ->allowedIncludes('relatedModelsCount')
            ->process();

        $this->assertEquals(2, $result->related_models_count);
    }

    #[Test]
    public function it_can_load_includes_with_alias(): void
    {
        $result = $this
            ->createModelWizardWithIncludes('related', $this->model)
            ->allowedIncludes(EloquentInclude::relationship('relatedModels')->alias('related'))
            ->process();

        $this->assertTrue($result->relationLoaded('relatedModels'));
    }

    #[Test]
    public function it_throws_exception_for_not_allowed_include(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createModelWizardWithIncludes('relatedModels', $this->model)
            ->allowedIncludes('otherRelatedModels')
            ->process();
    }

    #[Test]
    public function it_ignores_not_allowed_include_when_exception_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_include_query_exception', true);

        $result = $this
            ->createModelWizardWithIncludes('notAllowed', $this->model)
            ->allowedIncludes('relatedModels')
            ->process();

        $this->assertInstanceOf(TestModel::class, $result);
        $this->assertFalse($result->relationLoaded('notAllowed'));
    }

    #[Test]
    public function it_cleans_unwanted_relations(): void
    {
        $modelWithRelations = TestModel::with(['relatedModels', 'otherRelatedModels'])->find($this->model->id);

        $result = $this
            ->createModelWizardFromQuery([], $modelWithRelations)
            ->allowedIncludes('relatedModels')
            ->process();

        $this->assertTrue($result->relationLoaded('relatedModels'));
        $this->assertFalse($result->relationLoaded('otherRelatedModels'));
    }

    #[Test]
    public function it_can_use_default_includes(): void
    {
        $result = $this
            ->createModelWizardFromQuery([], $this->model)
            ->allowedIncludes('relatedModels')
            ->defaultIncludes('relatedModels')
            ->process();

        $this->assertTrue($result->relationLoaded('relatedModels'));
    }

    #[Test]
    public function it_can_disallow_includes(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createModelWizardWithIncludes('relatedModels', $this->model)
            ->allowedIncludes('relatedModels', 'otherRelatedModels')
            ->disallowedIncludes('relatedModels')
            ->process();
    }

    // ========== Fields Tests ==========
    #[Test]
    public function it_hides_fields_not_requested(): void
    {
        $result = $this
            ->createModelWizardWithFields(['testModel' => 'id,name'], $this->model)
            ->allowedFields('id', 'name', 'created_at')
            ->process();

        $array = $result->toArray();
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('created_at', $array);
    }

    #[Test]
    public function it_shows_all_fields_when_none_requested(): void
    {
        $result = $this
            ->createModelWizardFromQuery([], $this->model)
            ->allowedFields('id', 'name', 'created_at')
            ->process();

        $array = $result->toArray();
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('created_at', $array);
    }

    #[Test]
    public function it_hides_fields_on_relations(): void
    {
        $modelWithRelations = TestModel::with('relatedModels')->find($this->model->id);

        $result = $this
            ->createModelWizardFromQuery([
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id',
                ],
            ], $modelWithRelations)
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name')
            ->process();

        $relatedArray = $result->relatedModels->first()->toArray();
        $this->assertArrayHasKey('id', $relatedArray);
        $this->assertArrayNotHasKey('name', $relatedArray);
    }

    #[Test]
    public function it_can_disallow_fields(): void
    {
        // Request only fields that are allowed (after disallowed processing)
        // created_at is disallowed, so we can't request it
        $result = $this
            ->createModelWizardWithFields(['testModel' => 'id,name'], $this->model)
            ->allowedFields('id', 'name', 'created_at')
            ->disallowedFields('created_at')
            ->process();

        $array = $result->toArray();
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('created_at', $array);
    }

    // ========== Append Tests ==========
    #[Test]
    public function it_can_append_attributes(): void
    {
        $appendModel = AppendModel::factory()->create();

        $result = $this
            ->createModelWizardWithAppends('fullname', $appendModel)
            ->allowedAppends('fullname')
            ->process();

        $this->assertTrue(array_key_exists('fullname', $result->toArray()));
    }

    #[Test]
    public function it_can_append_multiple_attributes(): void
    {
        $appendModel = AppendModel::factory()->create();

        $result = $this
            ->createModelWizardWithAppends('fullname,reversename', $appendModel)
            ->allowedAppends('fullname', 'reversename')
            ->process();

        $array = $result->toArray();
        $this->assertTrue(array_key_exists('fullname', $array));
        $this->assertTrue(array_key_exists('reversename', $array));
    }

    #[Test]
    public function it_throws_exception_for_not_allowed_append(): void
    {
        $appendModel = AppendModel::factory()->create();

        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createModelWizardWithAppends('notAllowed', $appendModel)
            ->allowedAppends('fullname')
            ->process();
    }

    #[Test]
    public function it_ignores_not_allowed_append_when_exception_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_append_query_exception', true);

        $appendModel = AppendModel::factory()->create();

        $result = $this
            ->createModelWizardWithAppends('notAllowed', $appendModel)
            ->allowedAppends('fullname')
            ->process();

        $this->assertFalse(array_key_exists('notAllowed', $result->toArray()));
    }

    #[Test]
    public function it_can_use_default_appends(): void
    {
        $appendModel = AppendModel::factory()->create();

        $result = $this
            ->createModelWizardFromQuery([], $appendModel)
            ->allowedAppends('fullname')
            ->defaultAppends('fullname')
            ->process();

        $this->assertTrue(array_key_exists('fullname', $result->toArray()));
    }

    #[Test]
    public function it_can_disallow_appends(): void
    {
        $appendModel = AppendModel::factory()->create();

        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createModelWizardWithAppends('fullname', $appendModel)
            ->allowedAppends('fullname', 'reversename')
            ->disallowedAppends('fullname')
            ->process();
    }

    // ========== Combined Tests ==========
    #[Test]
    public function it_works_with_all_features_combined(): void
    {
        $appendModel = AppendModel::factory()->create();

        $result = $this
            ->createModelWizardFromQuery([
                'fields' => ['appendModels' => 'firstname,lastname'],
                'append' => 'fullname',
            ], $appendModel)
            ->allowedFields('firstname', 'lastname')
            ->allowedAppends('fullname')
            ->process();

        $array = $result->toArray();
        $this->assertArrayHasKey('firstname', $array);
        $this->assertArrayHasKey('lastname', $array);
        $this->assertArrayHasKey('fullname', $array);
    }

    // ========== Relation Cleanup Tests ==========

    #[Test]
    public function it_preserves_loaded_relations_when_no_includes_configured(): void
    {
        $modelWithRelations = TestModel::with(['relatedModels', 'otherRelatedModels'])->find($this->model->id);

        // No allowedIncludes() call and no schema — should preserve all loaded relations
        $result = $this
            ->createModelWizardFromQuery([], $modelWithRelations)
            ->process();

        $this->assertTrue($result->relationLoaded('relatedModels'));
        $this->assertTrue($result->relationLoaded('otherRelatedModels'));
    }

    #[Test]
    public function it_cleans_relations_when_includes_explicitly_set_to_empty(): void
    {
        $modelWithRelations = TestModel::with(['relatedModels', 'otherRelatedModels'])->find($this->model->id);

        // Explicitly set empty includes — should clean all relations
        $result = $this
            ->createModelWizardFromQuery([], $modelWithRelations)
            ->allowedIncludes([])
            ->process();

        $this->assertFalse($result->relationLoaded('relatedModels'));
        $this->assertFalse($result->relationLoaded('otherRelatedModels'));
    }

    // ========== Edge Cases ==========
    #[Test]
    public function it_handles_empty_request(): void
    {
        $result = $this
            ->createModelWizardFromQuery([], $this->model)
            ->process();

        $this->assertInstanceOf(TestModel::class, $result);
    }

    #[Test]
    public function it_handles_model_without_relations(): void
    {
        $result = $this
            ->createModelWizardWithIncludes('relatedModels', $this->model)
            ->allowedIncludes('relatedModels')
            ->process();

        $this->assertTrue($result->relationLoaded('relatedModels'));
    }

    #[Test]
    public function it_returns_same_model_instance(): void
    {
        $wizard = $this->createModelWizardFromQuery([], $this->model);
        $result = $wizard->process();

        $this->assertSame($this->model, $result);
    }

    #[Test]
    public function get_model_returns_model_without_processing(): void
    {
        $wizard = $this->createModelWizardFromQuery([], $this->model);
        $result = $wizard->getModel();

        $this->assertSame($this->model, $result);
    }

    // ========== schema() Method Tests ==========

    #[Test]
    public function it_can_set_schema_fluently(): void
    {
        $schema = new class extends \Jackardios\QueryWizard\Schema\ResourceSchema
        {
            public function model(): string
            {
                return TestModel::class;
            }

            public function includes(\Jackardios\QueryWizard\Contracts\QueryWizardInterface $wizard): array
            {
                return ['relatedModels'];
            }
        };

        $result = \Jackardios\QueryWizard\ModelQueryWizard::for($this->model)
            ->schema($schema)
            ->process();

        // Schema allows relatedModels, but no include requested - should not load
        $this->assertFalse($result->relationLoaded('relatedModels'));
    }

    #[Test]
    public function schema_method_provides_includes_configuration(): void
    {
        $schema = new class extends \Jackardios\QueryWizard\Schema\ResourceSchema
        {
            public function model(): string
            {
                return TestModel::class;
            }

            public function includes(\Jackardios\QueryWizard\Contracts\QueryWizardInterface $wizard): array
            {
                return ['relatedModels'];
            }
        };

        $result = $this
            ->createModelWizardWithIncludes('relatedModels', $this->model)
            ->schema($schema)
            ->process();

        $this->assertTrue($result->relationLoaded('relatedModels'));
    }

    #[Test]
    public function schema_method_provides_appends_configuration(): void
    {
        $appendModel = AppendModel::factory()->create();

        $schema = new class extends \Jackardios\QueryWizard\Schema\ResourceSchema
        {
            public function model(): string
            {
                return AppendModel::class;
            }

            public function appends(\Jackardios\QueryWizard\Contracts\QueryWizardInterface $wizard): array
            {
                return ['fullname'];
            }
        };

        $result = $this
            ->createModelWizardWithAppends('fullname', $appendModel)
            ->schema($schema)
            ->process();

        $this->assertArrayHasKey('fullname', $result->toArray());
    }

    #[Test]
    public function explicit_allowed_includes_override_schema(): void
    {
        $schema = new class extends \Jackardios\QueryWizard\Schema\ResourceSchema
        {
            public function model(): string
            {
                return TestModel::class;
            }

            public function includes(\Jackardios\QueryWizard\Contracts\QueryWizardInterface $wizard): array
            {
                return ['relatedModels', 'nestedRelatedModels'];
            }
        };

        // Schema allows both, but explicit call only allows 'relatedModels'
        // So requesting 'nestedRelatedModels' should throw exception
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createModelWizardWithIncludes('nestedRelatedModels', $this->model)
            ->schema($schema)
            ->allowedIncludes('relatedModels') // Override schema
            ->process();
    }
}

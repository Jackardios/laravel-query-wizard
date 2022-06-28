<?php

namespace Jackardios\QueryWizard\Tests\Feature\Model;

use Jackardios\QueryWizard\Model\ModelQueryWizard;
use Jackardios\QueryWizard\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Model\ModelInclude;
use Jackardios\QueryWizard\Model\Includes\CountInclude;
use Jackardios\QueryWizard\Model\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use Jackardios\QueryWizard\Tests\App\Models\MorphModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;

/**
 * @group model
 * @group include
 * @group model-include
 */
class IncludeTest extends TestCase
{
    protected TestModel $model;

    public function setUp(): void
    {
        parent::setUp();

        $this->model = factory(TestModel::class)->create()->first();
        $this->model
            ->relatedModels()->create(['name' => 'Test'])
            ->nestedRelatedModels()->create(['name' => 'Test']);

        $this->model->morphModels()->create(['name' => 'Test']);

        $this->model->relatedThroughPivotModels()->create([
            'id' => $this->model->id + 1,
            'name' => 'Test',
        ]);
    }

    /** @test */
    public function it_can_handle_empty_includes(): void
    {
        $model = TestModel::find($this->model->getKey());
        $modelWizard = $this->createModelWizardFromQuery([], $model)
            ->setAllowedIncludes([
                null,
                [],
                '',
            ])
            ->build();

        $this->assertTrue($modelWizard->is($model));
    }

    /** @test */
    public function it_can_include_model_relations(): void
    {
        $model = $this
            ->createModelWizardWithIncludes('relatedModels')
            ->setAllowedIncludes('relatedModels')
            ->build();

        $this->assertRelationLoaded($model, 'relatedModels');
    }

    /** @test */
    public function it_can_include_model_relations_by_alias(): void
    {
        $model = $this
            ->createModelWizardWithIncludes('include-alias')
            ->setAllowedIncludes(new RelationshipInclude('relatedModels', 'include-alias'))
            ->build();

        $this->assertRelationLoaded($model, 'relatedModels');
    }

    /** @test */
    public function it_can_include_an_includes_count(): void
    {
        $model = $this
            ->createModelWizardWithIncludes('relatedModelsCount')
            ->setAllowedIncludes('relatedModelsCount')
            ->build();

        $this->assertNotNull($model->related_models_count);
    }

    /** @test */
    public function allowing_an_include_also_allows_the_include_count(): void
    {
        $model = $this
            ->createModelWizardWithIncludes('relatedModelsCount')
            ->setAllowedIncludes('relatedModels')
            ->build();

        $this->assertNotNull($model->related_models_count);
    }

    /** @test */
    public function it_can_include_nested_model_relations(): void
    {
        $model = $this
            ->createModelWizardWithIncludes('relatedModels.nestedRelatedModels')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels')
            ->build();

        $this->assertRelationLoaded($model->relatedModels->first(), 'nestedRelatedModels');
    }

    /** @test */
    public function it_can_include_nested_model_relations_by_alias(): void
    {
        $model = $this
            ->createModelWizardWithIncludes('nested-alias')
            ->setAllowedIncludes(
                new RelationshipInclude('relatedModels.nestedRelatedModels', 'nested-alias')
            )
            ->build();

        $this->assertRelationLoaded($model->relatedModels->first(), 'nestedRelatedModels');
    }

    /** @test */
    public function it_can_include_model_relations_from_nested_model_relations(): void
    {
        $model = $this
            ->createModelWizardWithIncludes('relatedModels')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels')
            ->build();

        $this->assertRelationLoaded($model, 'relatedModels');
    }

    /** @test */
    public function allowing_a_nested_include_only_allows_the_include_count_for_the_first_level(): void
    {
        $model = $this
            ->createModelWizardWithIncludes('relatedModelsCount')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels')
            ->build();

        $this->assertNotNull($model->related_models_count);

        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createModelWizardWithIncludes('nestedRelatedModelsCount')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels')
            ->build();

        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createModelWizardWithIncludes('related-models.nestedRelatedModelsCount')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels')
            ->build();
    }

    /** @test */
    public function it_can_include_morph_model_relations(): void
    {
        $model = $this
            ->createModelWizardWithIncludes('morphModels')
            ->setAllowedIncludes('morphModels')
            ->build();

        $this->assertRelationLoaded($model, 'morphModels');
    }

    /** @test */
    public function it_can_include_reverse_morph_model_relations(): void
    {
        $model = $this->createModelWizardWithIncludes('parent', MorphModel::query()->first())
            ->setAllowedIncludes('parent')
            ->build();

        $this->assertRelationLoaded($model, 'parent');
    }

    /** @test */
    public function it_can_include_camel_case_includes(): void
    {
        $model = $this
            ->createModelWizardWithIncludes('relatedModels')
            ->setAllowedIncludes('relatedModels')
            ->build();

        $this->assertRelationLoaded($model, 'relatedModels');
    }

    /** @test */
    public function it_guards_against_invalid_includes(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createModelWizardWithIncludes('random-model')
            ->setAllowedIncludes('relatedModels')
            ->build();
    }

    /** @test */
    public function it_can_allow_multiple_includes(): void
    {
        $model = $this
            ->createModelWizardWithIncludes('relatedModels')
            ->setAllowedIncludes('relatedModels', 'otherRelatedModels')
            ->build();

        $this->assertRelationLoaded($model, 'relatedModels');
    }

    /** @test */
    public function it_can_allow_multiple_includes_as_an_array(): void
    {
        $model = $this
            ->createModelWizardWithIncludes('relatedModels')
            ->setAllowedIncludes(['relatedModels', 'otherRelatedModels'])
            ->build();

        $this->assertRelationLoaded($model, 'relatedModels');
    }

    /** @test */
    public function it_can_include_multiple_model_relations(): void
    {
        $model = $this
            ->createModelWizardWithIncludes('relatedModels,otherRelatedModels')
            ->setAllowedIncludes(['relatedModels', 'otherRelatedModels'])
            ->build();

        $this->assertRelationLoaded($model, 'relatedModels');
        $this->assertRelationLoaded($model, 'otherRelatedModels');
    }

    /** @test */
    public function it_can_query_included_many_to_many_relationships(): void
    {
        DB::enableQueryLog();

        $this
            ->createModelWizardWithIncludes('relatedThroughPivotModels')
            ->setAllowedIncludes('relatedThroughPivotModels')
            ->build();

        // Based on the following query: TestModel::with('relatedThroughPivotModels')->get();
        // Without where-clause as that differs per Laravel version
        //dump(DB::getQueryLog());
        $this->assertQueryLogContains('select `related_through_pivot_models`.*, `pivot_models`.`test_model_id` as `pivot_test_model_id`, `pivot_models`.`related_through_pivot_model_id` as `pivot_related_through_pivot_model_id` from `related_through_pivot_models` inner join `pivot_models` on `related_through_pivot_models`.`id` = `pivot_models`.`related_through_pivot_model_id` where `pivot_models`.`test_model_id` in (1)');
    }

    /** @test */
    public function it_returns_correct_id_when_including_many_to_many_relationship(): void
    {
        $model = $this
            ->createModelWizardWithIncludes('relatedThroughPivotModels')
            ->setAllowedIncludes('relatedThroughPivotModels')
            ->build();

        $relatedModel = $model->relatedThroughPivotModels->first();

        $this->assertEquals($relatedModel->id, $relatedModel->pivot->related_through_pivot_model_id);
    }

    /** @test */
    public function an_invalid_include_query_exception_contains_the_unknown_and_allowed_includes(): void
    {
        $exception = new InvalidIncludeQuery(collect(['unknown include']), collect(['allowed include']));

        $this->assertEquals(['unknown include'], $exception->unknownIncludes->all());
        $this->assertEquals(['allowed include'], $exception->allowedIncludes->all());
    }

    /** @test */
    public function it_can_alias_multiple_allowed_includes(): void
    {
        $model = $this->createModelWizardWithIncludes('relatedModelsCount,relationShipAlias')
            ->setAllowedIncludes([
                new CountInclude('relatedModels', 'relatedModelsCount'),
                new RelationshipInclude('otherRelatedModels', 'relationShipAlias'),
            ])
            ->build();

        $this->assertRelationLoaded($model, 'otherRelatedModels');
        $this->assertNotNull($model->related_models_count);
    }

    /** @test */
    public function it_can_include_custom_include_class(): void
    {
        $includeClass = new class('relatedModels') extends ModelInclude {
            public function handle($queryWizard, $model): void
            {
                $model->loadCount($this->getInclude());
            }
        };

        $modelResult = $this
            ->createModelWizardWithIncludes('relatedModels')
            ->setAllowedIncludes($includeClass)
            ->build();

        $this->assertNotNull($modelResult->related_models_count);
    }

    /** @test */
    public function it_can_include_custom_include_class_by_alias(): void
    {
        $includeClass = new class('relatedModels', 'relatedModelsCount') extends ModelInclude {
            public function handle($queryWizard, $model): void
            {
                $model->loadCount($this->getInclude());
            }
        };

        $modelResult = $this
            ->createModelWizardWithIncludes('relatedModelsCount')
            ->setAllowedIncludes($includeClass)
            ->build();

        $this->assertNotNull($modelResult->related_models_count);
    }

    /**
     * @param Model|ModelQueryWizard $model
     * @param string $relation
     */
    protected function assertRelationLoaded($model, string $relation): void
    {
        $this->assertTrue($model->relationLoaded($relation), "The `{$relation}` relation was expected but not loaded.");
    }


    protected function createModelWizardWithIncludes(array|string $includes, $model = null): ModelQueryWizard
    {
        return parent::createModelWizardWithIncludes($includes, $model ?? $this->model);
    }
}

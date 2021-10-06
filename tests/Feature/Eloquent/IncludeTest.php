<?php

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\AbstractEloquentInclude;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\CountInclude;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Tests\Concerns\AssertsRelations;
use Jackardios\QueryWizard\Tests\TestCase;
use ReflectionClass;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use Jackardios\QueryWizard\EloquentQueryWizard;
use Jackardios\QueryWizard\Tests\App\Models\MorphModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;

/**
 * @group eloquent
 * @group include
 * @group eloquent-include
 */
class IncludeTest extends TestCase
{
    use AssertsRelations;

    /** @var \Illuminate\Support\Collection */
    protected $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 5)->create();

        $this->models->each(function (TestModel $model) {
            $model
                ->relatedModels()->create(['name' => 'Test'])
                ->nestedRelatedModels()->create(['name' => 'Test']);

            $model->morphModels()->create(['name' => 'Test']);

            $model->relatedThroughPivotModels()->create([
                'id' => $model->id + 1,
                'name' => 'Test',
            ]);
        });
    }

    /** @test */
    public function it_does_not_require_includes(): void
    {
        $models = EloquentQueryWizard::for(TestModel::class, new Request())
            ->setAllowedIncludes('relatedModels')
            ->build()
            ->get();

        $this->assertCount(TestModel::count(), $models);
    }

    /** @test */
    public function it_can_handle_empty_includes(): void
    {
        $models = EloquentQueryWizard::for(TestModel::class, new Request())
            ->setAllowedIncludes([
                null,
                [],
                '',
            ])
            ->build()
            ->get();

        $this->assertCount(TestModel::count(), $models);
    }

    /** @test */
    public function it_can_include_model_relations(): void
    {
        $models = $this
            ->createWizardFromIncludeRequest('relatedModels')
            ->setAllowedIncludes('relatedModels')
            ->build()
            ->get();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function it_can_include_model_relations_by_alias(): void
    {
        $models = $this
            ->createWizardFromIncludeRequest('include-alias')
            ->setAllowedIncludes(new RelationshipInclude('relatedModels', 'include-alias'))
            ->build()
            ->get();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function it_can_include_an_includes_count(): void
    {
        $model = $this
            ->createWizardFromIncludeRequest('relatedModelsCount')
            ->setAllowedIncludes('relatedModelsCount')
            ->build()
            ->first();

        $this->assertNotNull($model->related_models_count);
    }

    /** @test */
    public function allowing_an_include_also_allows_the_include_count(): void
    {
        $model = $this
            ->createWizardFromIncludeRequest('relatedModelsCount')
            ->setAllowedIncludes('relatedModels')
            ->build()
            ->first();

        $this->assertNotNull($model->related_models_count);
    }

    /** @test */
    public function it_can_include_nested_model_relations(): void
    {
        $models = $this
            ->createWizardFromIncludeRequest('relatedModels.nestedRelatedModels')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels')
            ->build()
            ->get();

        $models->each(function (Model $model) {
            $this->assertRelationLoaded($model->relatedModels, 'nestedRelatedModels');
        });
    }

    /** @test */
    public function it_can_include_nested_model_relations_by_alias(): void
    {
        $models = $this
            ->createWizardFromIncludeRequest('nested-alias')
            ->setAllowedIncludes(
                new RelationshipInclude('relatedModels.nestedRelatedModels', 'nested-alias')
            )
            ->build()
            ->get();

        $models->each(function (TestModel $model) {
            $this->assertRelationLoaded($model->relatedModels, 'nestedRelatedModels');
        });
    }

    /** @test */
    public function it_can_include_model_relations_from_nested_model_relations(): void
    {
        $models = $this
            ->createWizardFromIncludeRequest('relatedModels')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels')
            ->build()
            ->get();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function allowing_a_nested_include_only_allows_the_include_count_for_the_first_level(): void
    {
        $model = $this
            ->createWizardFromIncludeRequest('relatedModelsCount')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels')
            ->build()
            ->first();

        $this->assertNotNull($model->related_models_count);

        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createWizardFromIncludeRequest('nestedRelatedModelsCount')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels')
            ->build()
            ->first();

        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createWizardFromIncludeRequest('related-models.nestedRelatedModelsCount')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels')
            ->build()
            ->first();
    }

    /** @test */
    public function it_can_include_morph_model_relations(): void
    {
        $models = $this
            ->createWizardFromIncludeRequest('morphModels')
            ->setAllowedIncludes('morphModels')
            ->build()
            ->get();

        $this->assertRelationLoaded($models, 'morphModels');
    }

    /** @test */
    public function it_can_include_reverse_morph_model_relations(): void
    {
        $request = new Request([
            'include' => 'parent',
        ]);

        $models = EloquentQueryWizard::for(MorphModel::class, $request)
            ->setAllowedIncludes('parent')
            ->build()
            ->get();

        $this->assertRelationLoaded($models, 'parent');
    }

    /** @test */
    public function it_can_include_camel_case_includes(): void
    {
        $models = $this
            ->createWizardFromIncludeRequest('relatedModels')
            ->setAllowedIncludes('relatedModels')
            ->build()
            ->get();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function it_can_include_models_on_an_empty_collection(): void
    {
        TestModel::query()->delete();

        $models = $this
            ->createWizardFromIncludeRequest('relatedModels')
            ->setAllowedIncludes('relatedModels')
            ->build()
            ->get();

        $this->assertCount(0, $models);
    }

    /** @test */
    public function it_guards_against_invalid_includes(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createWizardFromIncludeRequest('random-model')
            ->setAllowedIncludes('relatedModels')
            ->build();
    }

    /** @test */
    public function it_can_allow_multiple_includes(): void
    {
        $models = $this
            ->createWizardFromIncludeRequest('relatedModels')
            ->setAllowedIncludes('relatedModels', 'otherRelatedModels')
            ->build()
            ->get();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function it_can_allow_multiple_includes_as_an_array(): void
    {
        $models = $this
            ->createWizardFromIncludeRequest('relatedModels')
            ->setAllowedIncludes(['relatedModels', 'otherRelatedModels'])
            ->build()
            ->get();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function it_can_remove_duplicate_includes_from_nested_includes(): void
    {
        $query = $this
            ->createWizardFromIncludeRequest('relatedModels')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels', 'relatedModels')
            ->build();

        $property = (new ReflectionClass($query))->getProperty('allowedIncludes');
        $property->setAccessible(true);

        $includes = $property->getValue($query)->map(function (AbstractEloquentInclude $allowedInclude) {
            return $allowedInclude->getName();
        });

        $this->assertTrue($includes->contains('relatedModels'));
        $this->assertTrue($includes->contains('relatedModelsCount'));
        $this->assertTrue($includes->contains('relatedModels.nestedRelatedModels'));
    }

    /** @test */
    public function it_can_include_multiple_model_relations(): void
    {
        $models = $this
            ->createWizardFromIncludeRequest('relatedModels,otherRelatedModels')
            ->setAllowedIncludes(['relatedModels', 'otherRelatedModels'])
            ->build()
            ->get();

        $this->assertRelationLoaded($models, 'relatedModels');
        $this->assertRelationLoaded($models, 'otherRelatedModels');
    }

    /** @test */
    public function it_can_query_included_many_to_many_relationships(): void
    {
        DB::enableQueryLog();

        $this
            ->createWizardFromIncludeRequest('relatedThroughPivotModels')
            ->setAllowedIncludes('relatedThroughPivotModels')
            ->build()
            ->get();

        // Based on the following query: TestModel::with('relatedThroughPivotModels')->get();
        // Without where-clause as that differs per Laravel version
        //dump(DB::getQueryLog());
        $this->assertQueryLogContains('select `related_through_pivot_models`.*, `pivot_models`.`test_model_id` as `pivot_test_model_id`, `pivot_models`.`related_through_pivot_model_id` as `pivot_related_through_pivot_model_id` from `related_through_pivot_models` inner join `pivot_models` on `related_through_pivot_models`.`id` = `pivot_models`.`related_through_pivot_model_id` where `pivot_models`.`test_model_id` in (1, 2, 3, 4, 5)');
    }

    /** @test */
    public function it_returns_correct_id_when_including_many_to_many_relationship(): void
    {
        $models = $this
            ->createWizardFromIncludeRequest('relatedThroughPivotModels')
            ->setAllowedIncludes('relatedThroughPivotModels')
            ->build()
            ->get();

        $relatedModel = $models->first()->relatedThroughPivotModels->first();

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
        $request = new Request([
            'include' => 'relatedModelsCount,relationShipAlias',
        ]);

        $models = EloquentQueryWizard::for(TestModel::class, $request)
            ->setAllowedIncludes([
                new CountInclude('relatedModels', 'relatedModelsCount'),
                new RelationshipInclude('otherRelatedModels', 'relationShipAlias'),
            ])
            ->build()
            ->get();

        $this->assertRelationLoaded($models, 'otherRelatedModels');
        $models->each(function ($model) {
            $this->assertNotNull($model->related_models_count);
        });
    }

    /** @test */
    public function it_can_include_custom_include_class(): void
    {
        $includeClass = new class('relatedModels') extends AbstractEloquentInclude {
            public function handle($queryHandler, $queryBuilder): void
            {
                $queryBuilder->withCount($this->getInclude());
            }
        };

        $modelResult = $this
            ->createWizardFromIncludeRequest('relatedModels')
            ->setAllowedIncludes($includeClass)
            ->build()
            ->first();

        $this->assertNotNull($modelResult->related_models_count);
    }

    /** @test */
    public function it_can_include_custom_include_class_by_alias(): void
    {
        $includeClass = new class('relatedModels', 'relatedModelsCount') extends AbstractEloquentInclude {
            public function handle($queryHandler, $queryBuilder): void
            {
                $queryBuilder->withCount($this->getInclude());
            }
        };

        $modelResult = $this
            ->createWizardFromIncludeRequest('relatedModelsCount')
            ->setAllowedIncludes($includeClass)
            ->build()
            ->first();

        $this->assertNotNull($modelResult->related_models_count);
    }

    /** @test */
    public function it_can_include_a_custom_base_query_with_select(): void
    {
        $request = new Request([
            'include' => 'relatedModelsCount',
        ]);

        $modelResult = EloquentQueryWizard::for(TestModel::select('id', 'name'), $request)
            ->setAllowedIncludes(new CountInclude('relatedModels', 'relatedModelsCount'))
            ->build()
            ->first();

        $this->assertNotNull($modelResult->related_models_count);
    }

    protected function createWizardFromIncludeRequest(string $includes): EloquentQueryWizard
    {
        $request = new Request([
            'include' => $includes,
        ]);

        return EloquentQueryWizard::for(TestModel::class, $request);
    }
}

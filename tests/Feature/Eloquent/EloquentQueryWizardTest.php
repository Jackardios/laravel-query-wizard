<?php

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\Handlers\Eloquent\Sorts\AbstractEloquentSort;
use Jackardios\QueryWizard\Values\Sort;
use ReflectionClass;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Exceptions\InvalidSubject;
use Jackardios\QueryWizard\EloquentQueryWizard;
use Jackardios\QueryWizard\QueryWizardRequest;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedThroughPivotModel;
use Jackardios\QueryWizard\Tests\App\Models\ScopeModel;
use Jackardios\QueryWizard\Tests\App\Models\SoftDeleteModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;

/**
 * @group eloquent
 * @group wizard
 * @group eloquent-wizard
 */
class EloquentQueryWizardTest extends TestCase
{
    /** @test */
    public function it_can_be_given_an_eloquent_query_using_where(): void
    {
        $queryWizard = EloquentQueryWizard::for(TestModel::where('id', 1))->build();

        $eloquentBuilder = TestModel::where('id', 1);

        $this->assertEquals(
            $eloquentBuilder->toSql(),
            $queryWizard->toSql()
        );
    }

    /** @test */
    public function it_can_be_given_an_eloquent_query_using_select(): void
    {
        $queryWizard = EloquentQueryWizard::for(TestModel::select('id', 'name'))->build();

        $eloquentBuilder = TestModel::select('id', 'name');

        $this->assertEquals(
            $eloquentBuilder->toSql(),
            $queryWizard->toSql()
        );
    }

    /** @test */
    public function it_can_be_given_a_belongs_to_many_relation_query(): void
    {
        $testModel = TestModel::create(['id' => 321, 'name' => 'John Doe']);
        $relatedThroughPivotModel = RelatedThroughPivotModel::create(['id' => 789, 'name' => 'The related model']);

        $testModel->relatedThroughPivotModels()->attach($relatedThroughPivotModel);

        $queryWizardResult = EloquentQueryWizard::for($testModel->relatedThroughPivotModels())->build()->first();

        $this->assertEquals(789, $queryWizardResult->id);
    }

    /** @test */
    public function it_can_be_given_a_belongs_to_many_relation_query_with_pivot(): void
    {
        /** @var TestModel $testModel */
        $testModel = TestModel::create(['id' => 329, 'name' => 'Illia']);

        $queryWizard = EloquentQueryWizard::for($testModel->relatedThroughPivotModelsWithPivot())->build();

        $eloquentBuilder = $testModel->relatedThroughPivotModelsWithPivot();

        $this->assertEquals(
            $eloquentBuilder->toSql(),
            $queryWizard->toSql()
        );
    }

    /** @test */
    public function it_can_be_given_a_model_class_name(): void
    {
        $queryWizard = EloquentQueryWizard::for(TestModel::class)->build();

        $this->assertEquals(
            TestModel::query()->toSql(),
            $queryWizard->toSql()
        );
    }

    /** @test */
    public function it_can_not_be_given_a_string_that_is_not_a_class_name(): void
    {
        $this->expectException(InvalidSubject::class);

        $this->expectExceptionMessage('Subject type `string` is invalid.');

        EloquentQueryWizard::for('not a class name');
    }

    /** @test */
    public function it_can_not_be_given_an_object_that_is_neither_relation_nor_eloquent_builder(): void
    {
        $this->expectException(InvalidSubject::class);

        $this->expectExceptionMessage(sprintf('Subject class `%s` is invalid.', self::class));

        EloquentQueryWizard::for($this);
    }

    /** @test */
    public function it_will_determine_the_request_when_its_not_given(): void
    {
        $builderReflection = new ReflectionClass(EloquentQueryWizard::class);
        $requestProperty = $builderReflection->getProperty('request');
        $requestProperty->setAccessible(true);

        $this->getJson('/test-model?sort=name');

        $wizard = EloquentQueryWizard::for(TestModel::class)->build();

        $this->assertInstanceOf(QueryWizardRequest::class, $requestProperty->getValue($wizard));
        $this->assertEquals(
            ['name'],
            $requestProperty
                ->getValue($wizard)
                ->sorts()
                ->map(fn(Sort $sort) => $sort->getField())
                ->toArray()
        );
    }

    /** @test */
    public function it_can_query_soft_deletes(): void
    {
        $queryWizard = EloquentQueryWizard::for(SoftDeleteModel::class);

        $this->models = factory(SoftDeleteModel::class, 5)->create();

        $this->assertCount(5, $queryWizard->get());

        $this->models[0]->delete();

        $this->assertCount(4, $queryWizard->get());
        $this->assertCount(5, $queryWizard->withTrashed()->get());
    }

    /** @test */
    public function it_can_query_global_scopes(): void
    {
        ScopeModel::create(['name' => 'John Doe']);
        ScopeModel::create(['name' => 'test']);

        // Global scope on ScopeModel excludes models named 'test'
        $this->assertCount(1, EloquentQueryWizard::for(ScopeModel::class)->build()->get());

        $this->assertCount(2, EloquentQueryWizard::for(ScopeModel::query()->withoutGlobalScopes())->build()->get());

        $this->assertCount(2, EloquentQueryWizard::for(ScopeModel::class)->build()->withoutGlobalScopes()->get());
    }

    /** @test */
    public function it_keeps_eager_loaded_relationships_from_the_base_query(): void
    {
        TestModel::create(['name' => 'John Doe']);

        $baseQuery = TestModel::with('relatedModels');
        $queryWizard = EloquentQueryWizard::for($baseQuery)->build();

        $this->assertTrue($baseQuery->first()->relationLoaded('relatedModels'));
        $this->assertTrue($queryWizard->first()->relationLoaded('relatedModels'));
    }

    /** @test */
    public function it_keeps_local_macros_added_to_the_base_query(): void
    {
        $baseQuery = TestModel::query();

        $baseQuery->macro('customMacro', function ($builder) {
            return $builder->where('name', 'Foo');
        });

        $queryWizard = EloquentQueryWizard::for(clone $baseQuery)->build();

        $this->assertEquals(
            $baseQuery->customMacro()->toSql(),
            $queryWizard->customMacro()->toSql()
        );
    }

    /** @test */
    public function it_keeps_the_on_delete_callback_added_to_the_base_query(): void
    {
        $baseQuery = TestModel::query();

        $baseQuery->onDelete(function () {
            return 'onDelete called';
        });

        $this->assertEquals('onDelete called', EloquentQueryWizard::for($baseQuery)->build()->delete());
    }

    /** @test */
    public function it_can_query_local_scopes(): void
    {
        $queryWizardQuery = EloquentQueryWizard::for(TestModel::class)
            ->build()
            ->named('john')
            ->toSql();

        $expectedQuery = TestModel::query()->where('name', 'john')->toSql();

        $this->assertEquals($expectedQuery, $queryWizardQuery);
    }

    /** @test */
    public function it_executes_the_same_query_regardless_of_the_order_of_applied_filters_or_sorts(): void
    {
        $customSort = new class('name') extends AbstractEloquentSort {
            public function handle($queryHandler, $query, string $direction): void
            {
                $query->join(
                    'related_models',
                    'test_models.id',
                    '=',
                    'related_models.test_model_id'
                )->orderBy('related_models.name', $direction);
            }
        };

        $req = new Request([
            'filter' => ['name' => 'test'],
            'sort' => 'name',
        ]);

        $usingSortFirst = EloquentQueryWizard::for(TestModel::class, $req)
            ->setAllowedSorts($customSort)
            ->setAllowedFilters('name')
            ->build()
            ->toSql();

        $usingFilterFirst = EloquentQueryWizard::for(TestModel::class, $req)
            ->setAllowedFilters('name')
            ->setAllowedSorts($customSort)
            ->build()
            ->toSql();

        $this->assertEquals($usingSortFirst, $usingFilterFirst);
    }

    /** @test */
    public function it_can_filter_when_sorting_by_joining_a_related_model_which_contains_the_same_field_name(): void
    {
        $customSort = new class('name') extends AbstractEloquentSort {
            public function handle($queryHandler, $query, string $direction): void
            {
                $query->join(
                    'related_models',
                    'nested_related_models.related_model_id',
                    '=',
                    'related_models.id'
                )->orderBy('related_models.name', $direction);
            }
        };

        $req = new Request([
            'filter' => ['name' => 'test'],
            'sort' => 'name',
        ]);

        EloquentQueryWizard::for(NestedRelatedModel::class, $req)
            ->setAllowedSorts($customSort)
            ->setAllowedFilters('name')
            ->build()
            ->get();

        $this->assertTrue(true);
    }

    /** @test */
    public function it_queries_the_correct_data_for_a_relationship_query(): void
    {
        $testModel = TestModel::create(['id' => 321, 'name' => 'John Doe']);
        $relatedThroughPivotModel = RelatedThroughPivotModel::create(['id' => 789, 'name' => 'The related model']);

        $testModel->relatedThroughPivotModels()->attach($relatedThroughPivotModel);

        $relationship = $testModel->relatedThroughPivotModels()->with('testModels');

        $queryWizardResult = EloquentQueryWizard::for($relationship)->build()->first();

        $this->assertEquals(789, $queryWizardResult->id);
        $this->assertEquals(321, $queryWizardResult->testModels->first()->id);
    }

    /** @test */
    public function it_does_not_lose_pivot_values_with_belongs_to_many_relation(): void
    {
        /** @var TestModel $testModel */
        $testModel = TestModel::create(['id' => 324, 'name' => 'Illia']);

        /** @var RelatedThroughPivotModel $relatedThroughPivotModel */
        $relatedThroughPivotModel = RelatedThroughPivotModel::create(['id' => 721, 'name' => 'Kate']);

        $testModel->relatedThroughPivotModelsWithPivot()->attach($relatedThroughPivotModel, ['location' => 'Wood Cottage']);

        $foundTestModel = EloquentQueryWizard::for($testModel->relatedThroughPivotModelsWithPivot())
            ->build()
            ->first();

        $this->assertSame(
            'Wood Cottage',
            $foundTestModel->pivot->location
        );
    }


    /** @test */
    public function it_clones_the_subject_upon_cloning(): void
    {
        $queryWizard = EloquentQueryWizard::for(TestModel::class)->build();

        $queryWizard1 = (clone $queryWizard)->where('id', 1);
        $queryWizard2 = (clone $queryWizard)->where('name', 'John Doe');

        $this->assertNotSame(
            $queryWizard1->toSql(),
            $queryWizard2->toSql()
        );
    }

    /** @test */
    public function it_supports_clone_as_method(): void
    {
        $queryWizard = EloquentQueryWizard::for(TestModel::class)->build();

        $queryWizard1 = $queryWizard->clone()->where('id', 1);
        $queryWizard2 = $queryWizard->clone()->where('name', 'John Doe');

        $this->assertNotSame(
            $queryWizard1->toSql(),
            $queryWizard2->toSql()
        );
    }
}

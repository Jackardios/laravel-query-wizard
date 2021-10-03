<?php

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\AbstractEloquentFilter;
use Jackardios\QueryWizard\EloquentQueryWizard;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\FiltersExact;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\FiltersPartial;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\FiltersScope;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;

/**
 * @group eloquent
 * @group filter
 * @group eloquent-filter
 */
class FilterTest extends TestCase
{
    /** @var Collection */
    protected $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 5)->create();
    }

    /** @test */
    public function it_can_filter_models_by_exact_property_by_default(): void
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'name' => $this->models->first()->name,
            ])
            ->setAllowedFilters('name')
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_can_filter_models_by_an_array_as_filter_value(): void
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'name' => ['first' => $this->models->first()->name],
            ])
            ->setAllowedFilters('name')
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_can_filter_partially_and_case_insensitive(): void
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'name' => strtoupper($this->models->first()->name),
            ])
            ->setAllowedFilters('name')
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_can_filter_results_based_on_the_partial_existence_of_a_property_in_an_array(): void
    {
        $model1 = TestModel::create(['name' => 'abcdef']);
        $model2 = TestModel::create(['name' => 'uvwxyz']);

        $results = $this
            ->createQueryFromFilterRequest([
                'name' => 'abc,xyz',
            ])
            ->setAllowedFilters(new FiltersPartial('name'))
            ->build()
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals([$model1->id, $model2->id], $results->pluck('id')->all());
    }

    /** @test */
    public function it_can_filter_models_and_return_an_empty_collection(): void
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'name' => 'None existing first name',
            ])
            ->setAllowedFilters('name')
            ->build()
            ->get();

        $this->assertCount(0, $models);
    }

    /** @test */
    public function it_can_filter_a_custom_base_query_with_select(): void
    {
        $request = new Request([
            'filter' => ['name' => 'john'],
        ]);

        $queryWizardSql = EloquentQueryWizard::for(TestModel::select('id', 'name'), $request)
            ->setAllowedFilters('name', 'id')
            ->build()
            ->toSql();

        $expectedSql = TestModel::select('id', 'name')
            ->where(DB::raw('`test_models`.`name`'), '=', 'john')
            ->toSql();

        $this->assertEquals($expectedSql, $queryWizardSql);
    }

    /** @test */
    public function it_can_filter_results_based_on_the_existence_of_a_property_in_an_array(): void
    {
        $results = $this
            ->createQueryFromFilterRequest([
                'id' => '1,2',
            ])
            ->setAllowedFilters('id')
            ->build()
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals([1, 2], $results->pluck('id')->all());
    }

    /** @test */
    public function it_ignores_empty_values_in_an_array_partial_filter(): void
    {
        $results = $this
            ->createQueryFromFilterRequest([
                'id' => '2,',
            ])
            ->setAllowedFilters(new FiltersPartial('id'))
            ->build()
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals([2], $results->pluck('id')->all());
    }

    /** @test */
    public function it_ignores_an_empty_array_partial_filter(): void
    {
        $results = $this
            ->createQueryFromFilterRequest([
                'id' => ',,',
            ])
            ->setAllowedFilters(new FiltersPartial('id'))
            ->build()
            ->get();

        $this->assertCount(5, $results);
    }

    /** @test */
    public function falsy_values_are_not_ignored_when_applying_a_partial_filter(): void
    {
        DB::enableQueryLog();

        $this
            ->createQueryFromFilterRequest([
                'id' => [0],
            ])
            ->setAllowedFilters(new FiltersPartial('id'))
            ->build()
            ->get();

        $this->assertQueryLogContains("select * from `test_models` where (LOWER(`test_models`.`id`) LIKE ?)");
    }

    /** @test */
    public function it_can_filter_and_match_results_by_exact_property(): void
    {
        $testModel = TestModel::first();

        $models = TestModel::where('id', $testModel->id)
            ->get();

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'id' => $testModel->id,
            ])
            ->setAllowedFilters(new FiltersExact('id'))
            ->build()
            ->get();

        $this->assertEquals($modelsResult, $models);
    }

    /** @test */
    public function it_can_filter_and_reject_results_by_exact_property(): void
    {
        $testModel = TestModel::create(['name' => 'John Testing Doe']);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'name' => ' Testing ',
            ])
            ->setAllowedFilters(new FiltersExact('name'))
            ->build()
            ->get();

        $this->assertCount(0, $modelsResult);
    }

    /** @test */
    public function it_can_filter_results_by_scope(): void
    {
        $testModel = TestModel::create(['name' => 'John Testing Doe']);

        $modelsResult = $this
            ->createQueryFromFilterRequest(['named' => 'John Testing Doe'])
            ->setAllowedFilters(new FiltersScope('named'))
            ->build()
            ->get();

        $this->assertCount(1, $modelsResult);
    }

    /** @test */
    public function it_can_filter_results_by_nested_relation_scope(): void
    {
        $testModel = TestModel::create(['name' => 'John Testing Doe 234234']);
        $testModel->relatedModels()->create(['name' => 'John\'s Post']);

        $modelsResult = $this
            ->createQueryFromFilterRequest(['relatedModels.named' => 'John\'s Post'])
            ->setAllowedFilters(new FiltersScope('relatedModels.named'))
            ->build()
            ->get();

        $this->assertCount(1, $modelsResult);
    }

    /** @test */
    public function it_can_filter_results_by_type_hinted_scope(): void
    {
        TestModel::create(['name' => 'John Testing Doe']);

        $modelsResult = $this
            ->createQueryFromFilterRequest(['user' => 1])
            ->setAllowedFilters(new FiltersScope('user'))
            ->build()
            ->get();

        $this->assertCount(1, $modelsResult);
    }

    /** @test */
    public function it_can_filter_results_by_regular_and_type_hinted_scope(): void
    {
        TestModel::create(['id' => 1000, 'name' => 'John Testing Doe']);

        $modelsResult = $this
            ->createQueryFromFilterRequest(['user_info' => ['id' => '1000', 'name' => 'John Testing Doe']])
            ->setAllowedFilters(new FiltersScope('user_info'))
            ->build()
            ->get();

        $this->assertCount(1, $modelsResult);
    }

    /** @test */
    public function it_can_filter_results_by_scope_with_multiple_parameters(): void
    {
        Carbon::setTestNow(Carbon::parse('2016-05-05'));

        $testModel = TestModel::create(['name' => 'John Testing Doe']);

        $modelsResult = $this
            ->createQueryFromFilterRequest(['created_between' => '2016-01-01,2017-01-01'])
            ->setAllowedFilters(new FiltersScope('created_between'))
            ->build()
            ->get();

        $this->assertCount(1, $modelsResult);
    }

    /** @test */
    public function it_can_filter_results_by_scope_with_multiple_parameters_in_an_associative_array(): void
    {
        Carbon::setTestNow(Carbon::parse('2016-05-05'));

        $testModel = TestModel::create(['name' => 'John Testing Doe']);

        $modelsResult = $this
            ->createQueryFromFilterRequest(['created_between' => ['start' => '2016-01-01', 'end' => '2017-01-01']])
            ->setAllowedFilters(new FiltersScope('created_between'))
            ->build()
            ->get();

        $this->assertCount(1, $modelsResult);
    }

    /** @test */
    public function it_can_filter_results_by_a_custom_filter_class(): void
    {
        $testModel = $this->models->first();

        $filterClass = new class('custom_name') extends AbstractEloquentFilter {
            public function handle($queryHandler, $query, $value): void
            {
                $query->where('name', $value);
            }
        };

        $modelResult = $this
            ->createQueryFromFilterRequest([
                'custom_name' => $testModel->name,
            ])
            ->setAllowedFilters($filterClass)
            ->build()
            ->first();

        $this->assertEquals($testModel->id, $modelResult->id);
    }

    /** @test */
    public function it_can_allow_multiple_filters(): void
    {
        $model1 = TestModel::create(['name' => 'abcdef']);
        $model2 = TestModel::create(['name' => 'abcdef']);

        $results = $this
            ->createQueryFromFilterRequest([
                'name' => 'abc',
            ])
            ->setAllowedFilters(new FiltersPartial('name'), 'id')
            ->build()
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals([$model1->id, $model2->id], $results->pluck('id')->all());
    }

    /** @test */
    public function it_can_allow_multiple_filters_as_an_array(): void
    {
        $model1 = TestModel::create(['name' => 'abcdef']);
        $model2 = TestModel::create(['name' => 'abcdef']);

        $results = $this
            ->createQueryFromFilterRequest([
                'name' => 'abc',
            ])
            ->setAllowedFilters([new FiltersPartial('name'), 'id'])
            ->build()
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals([$model1->id, $model2->id], $results->pluck('id')->all());
    }

    /** @test */
    public function it_can_filter_by_multiple_filters(): void
    {
        $model1 = TestModel::create(['name' => 'abcdef']);
        $model2 = TestModel::create(['name' => 'abcdef']);

        $results = $this
            ->createQueryFromFilterRequest([
                'name' => 'abc',
                'id' => "1,{$model1->id}",
            ])
            ->setAllowedFilters(new FiltersPartial('name'), 'id')
            ->build()
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals([$model1->id], $results->pluck('id')->all());
    }

    /** @test */
    public function it_guards_against_invalid_filters(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createQueryFromFilterRequest(['name' => 'John'])
            ->setAllowedFilters('id')
            ->build();
    }

    /** @test */
    public function it_can_create_a_custom_filter_with_an_instantiated_filter(): void
    {
        $customFilter = new class('*') extends AbstractEloquentFilter {
            public function handle($queryHandler, $query, $value): void
            {
                //
            }
        };

        TestModel::create(['name' => 'abcdef']);

        $results = $this
            ->createQueryFromFilterRequest([
                '*' => '*',
            ])
            ->setAllowedFilters('name', $customFilter)
            ->build()
            ->get();

        $this->assertNotEmpty($results);
    }

    /** @test */
    public function an_invalid_filter_query_exception_contains_the_unknown_and_allowed_filters(): void
    {
        $exception = new InvalidFilterQuery(collect(['unknown filter']), collect(['allowed filter']));

        $this->assertEquals(['unknown filter'], $exception->unknownFilters->all());
        $this->assertEquals(['allowed filter'], $exception->allowedFilters->all());
    }

    /** @test */
    public function it_sets_property_column_name_to_property_name_by_default(): void
    {
        $filter = new FiltersExact('property_name');

        $this->assertEquals($filter->getName(), $filter->getPropertyName());
    }

    /** @test */
    public function it_resolves_queries_using_property_column_name(): void
    {
        $filter = new FiltersExact('name', 'nickname');

        TestModel::create(['name' => 'abcdef']);

        $models = $this
            ->createQueryFromFilterRequest([
                'nickname' => 'abcdef',
            ])
            ->setAllowedFilters($filter)
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_can_filter_using_boolean_flags(): void
    {
        TestModel::query()->update(['is_visible' => true]);
        $filter = new FiltersExact('is_visible');

        $models = $this
            ->createQueryFromFilterRequest(['is_visible' => 'false'])
            ->setAllowedFilters($filter)
            ->build()
            ->get();

        $this->assertCount(0, $models);
        $this->assertGreaterThan(0, TestModel::all()->count());
    }

    /** @test */
    public function it_should_apply_a_default_filter_value_if_nothing_in_request(): void
    {
        TestModel::create(['name' => 'UniqueJohn Doe']);
        TestModel::create(['name' => 'UniqueJohn Deer']);

        $filter = (new FiltersPartial('name'))->default('UniqueJohn');

        $models = $this
            ->createQueryFromFilterRequest([])
            ->setAllowedFilters($filter)
            ->build()
            ->get();

        $this->assertEquals(2, $models->count());
    }

    /** @test */
    public function it_does_not_apply_default_filter_when_filter_exists_and_default_is_set(): void
    {
        TestModel::create(['name' => 'UniqueJohn UniqueDoe']);
        TestModel::create(['name' => 'UniqueJohn Deer']);

        $filter = (new FiltersPartial('name'))->default('UniqueJohn');

        $models = $this
            ->createQueryFromFilterRequest([
                'name' => 'UniqueDoe',
            ])
            ->setAllowedFilters($filter)
            ->build()
            ->get();

        $this->assertEquals(1, $models->count());
    }

    protected function createQueryFromFilterRequest(array $filters): EloquentQueryWizard
    {
        $request = new Request([
            'filter' => $filters,
        ]);

        return EloquentQueryWizard::for(TestModel::class, $request);
    }
}

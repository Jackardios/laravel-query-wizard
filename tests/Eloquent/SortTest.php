<?php

namespace Jackardios\QueryWizard\Tests\Eloquent;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\FiltersScope;
use Jackardios\QueryWizard\Handlers\Eloquent\Sorts\AbstractEloquentSort;
use Jackardios\QueryWizard\Handlers\Eloquent\Sorts\SortsByField;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Enums\SortDirection;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\EloquentQueryWizard;
use Jackardios\QueryWizard\Tests\Concerns\AssertsCollectionSorting;
use Jackardios\QueryWizard\Tests\TestClasses\Models\TestModel;
use Jackardios\QueryWizard\Values\Sort;

/**
 * @group eloquent
 * @group sort
 */
class SortTest extends TestCase
{
    use AssertsCollectionSorting;

    /** @var \Illuminate\Support\Collection */
    protected $models;

    public function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        $this->models = factory(TestModel::class, 5)->create();
    }

    /** @test */
    public function it_can_sort_a_query_ascending(): void
    {
        $sortedModels = $this
            ->createQueryFromSortRequest('name')
            ->setAllowedSorts('name')
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` asc');
        $this->assertSortedAscending($sortedModels, 'name');
    }

    /** @test */
    public function it_can_sort_a_query_descending(): void
    {
        $sortedModels = $this
            ->createQueryFromSortRequest('-name')
            ->setAllowedSorts('name')
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` desc');
        $this->assertSortedDescending($sortedModels, 'name');
    }

    /** @test */
    public function it_can_sort_a_query_by_alias(): void
    {
        $sortedModels = $this
            ->createQueryFromSortRequest('name-alias')
            ->setAllowedSorts([new SortsByField('name', 'name-alias')])
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` asc');
        $this->assertSortedAscending($sortedModels, 'name');
    }

    /** @test */
    public function it_wont_sort_by_columns_that_werent_allowed_first(): void
    {
        $this->createQueryFromSortRequest('name')->build()->get();

        $this->assertQueryLogDoesntContain('order by `name`');
    }

    /** @test */
    public function it_can_allow_a_descending_sort_by_still_sort_ascending(): void
    {
        $sortedModels = $this
            ->createQueryFromSortRequest('name')
            ->setAllowedSorts('-name')
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` asc');
        $this->assertSortedAscending($sortedModels, 'name');
    }

    /** @test */
    public function it_can_sort_a_query_by_a_related_property(): void
    {
        $request = new Request([
            'sort' => 'related_models.name',
            'includes' => 'relatedModel',
        ]);

        $sortedQuery = EloquentQueryWizard::for(TestModel::class, $request)
            ->setAllowedIncludes('relatedModels')
            ->setAllowedSorts('related_models.name')
            ->build()
            ->toSql();

        $this->assertEquals('select * from `test_models` order by `related_models`.`name` asc', $sortedQuery);
    }

    /** @test */
    public function it_can_sort_by_json_property_if_its_an_allowed_sort(): void
    {
        TestModel::query()->update(['name' => json_encode(['first' => 'abc'])]);

        $this
            ->createQueryFromSortRequest('-name->first')
            ->setAllowedSorts(['name->first'])
            ->build()
            ->get();

        $expectedQuery = TestModel::query()->orderByDesc('name->first')->toSql();

        $this->assertQueryExecuted($expectedQuery);
    }

    /** @test */
    public function it_can_sort_by_sketchy_alias_if_its_an_allowed_sort(): void
    {
        $sortedModels = $this
            ->createQueryFromSortRequest('-sketchy<>sort')
            ->setAllowedSorts(new SortsByField('name', 'sketchy<>sort'))
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` desc');
        $this->assertSortedDescending($sortedModels, 'name');
    }

    /** @test */
    public function it_can_sort_a_query_with_custom_select(): void
    {
        $request = new Request([
            'sort' => '-id',
        ]);

        EloquentQueryWizard::for(TestModel::select('id', 'name'), $request)
            ->setAllowedSorts('id')
            ->setDefaultSorts('id')
            ->build()
            ->paginate(15);

        $this->assertQueryExecuted('select `id`, `name` from `test_models` order by `id` desc limit 15 offset 0');
    }

    /** @test */
    public function it_can_sort_a_chunk_query(): void
    {
        $this
            ->createQueryFromSortRequest('-name')
            ->setAllowedSorts('name')
            ->build()
            ->chunk(100, function ($models) {
                //
            });

        $this->assertQueryExecuted('select * from `test_models` order by `name` desc limit 100 offset 0');
    }

    /** @test */
    public function it_can_guard_against_sorts_that_are_not_allowed(): void
    {
        $sortedModels = $this
            ->createQueryFromSortRequest('name')
            ->setAllowedSorts('name')
            ->build()
            ->get();

        $this->assertSortedAscending($sortedModels, 'name');
    }

    /** @test */
    public function it_will_throw_an_exception_if_a_sort_property_is_not_allowed(): void
    {
        $this->expectException(InvalidSortQuery::class);

        $this
            ->createQueryFromSortRequest('name')
            ->setAllowedSorts('id')
            ->build();
    }

    /** @test */
    public function it_wont_sort_if_no_sort_query_parameter_is_given(): void
    {
        $builderQuery = EloquentQueryWizard::for(TestModel::class, new Request())
            ->setAllowedSorts('name')
            ->build()
            ->toSql();

        $eloquentQuery = TestModel::query()->toSql();

        $this->assertEquals($eloquentQuery, $builderQuery);
    }

    /** @test */
    public function it_wont_sort_sketchy_sort_requests(): void
    {
        $this
            ->createQueryFromSortRequest('id->"\') asc --injection')
            ->build()
            ->get();

        $this->assertQueryLogDoesntContain('--injection');
    }

    /** @test */
    public function it_uses_default_sort_parameter_when_no_sort_was_requested(): void
    {
        $sortedModels = EloquentQueryWizard::for(TestModel::class, new Request())
            ->setAllowedSorts('name')
            ->setDefaultSorts('name')
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` asc');
        $this->assertSortedAscending($sortedModels, 'name');
    }

    /** @test */
    public function it_doesnt_use_the_default_sort_parameter_when_a_sort_was_requested(): void
    {
        $this->createQueryFromSortRequest('id')
            ->setAllowedSorts('id')
            ->setDefaultSorts('name')
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `id` asc');
    }

    /** @test */
    public function it_allows_default_custom_sort_class_parameter(): void
    {
        $sortClass = new class('custom_name') extends AbstractEloquentSort {
            public function handle($queryHandler, $query, string $direction): void
            {
                $query->orderBy('name', $direction);
            }
        };

        $sortedModels = EloquentQueryWizard::for(TestModel::class, new Request())
            ->setAllowedSorts($sortClass)
            ->setDefaultSorts(new Sort('custom_name'))
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` asc');
        $this->assertSortedAscending($sortedModels, 'name');
    }

    /** @test */
    public function it_uses_default_descending_sort_parameter(): void
    {
        $sortedModels = EloquentQueryWizard::for(TestModel::class, new Request())
            ->setAllowedSorts('-name')
            ->setDefaultSorts('-name')
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` desc');
        $this->assertSortedDescending($sortedModels, 'name');
    }

    /** @test */
    public function it_allows_multiple_default_sort_parameters(): void
    {
        $sortClass = new class('custom_name') extends AbstractEloquentSort {
            public function handle($queryHandler, $query, string $direction): void
            {
                $query->orderBy('name', $direction);
            }
        };

        $sortedModels = EloquentQueryWizard::for(TestModel::class, new Request())
            ->setAllowedSorts($sortClass, 'id')
            ->setDefaultSorts('custom_name', new Sort('id', SortDirection::DESCENDING))
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` asc, `id` desc');
        $this->assertSortedAscending($sortedModels, 'name');
    }

    /** @test */
    public function it_can_allow_multiple_sort_parameters(): void
    {
        DB::enableQueryLog();
        $sortedModels = $this
            ->createQueryFromSortRequest('name')
            ->setAllowedSorts('id', 'name')
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` asc');
        $this->assertSortedAscending($sortedModels, 'name');
    }

    /** @test */
    public function it_can_allow_multiple_sort_parameters_as_an_array(): void
    {
        $sortedModels = $this
            ->createQueryFromSortRequest('name')
            ->setAllowedSorts(['id', 'name'])
            ->build()
            ->get();

        $this->assertSortedAscending($sortedModels, 'name');
    }

    /** @test */
    public function it_can_sort_by_multiple_columns(): void
    {
        factory(TestModel::class, 3)->create(['name' => 'foo']);

        $sortedModels = $this
            ->createQueryFromSortRequest('name,-id')
            ->setAllowedSorts('name', 'id')
            ->build()
            ->get();

        $expected = TestModel::orderBy('name')->orderByDesc('id');
        $this->assertQueryExecuted('select * from `test_models` order by `name` asc, `id` desc');
        $this->assertEquals($expected->pluck('id'), $sortedModels->pluck('id'));
    }

    /** @test */
    public function it_can_sort_by_a_custom_sort_class(): void
    {
        $sortClass = new class('custom_name') extends AbstractEloquentSort {
            public function handle($queryHandler, $query, string $direction): void
            {
                $query->orderBy('name', $direction);
            }
        };

        $sortedModels = $this
            ->createQueryFromSortRequest('custom_name')
            ->setAllowedSorts($sortClass)
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` asc');
        $this->assertSortedAscending($sortedModels, 'name');
    }

    /** @test */
    public function it_resolves_queries_using_property_column_name(): void
    {
        $sort = new SortsByField('name', 'nickname');

        $testModel = TestModel::create(['name' => 'zzzzzzzz']);

        $models = $this
            ->createQueryFromSortRequest('nickname')
            ->setAllowedSorts($sort)
            ->build()
            ->get();

        $this->assertSorted($models, 'name');
        $this->assertTrue($testModel->is($models->last()));
    }

    /** @test */
    public function it_can_sort_descending_with_an_alias(): void
    {
        $this->createQueryFromSortRequest('-exposed_property_name')
            ->setAllowedSorts(new SortsByField('name', 'exposed_property_name'))
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` desc');
    }

    /** @test */
    public function it_does_not_add_sort_clauses_multiple_times(): void
    {
        $sql = EloquentQueryWizard::for(TestModel::class)
            ->setAllowedSorts('name')
            ->setDefaultSorts('name', '-name')
            ->build()
            ->toSql();

        $this->assertSame('select * from `test_models` order by `name` asc', $sql);
    }

    /** @test */
    public function given_a_default_sort_a_sort_alias_will_still_be_resolved(): void
    {
        $sql = $this->createQueryFromSortRequest('-joined')
            ->setDefaultSorts('name')
            ->setAllowedSorts(new SortsByField('created_at', 'joined'))
            ->build()
            ->toSql();

        $this->assertSame('select * from `test_models` order by `created_at` desc', $sql);
    }

    /** @test */
    public function it_can_sort_and_use_scoped_filters_at_the_same_time(): void
    {
        $sortClass = new class('custom') extends AbstractEloquentSort {
            public function handle($queryHandler, $query, string $direction): void
            {
                $query->orderBy('name', $direction);
            }
        };

        $sortedModels = EloquentQueryWizard::for(TestModel::class, new Request([
            'filter' => [
                'name' => 'foo',
                'between' => '2016-01-01,2017-01-01',
            ],
            'sort' => '-custom',
        ]))
            ->setAllowedFilters([
                new FiltersScope('named', 'name'),
                new FiltersScope('createdBetween', 'between'),
            ])
            ->setAllowedSorts([$sortClass])
            ->setDefaultSorts('foo')
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` where `name` = ? and `created_at` between ? and ? order by `name` desc');
        $this->assertSortedAscending($sortedModels, 'name');
    }

    /** @test */
    public function raw_sorts_do_not_get_purged_when_specifying_allowed_sorts(): void
    {
        $query = $this->createQueryFromSortRequest('-name')
            ->orderByRaw('RANDOM()')
            ->setAllowedSorts('name')
            ->build();

        $this->assertSame('select * from `test_models` order by RANDOM(), `name` desc', $query->toSql());
    }

    /** @test */
    public function the_default_direction_of_an_allow_sort_can_be_set(): void
    {
        $sortClass = new class('custom_name') extends AbstractEloquentSort {
            public function handle($queryHandler, $query, string $direction): void
            {
                $query->orderBy('name', $direction);
            }
        };

        $sortedModels = EloquentQueryWizard::for(TestModel::class, new Request())
            ->setAllowedSorts($sortClass)
            ->setDefaultSorts('-custom_name')
            ->build()
            ->get();

        $this->assertQueryExecuted('select * from `test_models` order by `name` desc');
        $this->assertSortedDescending($sortedModels, 'name');
    }

    protected function createQueryFromSortRequest(string $sort): EloquentQueryWizard
    {
        $request = new Request([
            'sort' => $sort,
        ]);

        return EloquentQueryWizard::for(TestModel::class, $request);
    }

    protected function assertQueryExecuted(string $query): void
    {
        $queries = array_map(function ($queryLogItem) {
            return $queryLogItem['query'];
        }, DB::getQueryLog());

        $this->assertContains($query, $queries);
    }
}

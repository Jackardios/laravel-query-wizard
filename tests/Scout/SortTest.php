<?php

namespace Jackardios\QueryWizard\Tests\Scout;

use Jackardios\QueryWizard\Tests\TestCase;
use Illuminate\Http\Request;
use Jackardios\QueryWizard\Handlers\Scout\Sorts\AbstractScoutSort;
use Jackardios\QueryWizard\Handlers\Scout\Sorts\SortsByField;
use Jackardios\QueryWizard\Enums\SortDirection;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\ScoutQueryWizard;
use Jackardios\QueryWizard\Tests\Concerns\AssertsCollectionSorting;
use Jackardios\QueryWizard\Tests\TestClasses\Models\TestModel;
use Jackardios\QueryWizard\Values\Sort;

/**
 * @group scout
 * @group sort
 * @group scout-sort
 */
class SortTest extends TestCase
{
    use AssertsCollectionSorting;

    /** @var \Illuminate\Support\Collection */
    protected $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 5)->create();
    }

    /** @test */
    public function it_can_sort_a_query_ascending(): void
    {
        $query = $this
            ->createQueryFromSortRequest('name')
            ->setAllowedSorts('name')
            ->build();

        $this->assertEquals([
            [
                "column" => "name",
                "direction" => "asc"
            ]
        ], $query->orders);
    }

    /** @test */
    public function it_can_sort_a_query_descending(): void
    {
        $query = $this
            ->createQueryFromSortRequest('-name')
            ->setAllowedSorts('name')
            ->build();

        $this->assertEquals([
            [
                "column" => "name",
                "direction" => "desc"
            ]
        ], $query->orders);
    }

    /** @test */
    public function it_can_sort_a_query_by_alias(): void
    {
        $query = $this
            ->createQueryFromSortRequest('name-alias')
            ->setAllowedSorts([new SortsByField('name', 'name-alias')])
            ->build();

        $this->assertEquals([
            [
                "column" => "name",
                "direction" => "asc"
            ]
        ], $query->orders);
    }

    /** @test */
    public function it_can_allow_a_descending_sort_by_still_sort_ascending(): void
    {
        $query = $this
            ->createQueryFromSortRequest('name')
            ->setAllowedSorts('-name')
            ->build();

        $this->assertEquals([
            [
                "column" => "name",
                "direction" => "asc"
            ]
        ], $query->orders);
    }

    /** @test */
    public function it_can_sort_by_sketchy_alias_if_its_an_allowed_sort(): void
    {
        $query = $this
            ->createQueryFromSortRequest('-sketchy<>sort')
            ->setAllowedSorts(new SortsByField('name', 'sketchy<>sort'))
            ->build();

        $this->assertEquals([
            [
                "column" => "name",
                "direction" => "desc"
            ]
        ], $query->orders);
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
        $query = ScoutQueryWizard::for(TestModel::search(), new Request())
            ->setAllowedSorts('name')
            ->build();

        $this->assertEquals([], $query->orders);
    }

    /** @test */
    public function it_uses_default_sort_parameter_when_no_sort_was_requested(): void
    {
        $query = ScoutQueryWizard::for(TestModel::search(), new Request())
            ->setAllowedSorts('name')
            ->setDefaultSorts('name')
            ->build();

        $this->assertEquals([[
            "column" => "name",
            "direction" => "asc"
        ]], $query->orders);
    }

    /** @test */
    public function it_doesnt_use_the_default_sort_parameter_when_a_sort_was_requested(): void
    {
        $query = $this->createQueryFromSortRequest('id')
            ->setAllowedSorts('id')
            ->setDefaultSorts('name')
            ->build();

        $this->assertEquals([[
            "column" => "id",
            "direction" => "asc"
        ]], $query->orders);
    }

    /** @test */
    public function it_allows_default_custom_sort_class_parameter(): void
    {
        $sortClass = new class('custom_name') extends AbstractScoutSort {
            public function handle($queryHandler, $query, string $direction): void
            {
                $query->orderBy('name', $direction);
            }
        };

        $query = ScoutQueryWizard::for(TestModel::search(), new Request())
            ->setAllowedSorts($sortClass)
            ->setDefaultSorts(new Sort('custom_name'))
            ->build();

        $this->assertEquals([[
            "column" => "name",
            "direction" => "asc"
        ]], $query->orders);
    }

    /** @test */
    public function it_uses_default_descending_sort_parameter(): void
    {
        $query = ScoutQueryWizard::for(TestModel::search(), new Request())
            ->setAllowedSorts('-name')
            ->setDefaultSorts('-name')
            ->build();

        $this->assertEquals([[
            "column" => "name",
            "direction" => "desc"
        ]], $query->orders);
    }

    /** @test */
    public function it_allows_multiple_default_sort_parameters(): void
    {
        $sortClass = new class('custom_name') extends AbstractScoutSort {
            public function handle($queryHandler, $query, string $direction): void
            {
                $query->orderBy('name', $direction);
            }
        };

        $query = ScoutQueryWizard::for(TestModel::search(), new Request())
            ->setAllowedSorts($sortClass, 'id')
            ->setDefaultSorts('custom_name', new Sort('id', SortDirection::DESCENDING))
            ->build();

        $this->assertEquals([
            [
                "column" => "name",
                "direction" => "asc"
            ],
            [
                "column" => "id",
                "direction" => "desc"
            ],
        ], $query->orders);
    }

    /** @test */
    public function it_can_allow_multiple_sort_parameters(): void
    {
        $query = $this
            ->createQueryFromSortRequest('name')
            ->setAllowedSorts('id', 'name')
            ->build();

        $this->assertEquals([[
            "column" => "name",
            "direction" => "asc"
        ]], $query->orders);
    }

    /** @test */
    public function it_can_allow_multiple_sort_parameters_as_an_array(): void
    {
        $query = $this
            ->createQueryFromSortRequest('name')
            ->setAllowedSorts(['id', 'name'])
            ->build();

        $this->assertEquals([[
            "column" => "name",
            "direction" => "asc"
        ]], $query->orders);
    }

    /** @test */
    public function it_can_sort_by_multiple_columns(): void
    {
        $query = $this
            ->createQueryFromSortRequest('name,-id')
            ->setAllowedSorts('name', 'id')
            ->build();

        $this->assertEquals([
            [
                "column" => "name",
                "direction" => "asc"
            ],
            [
                "column" => "id",
                "direction" => "desc"
            ],
        ], $query->orders);
    }

    /** @test */
    public function it_can_sort_by_a_custom_sort_class(): void
    {
        $sortClass = new class('custom_name') extends AbstractScoutSort {
            public function handle($queryHandler, $query, string $direction): void
            {
                $query->orderBy('name', $direction);
            }
        };

        $query = $this
            ->createQueryFromSortRequest('custom_name')
            ->setAllowedSorts($sortClass)
            ->build();

        $this->assertEquals([
            [
                "column" => "name",
                "direction" => "asc"
            ],
        ], $query->orders);
    }

    /** @test */
    public function it_resolves_queries_using_property_column_name(): void
    {
        $sort = new SortsByField('name', 'nickname');

        $query = $this
            ->createQueryFromSortRequest('nickname')
            ->setAllowedSorts($sort)
            ->build();

        $this->assertEquals([
            [
                "column" => "name",
                "direction" => "asc"
            ],
        ], $query->orders);
    }

    /** @test */
    public function it_can_sort_descending_with_an_alias(): void
    {
        $query = $this->createQueryFromSortRequest('-exposed_property_name')
            ->setAllowedSorts(new SortsByField('name', 'exposed_property_name'))
            ->build();

        $this->assertEquals([
            [
                "column" => "name",
                "direction" => "desc"
            ],
        ], $query->orders);
    }

    /** @test */
    public function it_does_not_add_sort_clauses_multiple_times(): void
    {
        $query = ScoutQueryWizard::for(TestModel::search())
            ->setAllowedSorts('name')
            ->setDefaultSorts('name', '-name')
            ->build();

        $this->assertEquals([
            [
                "column" => "name",
                "direction" => "asc"
            ],
        ], $query->orders);
    }

    /** @test */
    public function given_a_default_sort_a_sort_alias_will_still_be_resolved(): void
    {
        $query = $this->createQueryFromSortRequest('-joined')
            ->setDefaultSorts('name')
            ->setAllowedSorts(new SortsByField('created_at', 'joined'))
            ->build();

        $this->assertEquals([
            [
                "column" => "created_at",
                "direction" => "desc"
            ],
        ], $query->orders);
    }

    /** @test */
    public function the_default_direction_of_an_allow_sort_can_be_set(): void
    {
        $sortClass = new class('custom_name') extends AbstractScoutSort {
            public function handle($queryHandler, $query, string $direction): void
            {
                $query->orderBy('name', $direction);
            }
        };

        $query = ScoutQueryWizard::for(TestModel::search(), new Request())
            ->setAllowedSorts($sortClass)
            ->setDefaultSorts('-custom_name')
            ->build();

        $this->assertEquals([
            [
                "column" => "name",
                "direction" => "desc"
            ],
        ], $query->orders);
    }

    protected function createQueryFromSortRequest(string $sort): ScoutQueryWizard
    {
        $request = new Request([
            'sort' => $sort,
        ]);

        return ScoutQueryWizard::for(TestModel::search(), $request);
    }
}

<?php

namespace Jackardios\QueryWizard\Tests\Scout;

use Jackardios\QueryWizard\Tests\TestCase;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\ScoutQueryWizard;
use Jackardios\QueryWizard\Handlers\Scout\Filters\FiltersExact;
use Jackardios\QueryWizard\Tests\TestClasses\Models\TestModel;

/**
 * @group scout
 * @group filter
 * @group scout-filter
 */
class RelationFilterTest extends TestCase
{
    /** @var \Illuminate\Support\Collection */
    protected $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 5)->create();

        $this->models->each(function (TestModel $model, $index) {
            $model
                ->relatedModels()->create(['name' => $model->name])
                ->nestedRelatedModels()->create(['name' => 'test'.$index]);
        });
    }

    /** @test */
    public function it_can_filter_related_model_property()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'relatedModels.name' => $this->models->first()->name,
            ])
            ->setAllowedFilters('relatedModels.name')
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_can_filter_results_based_on_the_exact_existence_of_a_property_in_an_array()
    {
        $results = $this
            ->createQueryFromFilterRequest([
                'relatedModels.nestedRelatedModels.name' => 'test0,test1',
            ])
            ->setAllowedFilters(new FiltersExact('relatedModels.nestedRelatedModels.name'))
            ->build()
            ->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing([$this->models->get(0)->id, $this->models->get(1)->id], $results->pluck('id')->all());
    }

    /** @test */
    public function it_can_filter_models_and_return_an_empty_collection()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'relatedModels.name' => 'None existing first name',
            ])
            ->setAllowedFilters('relatedModels.name')
            ->build()
            ->get();

        $this->assertCount(0, $models);
    }

    /** @test */
    public function it_can_filter_related_nested_model_property()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'relatedModels.nestedRelatedModels.name' => 'test1',
            ])
            ->setAllowedFilters(new FiltersExact('relatedModels.nestedRelatedModels.name'))
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_can_filter_related_model_and_related_nested_model_property()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'relatedModels.name' => $this->models->first()->name,
                'relatedModels.nestedRelatedModels.name' => 'test0',
            ])
            ->setAllowedFilters(
                new FiltersExact('relatedModels.name'),
                new FiltersExact('relatedModels.nestedRelatedModels.name')
            )
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_can_filter_results_based_on_the_existence_of_a_property_in_an_array()
    {
        $testModels = TestModel::whereIn('id', [1, 2])->get();

        $results = $this
            ->createQueryFromFilterRequest([
                'relatedModels.id' => $testModels->map(function ($model) {
                    return $model->relatedModels->pluck('id');
                })->flatten()->all(),
            ])
            ->setAllowedFilters(new FiltersExact('relatedModels.id'))
            ->build()
            ->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing([1, 2], $results->pluck('id')->all());
    }

    /** @test */
    public function it_can_filter_and_reject_results_by_exact_property()
    {
        $testModel = TestModel::create(['name' => 'John Testing Doe']);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'relatedModels.nestedRelatedModels.name' => ' test ',
            ])
            ->setAllowedFilters(new FiltersExact('relatedModels.nestedRelatedModels.name'))
            ->build()
            ->get();

        $this->assertCount(0, $modelsResult);
    }

    protected function createQueryFromFilterRequest(array $filters): ScoutQueryWizard
    {
        $request = new Request([
            'filter' => $filters,
        ]);

        return ScoutQueryWizard::for(TestModel::search(), $request);
    }
}

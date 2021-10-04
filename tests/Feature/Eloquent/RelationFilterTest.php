<?php

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\EloquentQueryWizard;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\ExactFilter;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\PartialFilter;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;

/**
 * @group eloquent
 * @group filter
 * @group eloquent-filter
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
            ->createWizardFromFilterRequest([
                'relatedModels.name' => $this->models->first()->name,
            ])
            ->setAllowedFilters('relatedModels.name')
            ->build()
            ->get();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_can_filter_results_based_on_the_partial_existence_of_a_property_in_an_array()
    {
        $results = $this
            ->createWizardFromFilterRequest([
                'relatedModels.nestedRelatedModels.name' => 'est0,est1',
            ])
            ->setAllowedFilters(new PartialFilter('relatedModels.nestedRelatedModels.name'))
            ->build()
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals([$this->models->get(0)->id, $this->models->get(1)->id], $results->pluck('id')->all());
    }

    /** @test */
    public function it_can_filter_models_and_return_an_empty_collection()
    {
        $models = $this
            ->createWizardFromFilterRequest([
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
            ->createWizardFromFilterRequest([
                'relatedModels.nestedRelatedModels.name' => 'test',
            ])
            ->setAllowedFilters(new PartialFilter('relatedModels.nestedRelatedModels.name'))
            ->build()
            ->get();

        $this->assertCount(5, $models);
    }

    /** @test */
    public function it_can_filter_related_model_and_related_nested_model_property()
    {
        $models = $this
            ->createWizardFromFilterRequest([
                'relatedModels.name' => $this->models->first()->name,
                'relatedModels.nestedRelatedModels.name' => 'test',
            ])
            ->setAllowedFilters(
                new PartialFilter('relatedModels.name'),
                new PartialFilter('relatedModels.nestedRelatedModels.name')
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
            ->createWizardFromFilterRequest([
                'relatedModels.id' => $testModels->map(function ($model) {
                    return $model->relatedModels->pluck('id');
                })->flatten()->all(),
            ])
            ->setAllowedFilters(new ExactFilter('relatedModels.id'))
            ->build()
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals([1, 2], $results->pluck('id')->all());
    }

    /** @test */
    public function it_can_filter_and_reject_results_by_exact_property()
    {
        $testModel = TestModel::create(['name' => 'John Testing Doe']);

        $modelsResult = $this
            ->createWizardFromFilterRequest([
                'relatedModels.nestedRelatedModels.name' => ' test ',
            ])
            ->setAllowedFilters(new ExactFilter('relatedModels.nestedRelatedModels.name'))
            ->build()
            ->get();

        $this->assertCount(0, $modelsResult);
    }

    /** @test */
    public function it_can_disable_exact_filtering_based_on_related_model_properties()
    {
        $addRelationConstraint = false;

        $sql = $this
            ->createWizardFromFilterRequest([
                'relatedModels.name' => $this->models->first()->name,
            ])
            ->setAllowedFilters(new ExactFilter('relatedModels.name', null, null, $addRelationConstraint))
            ->build()
            ->toSql();

        $this->assertStringContainsString('`relatedModels`.`name` = ', $sql);
    }

    /** @test */
    public function it_can_disable_partial_filtering_based_on_related_model_properties()
    {
        $addRelationConstraint = false;

        $sql = $this
            ->createWizardFromFilterRequest([
                'relatedModels.name' => $this->models->first()->name,
            ])
            ->setAllowedFilters(new PartialFilter('relatedModels.name', null, null, $addRelationConstraint))
            ->build()
            ->toSql();

        $this->assertStringContainsString('LOWER(`relatedModels`.`name`) LIKE ?', $sql);
    }

    protected function createWizardFromFilterRequest(array $filters): EloquentQueryWizard
    {
        $request = new Request([
            'filter' => $filters,
        ]);

        return EloquentQueryWizard::for(TestModel::class, $request);
    }
}

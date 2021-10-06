<?php

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Jackardios\QueryWizard\EloquentQueryWizard;
use Jackardios\QueryWizard\Handlers\Eloquent\EloquentQueryHandler;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\CallbackInclude;
use Jackardios\QueryWizard\Tests\Concerns\AssertsRelations;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;

/**
 * @group eloquent
 * @group include
 * @group eloquent-include
 */
class CallbackIncludeTest extends TestCase
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
    public function it_should_include_by_closure(): void
    {
        $models = $this
            ->createWizardFromIncludeRequest('callbackRelation')
            ->setAllowedIncludes(
                new CallbackInclude('callbackRelation', function (EloquentQueryHandler $queryHandler, Builder $query) {
                    $query->with('relatedModels');
                })
            )
            ->build()
            ->get();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function it_should_include_by_array_callback(): void
    {
        $models = $this
            ->createWizardFromIncludeRequest('callbackRelation')
            ->setAllowedIncludes(new CallbackInclude('callbackRelation', [$this, 'includeCallback']))
            ->build()
            ->get();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    public function includeCallback(EloquentQueryHandler $queryHandler, Builder $query): void
    {
        $query->with('relatedModels');
    }

    protected function createWizardFromIncludeRequest(string $includes): EloquentQueryWizard
    {
        $request = new Request([
            'include' => $includes,
        ]);

        return EloquentQueryWizard::for(TestModel::class, $request);
    }
}

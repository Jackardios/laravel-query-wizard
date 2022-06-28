<?php

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Eloquent\Includes\CallbackInclude;
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

    protected Collection $models;

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
            ->createEloquentWizardWithIncludes('callbackRelation')
            ->setAllowedIncludes(
                new CallbackInclude('relatedModels', function (EloquentQueryWizard $queryWizard, Builder $queryBuilder, $value) {
                    $this->assertEquals('relatedModels', $value);
                    $queryBuilder->with('relatedModels');
                }, 'callbackRelation')
            )
            ->build()
            ->get();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function it_should_include_by_array_callback(): void
    {
        $models = $this
            ->createEloquentWizardWithIncludes('callbackRelation')
            ->setAllowedIncludes(new CallbackInclude('callbackRelation', [$this, 'includeCallback']))
            ->build()
            ->get();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    public function includeCallback(EloquentQueryWizard $queryWizard, Builder $queryBuilder, $value): void
    {
        $this->assertEquals('callbackRelation', $value);
        $queryBuilder->with('relatedModels');
    }
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('query-count')]
class QueryCountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $models = TestModel::factory()->count(3)->create();
        $models->each(function (TestModel $model) {
            RelatedModel::factory()->count(2)->create([
                'test_model_id' => $model->id,
            ])->each(function (RelatedModel $related) {
                NestedRelatedModel::factory()->create([
                    'related_model_id' => $related->id,
                ]);
            });
        });
    }

    #[Test]
    public function without_includes_accessing_relation_causes_n_plus_one(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardFromQuery()
            ->get();

        // 1 query for models
        $this->assertCount(1, DB::getQueryLog());

        // Accessing relation triggers N+1
        DB::flushQueryLog();
        foreach ($models as $model) {
            $model->relatedModels->count();
        }

        // 3 additional queries (one per model)
        $this->assertCount(3, DB::getQueryLog());
    }

    #[Test]
    public function with_includes_uses_eager_loading(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels')
            ->allowedIncludes('relatedModels')
            ->get();

        // 2 queries: 1 for models + 1 for eager-loaded relation
        $this->assertCount(2, DB::getQueryLog());

        // Accessing relation does NOT trigger additional queries
        DB::flushQueryLog();
        foreach ($models as $model) {
            $model->relatedModels->count();
        }

        $this->assertCount(0, DB::getQueryLog());
    }

    #[Test]
    public function count_include_uses_single_query_with_subquery(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModelsCount')
            ->allowedIncludes(EloquentInclude::count('relatedModels'))
            ->get();

        // Count is done as subquery in SELECT, so only 1 query
        $this->assertCount(1, DB::getQueryLog());
        $this->assertEquals(2, $models->first()->related_models_count);
    }

    #[Test]
    public function nested_includes_use_three_queries(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels.nestedRelatedModels')
            ->allowedIncludes('relatedModels.nestedRelatedModels')
            ->get();

        // 3 queries: models + relatedModels + nestedRelatedModels
        $this->assertCount(3, DB::getQueryLog());
    }

    #[Test]
    public function multiple_includes_use_one_plus_n_relations_queries(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,otherRelatedModels')
            ->allowedIncludes('relatedModels', 'otherRelatedModels')
            ->get();

        // 3 queries: models + relatedModels + otherRelatedModels
        $this->assertCount(3, DB::getQueryLog());
    }
}

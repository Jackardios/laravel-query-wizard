<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('combined')]
class CombinedScenarioTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        $this->models = TestModel::factory()->count(5)->create();
        $this->models->each(function (TestModel $model, int $index) {
            RelatedModel::factory()->count($index + 1)->create([
                'test_model_id' => $model->id,
            ]);
        });

        AppendModel::factory()->count(3)->create();
    }

    #[Test]
    public function all_five_features_combined(): void
    {
        $target = $this->models->first();

        $models = $this
            ->createEloquentWizardFromQuery([
                'filter' => ['name' => $target->name],
                'sort' => '-id',
                'include' => 'relatedModels',
                'fields' => ['testModel' => 'id,name'],
                'append' => 'fullname',
            ])
            ->allowedFilters('name')
            ->allowedSorts('id')
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name')
            ->allowedAppends('fullname')
            ->get();

        $this->assertCount(1, $models);
        $first = $models->first();
        $this->assertEquals($target->name, $first->name);
        $this->assertTrue($first->relationLoaded('relatedModels'));
        $this->assertTrue(array_key_exists('fullname', $first->toArray()));

        $attributes = array_keys($first->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
    }

    #[Test]
    public function filter_sort_include_triple(): void
    {
        $target = $this->models->last();

        $models = $this
            ->createEloquentWizardFromQuery([
                'filter' => ['name' => $target->name],
                'sort' => 'name',
                'include' => 'relatedModels',
            ])
            ->allowedFilters('name')
            ->allowedSorts('name')
            ->allowedIncludes('relatedModels')
            ->get();

        $this->assertCount(1, $models);
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    #[Test]
    public function count_sort_with_include_of_same_relation(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'sort' => '-relatedModels',
                'include' => 'relatedModels',
            ])
            ->allowedSorts(EloquentSort::count('relatedModels'))
            ->allowedIncludes('relatedModels')
            ->get();

        $this->assertCount(5, $models);
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        // First model should have the most related models (index 4 has 5)
        $counts = $models->pluck('related_models_count')->toArray();
        $sorted = $counts;
        rsort($sorted);
        $this->assertEquals($sorted, $counts);
    }

    #[Test]
    public function multiple_filters_and_sorts_with_pagination(): void
    {
        // Create specific models to search for
        $specific = TestModel::factory()->create(['name' => 'SpecificCombined']);
        RelatedModel::factory()->count(2)->create(['test_model_id' => $specific->id]);

        $result = $this
            ->createEloquentWizardFromQuery([
                'filter' => ['name' => 'SpecificCombined'],
                'sort' => '-id',
            ])
            ->allowedFilters('name', 'id')
            ->allowedSorts('id', 'name')
            ->paginate(10);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('SpecificCombined', $result->first()->name);
    }

    #[Test]
    public function schema_based_combined(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return TestModel::class;
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return [EloquentFilter::exact('name')];
            }

            public function sorts(QueryWizardInterface $wizard): array
            {
                return ['name', 'id'];
            }

            public function includes(QueryWizardInterface $wizard): array
            {
                return ['relatedModels'];
            }

            public function fields(QueryWizardInterface $wizard): array
            {
                return ['id', 'name'];
            }

            public function appends(QueryWizardInterface $wizard): array
            {
                return ['fullname'];
            }

            public function defaultSorts(QueryWizardInterface $wizard): array
            {
                return ['-id'];
            }
        };

        $target = $this->models->first();

        $models = $this
            ->createEloquentWizardFromQuery([
                'filter' => ['name' => $target->name],
                'include' => 'relatedModels',
                'append' => 'fullname',
            ])
            ->schema($schema)
            ->get();

        $this->assertCount(1, $models);
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }
}

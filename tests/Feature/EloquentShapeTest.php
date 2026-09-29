<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature;

use ArrayObject;
use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\BaseQueryWizard;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentShape;
use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class EloquentShapeTest extends TestCase
{
    /** @var list<int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = TestModel::factory()->count(2)->create()->each(function (TestModel $model): void {
            RelatedModel::factory()->create(['test_model_id' => $model->id, 'name' => 'public']);
            RelatedModel::factory()->create(['test_model_id' => $model->id, 'name' => 'SECRET']);
        })->pluck('id')->all();
    }

    #[Test]
    public function it_shapes_a_query_the_wizard_did_not_build(): void
    {
        $shape = $this->shape([
            'include' => 'relatedModels,relatedModelsCount',
            'fields' => ['testModel' => 'name', 'relatedModels' => 'name'],
        ], ['id']);

        DB::enableQueryLog();
        $query = TestModel::query()
            ->whereKey($this->ids)
            ->with(['relatedModels' => fn ($query) => $query->where('name', '!=', 'SECRET')]);
        $models = $shape->postProcess($shape->applyTo($query)->get());
        $log = DB::getQueryLog();

        foreach ($models->toArray() as $model) {
            $this->assertSame(['name', 'related_models_count', 'related_models'], array_keys($model));
            $this->assertSame(2, $model['related_models_count']);
            $this->assertSame([['name' => 'public']], $model['related_models']);
        }

        $this->assertMatchesRegularExpression('/^select "test_models"."name", "test_models"."id", \(select count\(\*\)/', $log[0]['query']);
        $this->assertStringStartsWith('select "name", "test_model_id" from "related_models"', $log[1]['query']);
    }

    #[Test]
    public function required_root_columns_are_selected_under_a_root_fieldset_and_hidden(): void
    {
        $shape = $this->shape(['fields' => ['testModel' => 'name']], ['id']);

        DB::enableQueryLog();
        $models = $shape->postProcess($shape->applyTo(TestModel::query()->whereKey($this->ids))->get());

        $this->assertStringStartsWith('select "test_models"."name", "test_models"."id" from "test_models"', DB::getQueryLog()[0]['query']);
        $this->assertEqualsCanonicalizing($this->ids, $models->pluck('id')->all());
        $this->assertSame([['name'], ['name']], $models->map(fn (Model $model) => array_keys($model->toArray()))->all());
    }

    #[Test]
    public function a_requested_required_column_stays_visible(): void
    {
        $shape = $this->shape(['fields' => ['testModel' => 'id,name']], ['id']);

        $model = $shape->postProcess($shape->applyTo(TestModel::query()->whereKey($this->ids[0]))->first());

        $this->assertSame(['id', 'name'], array_keys($model->toArray()));
    }

    #[Test]
    public function without_a_root_fieldset_the_root_select_is_left_alone(): void
    {
        $shape = $this->shape(['include' => 'relatedModels'], ['id']);

        DB::enableQueryLog();
        $shape->applyTo(TestModel::query()->select('name', 'id'))->get();

        $this->assertStringStartsWith('select "name", "id" from "test_models"', DB::getQueryLog()[0]['query']);
    }

    #[Test]
    public function one_shape_applies_to_every_query_that_loads_the_models(): void
    {
        $shape = $this->shape([
            'include' => 'relatedModels',
            'fields' => ['testModel' => 'name', 'relatedModels' => 'name'],
        ], ['id']);
        $query = TestModel::query()->whereKey($this->ids);

        $first = $shape->postProcess($shape->applyTo(clone $query)->get());
        $second = $shape->postProcess($shape->applyTo(clone $query)->get());

        $this->assertSame($first->toArray(), $second->toArray());
        $this->assertSame(['name', 'related_models'], array_keys($second->first()->toArray()));
    }

    #[Test]
    public function a_shape_keeps_the_build_it_came_from_when_the_wizard_is_reconfigured(): void
    {
        $wizard = $this->wizard(['fields' => ['testModel' => 'name']], ['id']);
        $shape = $wizard->shape();

        $wizard->allowedFields('id', 'name');

        $this->assertNotSame($shape, $wizard->shape());
        $this->assertSame(['name'], array_keys($shape->postProcess(TestModel::query()->find($this->ids[0]))->toArray()));
    }

    #[Test]
    public function post_processing_takes_a_model_a_collection_an_array_and_a_paginator(): void
    {
        $shape = $this->shape([
            'include' => 'relatedModels',
            'fields' => ['testModel' => 'name', 'relatedModels' => 'name'],
            'append' => 'fullname',
        ]);
        $expected = ['name', 'fullname', 'related_models'];
        $load = fn () => TestModel::query()->with('relatedModels')->whereKey($this->ids);

        $this->assertSame($expected, array_keys($shape->postProcess($load()->first())->toArray()));
        $this->assertSame($expected, array_keys($shape->postProcess($load()->get())->first()->toArray()));
        $this->assertSame($expected, array_keys($shape->postProcess($load()->get()->all())[0]->toArray()));
        $this->assertSame($expected, array_keys($shape->postProcess($load()->paginate())->items()[0]->toArray()));
        $this->assertSame(['name'], array_keys($shape->postProcess($load()->first())->relatedModels->first()->toArray()));
    }

    #[Test]
    public function a_lazy_collection_is_post_processed_as_it_is_read_without_running_its_query_first(): void
    {
        $shape = $this->shape(['fields' => ['testModel' => 'name'], 'append' => 'fullname']);

        DB::enableQueryLog();
        $lazy = $shape->postProcess(TestModel::query()->whereKey($this->ids)->cursor());

        $this->assertSame([], DB::getQueryLog());
        $this->assertSame([['name', 'fullname'], ['name', 'fullname']], $lazy->map(fn (TestModel $model) => array_keys($model->toArray()))->all());
        $this->assertCount(1, DB::getQueryLog());
    }

    #[Test]
    public function a_generator_is_refused(): void
    {
        $shape = $this->shape(['append' => 'fullname']);
        $models = (function () {
            yield from TestModel::query()->whereKey($this->ids)->get();
        })();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A generator can be read only once');

        $shape->postProcess($models);
    }

    #[Test]
    public function root_appends_keep_the_whole_root_select(): void
    {
        $shape = $this->shape(['fields' => ['testModel' => 'name'], 'append' => 'fullname'], ['id']);

        DB::enableQueryLog();
        $model = $shape->postProcess($shape->applyTo(TestModel::query()->whereKey($this->ids[0]))->first());

        $this->assertStringStartsWith('select * from "test_models"', DB::getQueryLog()[0]['query']);
        $this->assertSame(['name', 'fullname'], array_keys($model->toArray()));
    }

    #[Test]
    public function an_invalid_relation_fieldset_fails_the_build(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this->shape(['include' => 'relatedModels', 'fields' => ['relatedModels' => 'secret']]);
    }

    #[Test]
    public function an_invalid_append_fails_the_build(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $this->shape(['append' => 'unknown']);
    }

    #[Test]
    public function applying_and_post_processing_read_no_configuration(): void
    {
        $shape = $this->shape([
            'include' => 'relatedModels,relatedModelsCount',
            'fields' => ['testModel' => 'name', 'relatedModels' => 'name'],
        ], ['id']);

        $config = new class($this->app['config']->all()) extends Repository
        {
            public int $packageReads = 0;

            public function get($key, $default = null)
            {
                if (is_string($key) && str_starts_with($key, 'query-wizard')) {
                    $this->packageReads++;
                }

                return parent::get($key, $default);
            }
        };
        $this->app->instance('config', $config);

        $shape->postProcess($shape->applyTo(TestModel::query()->whereKey($this->ids))->get());

        $this->assertSame(0, $config->packageReads);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>  $requiredRootColumns
     */
    private function shape(array $query, array $requiredRootColumns = []): EloquentShape
    {
        return $this->wizard($query, $requiredRootColumns)->shape();
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>  $requiredRootColumns
     */
    private function wizard(array $query, array $requiredRootColumns = []): ModelLoadingWizard
    {
        return (new ModelLoadingWizard(new QueryParametersManager(new Request($query)), $requiredRootColumns))
            ->allowedIncludes('relatedModels', 'relatedModelsCount')
            ->allowedFields('id', 'name', 'relatedModels.name')
            ->allowedAppends('fullname');
    }
}

/**
 * Loads the models with a query of its own, like a search engine wizard does.
 *
 * @extends BaseQueryWizard<ArrayObject<int, string>>
 */
final class ModelLoadingWizard extends BaseQueryWizard
{
    /** @var array<string, IncludeInterface> */
    private array $includes = [];

    /** @var array<string>|null */
    private ?array $rootFields = null;

    private ?EloquentShape $shape = null;

    /**
     * @param  list<string>  $requiredRootColumns
     */
    public function __construct(QueryParametersManager $parameters, private readonly array $requiredRootColumns)
    {
        parent::__construct(new ArrayObject, $parameters);
    }

    public function shape(): EloquentShape
    {
        $this->build();

        return $this->shape ?? throw new \LogicException('The build did not resolve a shape.');
    }

    public function getResourceKey(): string
    {
        return 'testModel';
    }

    protected function resourceModel(): Model
    {
        return new TestModel;
    }

    protected function prepareBuild(): void
    {
        $this->includes = [];
        $this->rootFields = null;
        $this->shape = null;
    }

    protected function applyValidatedIncludes(array $validRequestedIncludes, array $includesIndex): void
    {
        foreach ($validRequestedIncludes as $name) {
            $this->includes[$name] = $includesIndex[$name];
        }
    }

    protected function applyFields(array $fields): void
    {
        $this->rootFields = $fields;
    }

    protected function finalizeBuild(): void
    {
        $this->shape = $this->resolveEloquentShape($this->includes, $this->rootFields, $this->requiredRootColumns);
    }

    protected function normalizeStringToFilter(string $name): FilterInterface
    {
        return EloquentFilter::exact($name);
    }

    protected function normalizeStringToSort(string $name): SortInterface
    {
        return EloquentSort::field($name);
    }

    protected function normalizeStringToInclude(string $name): IncludeInterface
    {
        return RelationshipInclude::fromString($name, $this->getConfig()->getCountSuffix(), $this->getConfig()->getExistsSuffix());
    }
}

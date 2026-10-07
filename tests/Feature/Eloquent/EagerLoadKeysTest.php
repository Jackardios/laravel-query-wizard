<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\ModelQueryWizard;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModelWithEagerLoads;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

/**
 * Sparse fieldsets must keep the columns every eager load needs to match its models,
 * not only those of the requested includes.
 */
#[Group('eloquent')]
#[Group('fields')]
class EagerLoadKeysTest extends TestCase
{
    /** @var array<string, array<string, mixed>> */
    private array $relationResolvers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->relationResolvers = $this->relationResolvers();

        TestModel::factory()->count(2)->create()->each(function (TestModel $model): void {
            RelatedModel::factory()->count(2)->create(['test_model_id' => $model->id])
                ->each(fn (RelatedModel $related) => NestedRelatedModel::factory()->create(['related_model_id' => $related->id]));
        });

        DB::enableQueryLog();
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Model::class, 'relationResolvers'))->setValue(null, $this->relationResolvers);

        parent::tearDown();
    }

    #[Test]
    public function root_fieldset_keeps_the_keys_of_the_model_default_eager_loads(): void
    {
        $models = $this->wizard(TestModelWithEagerLoads::query(), ['fields' => ['testModelWithEagerLoads' => 'name']])
            ->allowedFields('name')
            ->get();

        $this->assertSame([2, 2], $models->map(fn ($model) => $model->relatedModels->count())->all());
        $this->assertArrayNotHasKey('id', $models->first()->toArray());
        $this->assertStringNotContainsString('*', $this->rootQuery());
    }

    #[Test]
    public function root_fieldset_keeps_the_keys_of_developer_eager_loads(): void
    {
        $models = $this->wizard(TestModel::query()->with('relatedModels'), ['fields' => ['testModel' => 'name']])
            ->allowedFields('name')
            ->get();

        $this->assertSame([2, 2], $models->map(fn ($model) => $model->relatedModels->count())->all());
        $this->assertArrayNotHasKey('id', $models->first()->toArray());
    }

    #[Test]
    public function root_fieldset_keeps_the_keys_of_eager_loads_added_through_the_wizard(): void
    {
        $models = $this->wizard(TestModel::query(), ['fields' => ['testModel' => 'name']])
            ->allowedFields('name')
            ->with('relatedModels')
            ->get();

        $this->assertSame([2, 2], $models->map(fn ($model) => $model->relatedModels->count())->all());
        $this->assertSame(['name', 'related_models'], array_keys($models->first()->toArray()));
        $this->assertSame('select "test_models"."name", "test_models"."id" from "test_models"', $this->rootQuery());
    }

    #[Test]
    public function root_fieldset_keeps_the_keys_of_eager_loads_added_through_the_wizard_for_every_way_to_run_it(): void
    {
        $wizard = fn (): EloquentQueryWizard => $this->wizard(TestModel::query(), ['fields' => ['testModel' => 'name']])
            ->allowedFields('name')
            ->where('id', '>', 0)
            ->with(['relatedModels'])
            ->orderBy('id');

        $counts = [];
        $wizard()->chunk(10, function ($models) use (&$counts): void {
            $counts['chunk'] = $models->map(fn ($model) => $model->relatedModels->count())->all();
        });
        $counts['lazy'] = $wizard()->lazy()->map(fn ($model) => $model->relatedModels->count())->all();
        $counts['cursor'] = $wizard()->cursor()->map(fn ($model) => $model->relatedModels->count())->all();
        $counts['paginate'] = collect($wizard()->paginate()->items())->map(fn ($model) => $model->relatedModels->count())->all();
        $counts['find'] = [$wizard()->find(1)->relatedModels->count(), $wizard()->find(2)->relatedModels->count()];

        $this->assertSame(array_fill_keys(['chunk', 'lazy', 'cursor', 'paginate', 'find'], [2, 2]), $counts);
        $this->assertSame(['name', 'related_models'], array_keys($wizard()->first()->toArray()));
    }

    #[Test]
    public function root_fieldset_keeps_the_full_select_for_an_unknown_eager_load_added_through_the_wizard(): void
    {
        $models = $this->wizard(TestModel::query(), ['fields' => ['testModel' => 'name']])
            ->allowedFields('name')
            ->with('missingRelation')
            ->toQuery();

        $this->assertSame('select "test_models"."name", "test_models".* from "test_models"', $models->toSql());
    }

    #[Test]
    public function a_developer_select_is_left_alone_without_a_root_fieldset(): void
    {
        $query = $this->wizard(TestModel::query()->select('name'), [])
            ->allowedFields('name')
            ->with('relatedModels')
            ->toQuery();

        $this->assertSame('select "name" from "test_models"', $query->toSql());
    }

    #[Test]
    public function a_developer_select_gets_the_keys_of_eager_loads_added_through_the_wizard_under_a_root_fieldset(): void
    {
        $models = $this->wizard(TestModel::query(), ['fields' => ['testModel' => 'name']])
            ->allowedFields('name')
            ->select('name')
            ->with('relatedModels')
            ->get();

        $this->assertSame([2, 2], $models->map(fn ($model) => $model->relatedModels->count())->all());
        $this->assertSame(['name', 'related_models'], array_keys($models->first()->toArray()));
        $this->assertSame('select "name", "test_models"."id" from "test_models"', $this->rootQuery());
    }

    #[Test]
    public function relation_fieldset_keeps_the_keys_of_nested_eager_loads_added_through_the_wizard(): void
    {
        $models = $this->wizard(TestModel::query(), ['include' => 'relatedModels', 'fields' => ['relatedModels' => 'name']])
            ->allowedIncludes('relatedModels')
            ->allowedFields('relatedModels.name')
            ->with('relatedModels.nestedRelatedModels')
            ->get();

        $related = $models->first()->relatedModels->first();

        $this->assertCount(1, $related->nestedRelatedModels);
        $this->assertSame(['name', 'nested_related_models'], array_keys($related->toArray()));
    }

    #[Test]
    public function root_fieldset_keeps_the_keys_of_callback_include_eager_loads(): void
    {
        $models = $this->wizard(TestModel::query(), ['include' => 'related', 'fields' => ['testModel' => 'name']])
            ->allowedIncludes(EloquentInclude::callback('related', fn ($query) => $query->with('relatedModels')))
            ->allowedFields('name')
            ->get();

        $this->assertSame([2, 2], $models->map(fn ($model) => $model->relatedModels->count())->all());
    }

    #[Test]
    public function relation_fieldset_keeps_the_keys_of_the_related_model_default_eager_loads(): void
    {
        $models = $this->wizard(TestModelWithEagerLoads::query()->without('relatedModels'), [
            'include' => 'eagerRelatedModels',
            'fields' => ['eagerRelatedModels' => 'name'],
        ])
            ->allowedIncludes('eagerRelatedModels')
            ->allowedFields('eagerRelatedModels.name')
            ->get();

        $related = $models->first()->eagerRelatedModels->first();

        $this->assertTrue($related->relationLoaded('nestedRelatedModels'));
        $this->assertCount(1, $related->nestedRelatedModels);
        $this->assertStringNotContainsString('*', $this->queryFrom('related_models'));
    }

    #[Test]
    public function model_wizard_relation_fieldset_keeps_the_keys_of_the_related_model_default_eager_loads(): void
    {
        $model = (new ModelQueryWizard(
            TestModelWithEagerLoads::query()->firstOrFail(),
            new QueryParametersManager(new Request(['include' => 'eagerRelatedModels', 'fields' => ['eagerRelatedModels' => 'name']]))
        ))
            ->allowedIncludes('eagerRelatedModels')
            ->allowedFields('eagerRelatedModels.name')
            ->process();

        $related = $model->eagerRelatedModels->first();

        $this->assertTrue($related->relationLoaded('nestedRelatedModels'));
        $this->assertCount(1, $related->nestedRelatedModels);
    }

    #[Test]
    public function dynamic_relations_are_resolved_for_the_safe_select(): void
    {
        TestModel::resolveRelationUsing('dynamicRelated', fn (TestModel $model) => $model->hasMany(RelatedModel::class, 'test_model_id'));

        $models = $this->wizard(TestModel::query(), ['include' => 'dynamicRelated', 'fields' => ['testModel' => 'name']])
            ->allowedIncludes('dynamicRelated')
            ->allowedFields('name')
            ->get();

        $this->assertSame([2, 2], $models->map(fn ($model) => $model->dynamicRelated->count())->all());
        $this->assertStringNotContainsString('*', $this->rootQuery());
    }

    #[Test]
    public function relation_fieldsets_build_each_included_relation_only_for_the_eager_load(): void
    {
        $calls = [];
        TestModel::resolveRelationUsing('countedRelated', function (TestModel $model) use (&$calls) {
            $calls[] = 'countedRelated';

            return $model->hasMany(RelatedModel::class, 'test_model_id');
        });
        RelatedModel::resolveRelationUsing('countedNested', function (RelatedModel $model) use (&$calls) {
            $calls[] = 'countedNested';

            return $model->hasMany(NestedRelatedModel::class, 'related_model_id');
        });
        $query = [
            'include' => 'countedRelated,countedRelated.countedNested',
            'fields' => ['testModel' => 'name', 'countedRelated' => 'name', 'countedRelated.countedNested' => 'name'],
        ];

        $models = $this->wizard(TestModel::query(), $query)
            ->allowedIncludes('countedRelated', 'countedRelated.countedNested')
            ->allowedFields('name', 'countedRelated.name', 'countedRelated.countedNested.name')
            ->get();

        $this->assertSame(['name'], array_keys($models->first()->countedRelated->first()->countedNested->first()->toArray()));
        $this->assertSame('select "name", "test_model_id", "id" from "related_models" where "related_models"."test_model_id" in (1, 2)', $this->queryFrom('related_models'));
        $this->assertSame(['countedRelated' => 2, 'countedNested' => 2], array_count_values($calls));

        $calls = [];
        (new ModelQueryWizard(TestModel::query()->firstOrFail(), new QueryParametersManager(new Request($query))))
            ->allowedIncludes('countedRelated', 'countedRelated.countedNested')
            ->allowedFields('name', 'countedRelated.name', 'countedRelated.countedNested.name')
            ->process();

        $this->assertSame(['countedRelated' => 1, 'countedNested' => 1], array_count_values($calls));
    }

    #[Test]
    public function eager_load_of_an_unknown_relation_type_keeps_the_full_root_select(): void
    {
        TestModel::resolveRelationUsing('customRelated', fn (TestModel $model) => new class(RelatedModel::query(), $model) extends Relation
        {
            public function addConstraints(): void {}

            public function addEagerConstraints(array $models): void
            {
                $this->query->whereIn('test_model_id', array_map(fn ($model) => $model->getKey(), $models));
            }

            public function initRelation(array $models, $relation): array
            {
                foreach ($models as $model) {
                    $model->setRelation($relation, $this->related->newCollection());
                }

                return $models;
            }

            public function match(array $models, EloquentCollection $results, $relation): array
            {
                foreach ($models as $model) {
                    $model->setRelation($relation, $results->where('test_model_id', $model->getKey())->values());
                }

                return $models;
            }

            public function getResults(): mixed
            {
                return $this->query->get();
            }
        });

        $models = $this->wizard(TestModel::query()->with('customRelated'), ['fields' => ['testModel' => 'name']])
            ->allowedFields('name')
            ->get();

        $this->assertSame([2, 2], $models->map(fn ($model) => $model->customRelated->count())->all());
        $this->assertArrayNotHasKey('id', $models->first()->toArray());
        $this->assertStringContainsString('select *', $this->rootQuery());
    }

    #[Test]
    public function relation_fieldset_keeps_the_related_model_default_counts(): void
    {
        $models = $this->wizard(TestModelWithEagerLoads::query()->without('relatedModels'), [
            'include' => 'countedRelatedModels',
            'fields' => ['countedRelatedModels' => 'name,nested_related_models_count'],
        ])
            ->allowedIncludes('countedRelatedModels')
            ->allowedFields('countedRelatedModels.name', 'countedRelatedModels.nested_related_models_count')
            ->get();

        $this->assertSame(
            ['name', 'nested_related_models_count'],
            array_keys($models->first()->countedRelatedModels->first()->toArray())
        );
        $this->assertSame(1, (int) $models->first()->countedRelatedModels->first()->nested_related_models_count);
    }

    #[Test]
    public function relation_fieldset_keeps_the_select_of_the_relation_definition(): void
    {
        $models = $this->wizard(TestModelWithEagerLoads::query()->without('relatedModels'), [
            'include' => 'markedRelatedModels',
            'fields' => ['markedRelatedModels' => 'name,marker'],
        ])
            ->allowedIncludes('markedRelatedModels')
            ->allowedFields('markedRelatedModels.name', 'markedRelatedModels.marker')
            ->get();

        $this->assertSame(['name' => $models->first()->markedRelatedModels->first()->name, 'marker' => 'marked'], $models->first()->markedRelatedModels->first()->toArray());
    }

    #[Test]
    public function relation_fieldset_keeps_a_developer_select_on_the_relation(): void
    {
        $models = $this->wizard(
            TestModel::query()->with(['relatedModels' => fn ($query) => $query->select('id', 'test_model_id', 'name')->selectRaw("'dev' as marker")]),
            ['include' => 'relatedModels', 'fields' => ['relatedModels' => 'marker']]
        )
            ->allowedIncludes('relatedModels')
            ->allowedFields('relatedModels.marker')
            ->get();

        $this->assertSame(['marker' => 'dev'], $models->first()->relatedModels->first()->toArray());
    }

    #[Test]
    public function relation_fieldset_does_not_narrow_a_relation_that_joins_a_table(): void
    {
        TestModel::resolveRelationUsing('joinedRelated', fn (TestModel $model) => $model
            ->hasMany(RelatedModel::class, 'test_model_id')
            ->join('test_models as owners', 'owners.id', '=', 'related_models.test_model_id'));

        $models = $this->wizard(TestModel::query(), ['include' => 'joinedRelated', 'fields' => ['joinedRelated' => 'name']])
            ->allowedIncludes('joinedRelated')
            ->allowedFields('joinedRelated.name')
            ->get();

        $this->assertSame([2, 2], $models->map(fn ($model) => $model->joinedRelated->count())->all());
        $models->each(fn ($model) => $model->joinedRelated->each(
            fn ($related) => $this->assertSame(['name'], array_keys($related->toArray()))
        ));
        $this->assertStringStartsWith('select * from "related_models" inner join', $this->queryFrom('related_models'));
    }

    #[Test]
    public function relation_fieldset_may_name_a_column_of_the_table_the_relation_joins(): void
    {
        TestModel::resolveRelationUsing('joinedNested', fn (TestModel $model) => $model
            ->hasMany(RelatedModel::class, 'test_model_id')
            ->join('nested_related_models', 'nested_related_models.related_model_id', '=', 'related_models.id'));

        $models = $this->wizard(TestModel::query(), ['include' => 'joinedNested', 'fields' => ['joinedNested' => 'related_model_id']])
            ->allowedIncludes('joinedNested')
            ->allowedFields('joinedNested.related_model_id')
            ->get();

        foreach ($models as $model) {
            $this->assertEqualsCanonicalizing(
                RelatedModel::query()->where('test_model_id', $model->id)->pluck('id')->map(fn ($id) => ['related_model_id' => $id])->all(),
                $model->joinedNested->map(fn ($related) => $related->toArray())->all()
            );
        }
    }

    #[Test]
    public function relation_fieldset_works_with_one_of_many_relations(): void
    {
        $models = $this->wizard(TestModelWithEagerLoads::query()->without('relatedModels'), [
            'include' => 'latestRelatedModel',
            'fields' => ['latestRelatedModel' => 'name'],
        ])
            ->allowedIncludes('latestRelatedModel')
            ->allowedFields('latestRelatedModel.name')
            ->get();

        $latest = RelatedModel::query()->where('test_model_id', $models->first()->id)->orderByDesc('id')->firstOrFail();

        $this->assertSame(['name' => $latest->name], $models->first()->latestRelatedModel->toArray());
    }

    /**
     * @param  Builder<Model>  $subject
     * @param  array<string, mixed>  $query
     */
    private function wizard(Builder $subject, array $query): EloquentQueryWizard
    {
        return new EloquentQueryWizard($subject, new QueryParametersManager(new Request($query)));
    }

    private function rootQuery(): string
    {
        return $this->queryFrom('test_models');
    }

    private function queryFrom(string $table): string
    {
        return (string) collect(DB::getQueryLog())
            ->pluck('query')
            ->first(fn (string $sql) => str_contains($sql, "from \"{$table}\""));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function relationResolvers(): array
    {
        return (new ReflectionProperty(Model::class, 'relationResolvers'))->getValue();
    }
}

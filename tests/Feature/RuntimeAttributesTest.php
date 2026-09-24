<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\ProvidesRuntimeAttributes;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Includes\AbstractInclude;
use Jackardios\QueryWizard\Includes\CallbackInclude;
use Jackardios\QueryWizard\ModelQueryWizard;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Attributes that includes add to the models stay visible under sparse fieldsets.
 */
#[Group('include')]
#[Group('fields')]
class RuntimeAttributesTest extends TestCase
{
    private TestModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = TestModel::factory()->create(['name' => 'root']);
        RelatedModel::factory()->count(2)->create(['test_model_id' => $this->model->id])
            ->each(fn (RelatedModel $related) => NestedRelatedModel::factory()->count(3)->create(['related_model_id' => $related->id]));
    }

    #[Test]
    public function declared_root_attributes_of_a_callback_include_stay_visible(): void
    {
        $model = $this->eloquentWizard(['include' => 'visibleCount', 'fields' => ['testModel' => 'name']])
            ->allowedIncludes($this->visibleCountInclude()->withRuntimeAttributes('visible_related_count'))
            ->allowedFields('name')
            ->get()
            ->first();

        $this->assertSame(['name' => 'root', 'visible_related_count' => 2], $this->normalized($model));
    }

    #[Test]
    public function undeclared_attributes_are_still_hidden(): void
    {
        $model = $this->eloquentWizard(['include' => 'visibleCount', 'fields' => ['testModel' => 'name']])
            ->allowedIncludes($this->visibleCountInclude())
            ->allowedFields('name')
            ->get()
            ->first();

        $this->assertSame(['name' => 'root'], $model->toArray());
    }

    #[Test]
    public function a_requested_runtime_attribute_is_not_selected_as_a_column(): void
    {
        $model = $this->eloquentWizard(['include' => 'visibleCount', 'fields' => ['testModel' => 'name,visible_related_count']])
            ->allowedIncludes($this->visibleCountInclude()->withRuntimeAttributes('visible_related_count'))
            ->allowedFields('name', 'visible_related_count')
            ->get()
            ->first();

        $this->assertSame(['name' => 'root', 'visible_related_count' => 2], $this->normalized($model));
    }

    #[Test]
    public function nested_runtime_attributes_belong_to_the_level_that_owns_the_relation(): void
    {
        $model = $this->eloquentWizard([
            'include' => 'relatedModels,nestedCount',
            'fields' => ['testModel' => 'name', 'relatedModels' => 'name'],
        ])
            ->allowedIncludes('relatedModels', $this->nestedCountInclude())
            ->allowedFields('name', 'relatedModels.name')
            ->get()
            ->first();

        $related = $model->toArray()['related_models'];

        $this->assertSame(['name', 'nested_related_models_count'], array_keys($related[0]));
        $this->assertSame([3, 3], array_map(fn (array $item) => (int) $item['nested_related_models_count'], $related));
        $this->assertArrayNotHasKey('nested_related_models_count', $model->getAttributes());
    }

    #[Test]
    public function model_wizard_keeps_count_and_exists_includes_visible_under_root_fields(): void
    {
        $model = $this->modelWizard(['include' => 'relatedModelsCount,relatedModelsExists', 'fields' => ['testModel' => 'name']])
            ->allowedIncludes(EloquentInclude::count('relatedModels'), EloquentInclude::exists('relatedModels'))
            ->allowedFields('name')
            ->process();

        $this->assertSame(['name' => 'root', 'related_models_count' => 2, 'related_models_exists' => true], $this->normalized($model));
    }

    #[Test]
    public function model_wizard_loads_exists_includes(): void
    {
        $model = $this->modelWizard(['include' => 'relatedModelsExists'])
            ->allowedIncludes(EloquentInclude::exists('relatedModels'))
            ->process();

        $this->assertTrue((bool) $model->toArray()['related_models_exists']);
    }

    #[Test]
    public function model_wizard_keeps_declared_attributes_visible(): void
    {
        $model = $this->modelWizard([
            'include' => 'visibleCount,relatedModels,nestedCount',
            'fields' => ['testModel' => 'name', 'relatedModels' => 'name'],
        ])
            ->allowedIncludes(
                $this->visibleCountInclude()->withRuntimeAttributes('visible_related_count'),
                'relatedModels',
                $this->nestedCountInclude()
            )
            ->allowedFields('name', 'relatedModels.name')
            ->process()
            ->toArray();

        $this->assertSame(['name', 'visible_related_count', 'related_models'], array_keys($model));
        $this->assertSame(2, (int) $model['visible_related_count']);
        $this->assertSame(['name', 'nested_related_models_count'], array_keys($model['related_models'][0]));
    }

    private function visibleCountInclude(): CallbackInclude
    {
        $count = ['relatedModels as visible_related_count' => fn ($query) => $query->whereNotNull('name')];

        return EloquentInclude::callback('visibleCount', fn ($subject) => $subject instanceof Model
            ? $subject->loadCount($count)
            : $subject->withCount($count));
    }

    private function nestedCountInclude(): IncludeInterface
    {
        return new class('relatedModels.nestedRelatedModels', 'nestedCount') extends AbstractInclude implements ProvidesRuntimeAttributes
        {
            public function __construct(string $relation, string $alias)
            {
                parent::__construct($relation, $alias);
            }

            public function getType(): string
            {
                return 'callback';
            }

            public function runtimeAttributes(): array
            {
                return ['nested_related_models_count'];
            }

            public function apply(mixed $subject): mixed
            {
                if ($subject instanceof Model) {
                    $subject->loadMissing('relatedModels');
                    $subject->getRelation('relatedModels')->loadCount('nestedRelatedModels');

                    return $subject;
                }

                /** @var Builder<Model> $subject */
                return $subject->with(['relatedModels' => fn ($query) => $query->withCount('nestedRelatedModels')]);
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function normalized(Model $model): array
    {
        return array_map(
            fn (mixed $value) => is_numeric($value) ? (int) $value : $value,
            $model->toArray()
        );
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function eloquentWizard(array $query): EloquentQueryWizard
    {
        return new EloquentQueryWizard(TestModel::query(), new QueryParametersManager(new Request($query)));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function modelWizard(array $query): ModelQueryWizard
    {
        return new ModelQueryWizard(TestModel::query()->findOrFail($this->model->id), new QueryParametersManager(new Request($query)));
    }
}

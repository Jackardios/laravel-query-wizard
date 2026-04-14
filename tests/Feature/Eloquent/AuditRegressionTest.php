<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\MaxFiltersCountExceeded;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('regression')]
class AuditRegressionTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = TestModel::factory()->count(3)->create();

        $this->models->each(function (TestModel $model): void {
            RelatedModel::factory()->count(2)->create([
                'test_model_id' => $model->id,
            ]);
        });
    }

    #[Test]
    public function exact_filter_rejects_malformed_nested_payload(): void
    {
        $this->expectException(InvalidFilterQuery::class);
        $this->expectExceptionMessage('Invalid `filter` parameter format');

        $this
            ->createEloquentWizardWithFilters(['name' => ['foo' => ['bar' => 'Alpha']]])
            ->allowedFilters(EloquentFilter::exact('name'))
            ->get();
    }

    #[Test]
    public function partial_filter_rejects_malformed_nested_payload(): void
    {
        $this->expectException(InvalidFilterQuery::class);
        $this->expectExceptionMessage('Invalid `filter` parameter format');

        $this
            ->createEloquentWizardWithFilters(['name' => ['foo' => ['bar' => 'Alpha']]])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();
    }

    #[Test]
    public function operator_filter_rejects_malformed_nested_payload(): void
    {
        $this->expectException(InvalidFilterQuery::class);
        $this->expectExceptionMessage('Invalid `filter` parameter format');

        $this
            ->createEloquentWizardWithFilters(['name' => ['foo' => ['bar' => 'Alpha']]])
            ->allowedFilters(EloquentFilter::operator('name'))
            ->get();
    }

    #[Test]
    public function exact_filter_can_opt_in_to_structured_input_normalization(): void
    {
        $targetModel = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => ['value' => $targetModel->name]])
            ->allowedFilters(
                EloquentFilter::exact('name')
                    ->allowStructuredInput()
                    ->prepareValueWith(static fn (array $value) => $value['value'] ?? null)
            )
            ->get();

        $this->assertCount(1, $models);
        $this->assertSame($targetModel->id, $models->first()->id);
    }

    #[Test]
    public function structured_input_opt_in_still_validates_prepared_value_shape(): void
    {
        $this->expectException(InvalidFilterQuery::class);
        $this->expectExceptionMessage('Invalid `filter` parameter format');

        $this
            ->createEloquentWizardWithFilters(['name' => ['value' => 'Alpha']])
            ->allowedFilters(
                EloquentFilter::exact('name')
                    ->allowStructuredInput()
                    ->prepareValueWith(static fn (array $value) => ['invalid' => $value])
            )
            ->get();
    }

    #[Test]
    public function range_filter_accepts_flat_list_values(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => [2, 3]])
            ->allowedFilters(EloquentFilter::range('id'))
            ->get();

        $this->assertCount(2, $models);
        $this->assertSame([2, 3], $models->pluck('id')->all());
    }

    #[Test]
    public function range_filter_rejects_nested_boundary_values(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createEloquentWizardWithFilters(['id' => ['min' => ['nested' => 1], 'max' => 3]])
            ->allowedFilters(EloquentFilter::range('id'))
            ->get();
    }

    #[Test]
    public function date_range_filter_rejects_nested_boundary_values(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createEloquentWizardWithFilters(['created_at' => ['from' => ['nested' => '2020-01-01'], 'to' => '2030-01-01']])
            ->allowedFilters(EloquentFilter::dateRange('created_at'))
            ->get();
    }

    #[Test]
    public function malformed_filter_shape_is_not_suppressed_when_unknown_filter_exceptions_are_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_filter_query_exception', true);

        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createEloquentWizardWithFilters(['name' => ['foo' => ['bar' => 'Alpha']]])
            ->allowedFilters(EloquentFilter::exact('name'))
            ->get();
    }

    #[Test]
    public function malformed_filter_default_value_is_rejected(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createEloquentWizardFromQuery()
            ->allowedFilters(
                EloquentFilter::exact('name')->default(['foo' => ['bar' => 'Alpha']])
            )
            ->get();
    }

    #[Test]
    public function malformed_schema_default_filter_value_is_rejected(): void
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

            public function defaultFilters(QueryWizardInterface $wizard): array
            {
                return ['name' => ['foo' => ['bar' => 'Alpha']]];
            }
        };

        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createEloquentWizardFromQuery()
            ->schema($schema)
            ->get();
    }

    #[Test]
    public function get_passthrough_filters_validates_unknown_filter_names(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createEloquentWizardWithFilters([
                'custom' => 'foo',
                'evil' => 'bar',
            ])
            ->allowedFilters(EloquentFilter::passthrough('custom'))
            ->getPassthroughFilters();
    }

    #[Test]
    public function get_passthrough_filters_respects_disabled_unknown_filter_exceptions(): void
    {
        config()->set('query-wizard.disable_invalid_filter_query_exception', true);

        $passthrough = $this
            ->createEloquentWizardWithFilters([
                'custom' => 'foo',
                'evil' => 'bar',
            ])
            ->allowedFilters(EloquentFilter::passthrough('custom'))
            ->getPassthroughFilters();

        $this->assertSame(['custom' => 'foo'], $passthrough->all());
    }

    #[Test]
    public function get_passthrough_filters_respects_max_filters_count(): void
    {
        config()->set('query-wizard.limits.max_filters_count', 1);

        $this->expectException(MaxFiltersCountExceeded::class);

        $this
            ->createEloquentWizardWithFilters([
                'name' => 'Alpha',
                'custom' => 'foo',
            ])
            ->allowedFilters(
                EloquentFilter::exact('name'),
                EloquentFilter::passthrough('custom')
            )
            ->getPassthroughFilters();
    }

    #[Test]
    public function get_passthrough_filters_skips_values_prepared_to_null(): void
    {
        $passthrough = $this
            ->createEloquentWizardWithFilters(['flag' => '1'])
            ->allowedFilters(
                EloquentFilter::passthrough('flag')->when(static fn (): bool => false)
            )
            ->getPassthroughFilters();

        $this->assertTrue($passthrough->isEmpty());
    }

    #[Test]
    public function get_passthrough_filters_rejects_malformed_regular_filters_in_mixed_requests(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createEloquentWizardWithFilters([
                'name' => ['foo' => ['bar' => 'Alpha']],
                'custom' => 'foo',
            ])
            ->allowedFilters(
                EloquentFilter::partial('name'),
                EloquentFilter::passthrough('custom')
            )
            ->getPassthroughFilters();
    }

    #[Test]
    public function count_include_remains_visible_with_root_fields(): void
    {
        $model = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModelsCount',
                'fields' => ['testModel' => 'id,name'],
            ])
            ->allowedIncludes(EloquentInclude::count('relatedModels'))
            ->allowedFields('id', 'name')
            ->firstOrFail();

        $this->assertSame(2, $model->related_models_count);
        $this->assertArrayHasKey('related_models_count', $model->toArray());
    }

    #[Test]
    public function exists_include_remains_visible_with_root_fields(): void
    {
        $model = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModelsExists',
                'fields' => ['testModel' => 'id,name'],
            ])
            ->allowedIncludes(EloquentInclude::exists('relatedModels'))
            ->allowedFields('id', 'name')
            ->firstOrFail();

        $this->assertTrue($model->related_models_exists);
        $this->assertArrayHasKey('related_models_exists', $model->toArray());
    }

    #[Test]
    public function default_count_include_remains_visible_with_root_fields(): void
    {
        $model = $this
            ->createEloquentWizardWithFields(['testModel' => 'id'])
            ->allowedIncludes(EloquentInclude::count('relatedModels'))
            ->defaultIncludes('relatedModelsCount')
            ->allowedFields('id')
            ->firstOrFail();

        $this->assertSame(2, $model->related_models_count);
        $this->assertArrayHasKey('related_models_count', $model->toArray());
    }

    #[Test]
    public function aliased_count_include_remains_visible_with_root_fields(): void
    {
        $model = $this
            ->createEloquentWizardFromQuery([
                'include' => 'totalRelated',
                'fields' => ['testModel' => 'id'],
            ])
            ->allowedIncludes(EloquentInclude::count('relatedModels')->alias('totalRelated'))
            ->allowedFields('id')
            ->firstOrFail();

        $this->assertSame(2, $model->related_models_count);
        $this->assertArrayHasKey('related_models_count', $model->toArray());
    }

    #[Test]
    public function aliased_exists_include_remains_visible_with_root_fields(): void
    {
        $model = $this
            ->createEloquentWizardFromQuery([
                'include' => 'hasRelated',
                'fields' => ['testModel' => 'id'],
            ])
            ->allowedIncludes(EloquentInclude::exists('relatedModels')->alias('hasRelated'))
            ->allowedFields('id')
            ->firstOrFail();

        $this->assertTrue($model->related_models_exists);
        $this->assertArrayHasKey('related_models_exists', $model->toArray());
    }

    #[Test]
    public function explicit_empty_root_fieldset_still_keeps_requested_count_include_visible(): void
    {
        $model = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModelsCount',
                'fields' => ['testModel' => ''],
            ])
            ->allowedIncludes(EloquentInclude::count('relatedModels'))
            ->firstOrFail();

        $this->assertSame(['related_models_count' => 2], $model->toArray());
    }

    #[Test]
    public function include_public_name_in_root_fields_acts_as_visibility_hint_without_sql_error(): void
    {
        $model = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModelsCount',
                'fields' => ['testModel' => 'id,relatedModelsCount'],
            ])
            ->allowedIncludes(EloquentInclude::count('relatedModels'))
            ->allowedFields('id', 'relatedModelsCount')
            ->firstOrFail();

        $this->assertSame([
            'id' => $model->id,
            'related_models_count' => 2,
        ], $model->toArray());
    }

    #[Test]
    public function count_sort_still_orders_correctly_with_root_fields(): void
    {
        ['low' => $low, 'medium' => $medium, 'high' => $high] = $this->createCountSortFixture();

        $models = $this
            ->createEloquentWizardFromQuery([
                'sort' => '-popularity',
                'fields' => ['testModel' => 'id,name'],
            ], TestModel::query()->whereKey([$low->id, $medium->id, $high->id]))
            ->allowedSorts(EloquentSort::count('relatedModels')->alias('popularity'))
            ->allowedFields('id', 'name')
            ->get();

        $this->assertSame([$high->id, $medium->id, $low->id], $models->pluck('id')->all());
        $this->assertArrayNotHasKey('related_models_count', $models->first()->toArray());
    }

    #[Test]
    public function relation_sort_still_orders_correctly_with_root_fields(): void
    {
        ['apple' => $apple, 'mango' => $mango, 'zebra' => $zebra] = $this->createRelationSortFixture();

        $models = $this
            ->createEloquentWizardFromQuery([
                'sort' => 'relatedName',
                'fields' => ['testModel' => 'id,name'],
            ], TestModel::query()->whereKey([$apple->id, $mango->id, $zebra->id]))
            ->allowedSorts(EloquentSort::relation('relatedModels', 'name', 'max')->alias('relatedName'))
            ->allowedFields('id', 'name')
            ->get();

        $this->assertSame([$apple->id, $mango->id, $zebra->id], $models->pluck('id')->all());
    }

    #[Test]
    public function count_sort_without_fields_still_exposes_count_attribute(): void
    {
        ['low' => $low, 'medium' => $medium, 'high' => $high] = $this->createCountSortFixture();

        $models = $this
            ->createEloquentWizardWithSorts('-popularity', TestModel::query()->whereKey([$low->id, $medium->id, $high->id]))
            ->allowedSorts(EloquentSort::count('relatedModels')->alias('popularity'))
            ->get();

        $this->assertSame([$high->id, $medium->id, $low->id], $models->pluck('id')->all());
        $this->assertSame(5, $models->first()->related_models_count);
    }

    /**
     * @return array{low: TestModel, medium: TestModel, high: TestModel}
     */
    private function createCountSortFixture(): array
    {
        $low = TestModel::factory()->create(['name' => 'Low Count']);
        $medium = TestModel::factory()->create(['name' => 'Medium Count']);
        $high = TestModel::factory()->create(['name' => 'High Count']);

        RelatedModel::factory()->count(1)->create(['test_model_id' => $low->id]);
        RelatedModel::factory()->count(3)->create(['test_model_id' => $medium->id]);
        RelatedModel::factory()->count(5)->create(['test_model_id' => $high->id]);

        return [
            'low' => $low,
            'medium' => $medium,
            'high' => $high,
        ];
    }

    /**
     * @return array{apple: TestModel, mango: TestModel, zebra: TestModel}
     */
    private function createRelationSortFixture(): array
    {
        $zebra = TestModel::factory()->create(['name' => 'Zebra Parent']);
        $apple = TestModel::factory()->create(['name' => 'Apple Parent']);
        $mango = TestModel::factory()->create(['name' => 'Mango Parent']);

        RelatedModel::factory()->create([
            'test_model_id' => $zebra->id,
            'name' => 'Zebra',
        ]);
        RelatedModel::factory()->create([
            'test_model_id' => $apple->id,
            'name' => 'Apple',
        ]);
        RelatedModel::factory()->create([
            'test_model_id' => $mango->id,
            'name' => 'Mango',
        ]);

        return [
            'apple' => $apple,
            'mango' => $mango,
            'zebra' => $zebra,
        ];
    }
}

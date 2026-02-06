<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('filter')]
#[Group('exact-filter')]
class ExactFilterTest extends EloquentFilterTestCase
{
    #[Test]
    public function it_can_filter_by_exact_property(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => $this->models->first()->name])
            ->allowedFilters('name')
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($this->models->first()->id, $models->first()->id);
    }

    #[Test]
    public function it_can_filter_by_exact_property_with_definition(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => $this->models->first()->name])
            ->allowedFilters(EloquentFilter::exact('name'))
            ->get();

        $this->assertCount(1, $models);
    }

    #[Test]
    public function it_can_filter_by_exact_property_with_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['n' => $this->models->first()->name])
            ->allowedFilters(EloquentFilter::exact('name')->alias('n'))
            ->get();

        $this->assertCount(1, $models);
    }

    #[Test]
    public function it_can_filter_by_array_of_values(): void
    {
        $names = $this->models->take(2)->pluck('name')->toArray();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $names])
            ->allowedFilters('name')
            ->get();

        $this->assertCount(2, $models);
    }

    #[Test]
    public function it_can_filter_by_comma_separated_values(): void
    {
        $names = $this->models->take(2)->pluck('name')->implode(',');

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $names])
            ->allowedFilters('name')
            ->get();

        $this->assertCount(2, $models);
    }

    #[Test]
    public function exact_filter_is_case_sensitive_on_sqlite(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => strtoupper($model->name)])
            ->allowedFilters('name')
            ->get();

        // SQLite is case-sensitive by default
        $this->assertCount(0, $models);
    }

    #[Test]
    public function it_uses_default_filter_value_when_not_provided(): void
    {
        TestModel::factory()->create(['name' => 'default_value']);

        $models = $this
            ->createEloquentWizardFromQuery()
            ->allowedFilters(EloquentFilter::exact('name')->default('default_value'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals('default_value', $models->first()->name);
    }

    #[Test]
    public function it_ignores_default_when_filter_is_provided(): void
    {
        TestModel::factory()->create(['name' => 'default_value']);
        TestModel::factory()->create(['name' => 'explicit_value']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'explicit_value'])
            ->allowedFilters(EloquentFilter::exact('name')->default('default_value'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals('explicit_value', $models->first()->name);
    }

    #[Test]
    public function exact_filter_handles_integer_values(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['id' => $model->id])
            ->allowedFilters('id')
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($model->id, $models->first()->id);
    }

    #[Test]
    public function exact_filter_handles_zero_value(): void
    {
        TestModel::factory()->create(['name' => '0']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => '0'])
            ->allowedFilters('name')
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals('0', $models->first()->name);
    }

    #[Test]
    public function it_can_filter_multiple_properties_with_definitions(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters([
                'name' => $model->name,
                'id' => $model->id,
            ])
            ->allowedFilters(
                EloquentFilter::exact('name'),
                EloquentFilter::exact('id')
            )
            ->get();

        $this->assertCount(1, $models);
    }

    #[Test]
    public function it_can_mix_string_and_definition_filters(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters([
                'name' => $model->name,
                'id' => $model->id,
            ])
            ->allowedFilters(
                'name',
                EloquentFilter::exact('id')
            )
            ->get();

        $this->assertCount(1, $models);
    }

    #[Test]
    public function default_filter_works_with_array_value(): void
    {
        $targetModels = $this->models->take(2);
        $names = $targetModels->pluck('name')->toArray();

        $models = $this
            ->createEloquentWizardFromQuery()
            ->allowedFilters(EloquentFilter::exact('name')->default($names))
            ->get();

        $this->assertCount(2, $models);
    }

    #[Test]
    public function prepare_value_can_transform_value(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'INPUT_'.$model->name])
            ->allowedFilters(
                EloquentFilter::exact('name')
                    ->prepareValueWith(fn ($v) => str_replace('INPUT_', '', $v))
            )
            ->get();

        $this->assertCount(1, $models);
    }

    #[Test]
    public function prepare_value_receives_original_array(): void
    {
        $receivedValue = null;

        $this
            ->createEloquentWizardWithFilters(['name' => ['a', 'b', 'c']])
            ->allowedFilters(
                EloquentFilter::exact('name')
                    ->prepareValueWith(function ($v) use (&$receivedValue) {
                        $receivedValue = $v;

                        return $v;
                    })
            )
            ->get();

        $this->assertEquals(['a', 'b', 'c'], $receivedValue);
    }

    #[Test]
    public function it_returns_all_models_for_empty_array_value(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery()
            ->allowedFilters(EloquentFilter::exact('name')->default([]))
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function it_returns_all_models_when_prepare_value_returns_empty_array(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'anything'])
            ->allowedFilters(
                EloquentFilter::exact('name')
                    ->prepareValueWith(fn () => [])
            )
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function it_qualifies_column_names(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => 'test'])
            ->allowedFilters('name')
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('"test_models"."name"', $sql);
    }
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('filter')]
#[Group('callback-filter')]
class CallbackFilterTest extends EloquentFilterTestCase
{
    #[Test]
    public function it_can_filter_by_callback(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['custom' => $model->name])
            ->allowedFilters(
                EloquentFilter::callback('custom', function ($query, $value) {
                    $query->where('name', $value);
                })
            )
            ->get();

        $this->assertCount(1, $models);
    }

    #[Test]
    public function callback_filter_receives_property_name(): void
    {
        $receivedProperty = null;

        $this
            ->createEloquentWizardWithFilters(['search' => 'test'])
            ->allowedFilters(
                EloquentFilter::callback('name', function ($query, $value, $property) use (&$receivedProperty) {
                    $receivedProperty = $property;
                })->alias('search')
            )
            ->get();

        $this->assertEquals('name', $receivedProperty);
    }

    #[Test]
    public function callback_filter_with_array_callback(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['custom' => $model->name])
            ->allowedFilters(
                EloquentFilter::callback('custom', [$this, 'customFilterCallback'])
            )
            ->get();

        $this->assertCount(1, $models);
    }

    public function customFilterCallback($query, $value): void
    {
        $query->where('name', $value);
    }

    #[Test]
    public function callback_filter_can_add_complex_conditions(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['search' => $model->name])
            ->allowedFilters(
                EloquentFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'LIKE', "%{$value}%")
                            ->orWhere('id', $value);
                    });
                })
            )
            ->get();

        $this->assertTrue($models->contains('id', $model->id));
        $this->assertNotEmpty($models);
    }

    #[Test]
    public function callback_filter_can_modify_query_builder(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['limit' => 2])
            ->allowedFilters(
                EloquentFilter::callback('limit', function ($query, $value) {
                    $query->limit((int) $value);
                })
            )
            ->get();

        $this->assertCount(2, $models);
    }

    #[Test]
    public function it_prepares_filter_value_with_callback(): void
    {
        $receivedValue = null;

        $this
            ->createEloquentWizardWithFilters(['name' => 'TRANSFORM_ME'])
            ->allowedFilters(
                EloquentFilter::callback('name', function ($query, $value, $property) use (&$receivedValue) {
                    $receivedValue = $value;
                })->prepareValueWith(fn ($v) => strtolower($v))
            )
            ->get();

        $this->assertEquals('transform_me', $receivedValue);
    }
}

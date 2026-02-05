<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('filter')]
#[Group('passthrough-filter')]
class PassthroughFilterTest extends EloquentFilterTestCase
{
    #[Test]
    public function it_captures_passthrough_filter_values(): void
    {
        $wizard = $this
            ->createEloquentWizardWithFilters([
                'name' => 'test',
                'custom' => 'foo',
            ])
            ->allowedFilters(
                'name',
                EloquentFilter::passthrough('custom')
            );

        $wizard->get();

        $passthrough = $wizard->getPassthroughFilters();
        $this->assertEquals(['custom' => 'foo'], $passthrough->all());
    }

    #[Test]
    public function passthrough_filters_do_not_modify_query(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['custom' => 'value'])
            ->allowedFilters(EloquentFilter::passthrough('custom'))
            ->toQuery()
            ->toSql();

        // Should NOT contain WHERE clause for passthrough filter
        $this->assertStringNotContainsString('custom', strtolower($sql));
    }

    #[Test]
    public function passthrough_filters_pass_validation(): void
    {
        // Should NOT throw InvalidFilterQuery
        $wizard = $this
            ->createEloquentWizardWithFilters([
                'name' => $this->models->first()->name,
                'custom' => 'foo',
            ])
            ->allowedFilters(
                'name',
                EloquentFilter::passthrough('custom')
            );

        $result = $wizard->get();

        $this->assertNotEmpty($result);
        $this->assertEquals(['custom' => 'foo'], $wizard->getPassthroughFilters()->all());
    }

    #[Test]
    public function passthrough_filter_with_default_value(): void
    {
        $wizard = $this
            ->createEloquentWizardFromQuery()
            ->allowedFilters(
                EloquentFilter::passthrough('custom')->default('default_value')
            );

        $passthrough = $wizard->getPassthroughFilters();
        $this->assertEquals(['custom' => 'default_value'], $passthrough->all());
    }

    #[Test]
    public function passthrough_filter_with_value_preparation(): void
    {
        $wizard = $this
            ->createEloquentWizardWithFilters(['custom' => 'UPPERCASE'])
            ->allowedFilters(
                EloquentFilter::passthrough('custom')
                    ->prepareValueWith(fn ($v) => strtolower($v))
            );

        $passthrough = $wizard->getPassthroughFilters();
        $this->assertEquals(['custom' => 'uppercase'], $passthrough->all());
    }

    #[Test]
    public function it_returns_empty_when_no_passthrough_value_in_request(): void
    {
        $wizard = $this
            ->createEloquentWizardWithFilters(['name' => 'test'])
            ->allowedFilters(
                'name',
                EloquentFilter::passthrough('custom')
            );

        $passthrough = $wizard->getPassthroughFilters();
        $this->assertTrue($passthrough->isEmpty());
    }

    #[Test]
    public function multiple_passthrough_filters(): void
    {
        $wizard = $this
            ->createEloquentWizardWithFilters([
                'a' => '1',
                'b' => '2',
                'c' => '3',
            ])
            ->allowedFilters(
                EloquentFilter::passthrough('a'),
                EloquentFilter::passthrough('b'),
                EloquentFilter::passthrough('c')
            );

        $passthrough = $wizard->getPassthroughFilters();
        $this->assertCount(3, $passthrough);
        $this->assertEquals(['a' => '1', 'b' => '2', 'c' => '3'], $passthrough->all());
    }

    #[Test]
    public function mixed_regular_and_passthrough_filters(): void
    {
        $targetModel = $this->models->first();

        $wizard = $this
            ->createEloquentWizardWithFilters([
                'name' => $targetModel->name,
                'custom' => 'passthrough_value',
            ])
            ->allowedFilters(
                EloquentFilter::exact('name'),
                EloquentFilter::passthrough('custom')
            );

        $result = $wizard->get();

        // Regular filter applied
        $this->assertCount(1, $result);
        $this->assertEquals($targetModel->name, $result->first()->name);

        // Passthrough captured
        $this->assertEquals(['custom' => 'passthrough_value'], $wizard->getPassthroughFilters()->all());
    }
}

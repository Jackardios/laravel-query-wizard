<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\Filters\DateRangeFilter;
use Jackardios\QueryWizard\Eloquent\Filters\ExactFilter;
use Jackardios\QueryWizard\Eloquent\Filters\JsonContainsFilter;
use Jackardios\QueryWizard\Eloquent\Filters\NullFilter;
use Jackardios\QueryWizard\Eloquent\Filters\PartialFilter;
use Jackardios\QueryWizard\Eloquent\Filters\RangeFilter;
use Jackardios\QueryWizard\Eloquent\Filters\ScopeFilter;
use Jackardios\QueryWizard\Eloquent\Filters\TrashedFilter;
use Jackardios\QueryWizard\Filters\CallbackFilter;
use Jackardios\QueryWizard\Filters\PassthroughFilter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FilterDefinitionTest extends TestCase
{
    // ========== ExactFilter Base Tests ==========

    #[Test]
    public function it_creates_exact_filter_with_make(): void
    {
        $filter = ExactFilter::make('name');

        $this->assertEquals('exact', $filter->getType());
        $this->assertEquals('name', $filter->getProperty());
        $this->assertEquals('name', $filter->getName()); // name = alias ?? property
        $this->assertNull($filter->getDefault());
        $this->assertNull($filter->getAlias());
    }

    #[Test]
    public function it_sets_alias(): void
    {
        $filter = ExactFilter::make('property_name')->alias('alias');

        $this->assertEquals('property_name', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
        $this->assertEquals('alias', $filter->getAlias());
    }

    #[Test]
    public function it_sets_alias_fluently(): void
    {
        $filter = ExactFilter::make('name');
        $result = $filter->alias('alias');

        $this->assertSame($filter, $result);
        $this->assertEquals('alias', $filter->getAlias());
    }

    #[Test]
    public function it_sets_default_value(): void
    {
        $filter = ExactFilter::make('status')->default('active');

        $this->assertEquals('active', $filter->getDefault());
    }

    #[Test]
    public function it_sets_default_value_fluently(): void
    {
        $filter = ExactFilter::make('status');
        $result = $filter->default('active');

        $this->assertSame($filter, $result);
        $this->assertEquals('active', $filter->getDefault());
    }

    #[Test]
    public function it_handles_null_default_value(): void
    {
        $filter = ExactFilter::make('status')->default(null);

        $this->assertNull($filter->getDefault());
    }

    #[Test]
    public function it_handles_array_default_value(): void
    {
        $filter = ExactFilter::make('statuses')->default(['active', 'pending']);

        $this->assertEquals(['active', 'pending'], $filter->getDefault());
    }

    #[Test]
    public function it_handles_boolean_default_value(): void
    {
        $filter = ExactFilter::make('is_active')->default(true);

        $this->assertTrue($filter->getDefault());
    }

    #[Test]
    public function it_handles_integer_default_value(): void
    {
        $filter = ExactFilter::make('count')->default(0);

        $this->assertSame(0, $filter->getDefault());
    }

    #[Test]
    public function it_sets_prepare_value_callback(): void
    {
        $filter = ExactFilter::make('status')
            ->prepareValueWith(fn ($value) => strtoupper($value));

        $this->assertEquals('ACTIVE', $filter->prepareValue('active'));
    }

    #[Test]
    public function it_chains_multiple_modifiers(): void
    {
        $filter = ExactFilter::make('status')
            ->alias('state')
            ->default('pending')
            ->prepareValueWith(fn ($v) => strtolower($v));

        $this->assertEquals('status', $filter->getProperty());
        $this->assertEquals('state', $filter->getName());
        $this->assertEquals('pending', $filter->getDefault());
        $this->assertEquals('test', $filter->prepareValue('TEST'));
    }

    // ========== EloquentFilter Factory Tests ==========

    #[Test]
    public function it_creates_exact_filter(): void
    {
        $filter = EloquentFilter::exact('name');

        $this->assertInstanceOf(ExactFilter::class, $filter);
        $this->assertInstanceOf(FilterInterface::class, $filter);
        $this->assertEquals('name', $filter->getProperty());
        $this->assertEquals('name', $filter->getName());
        $this->assertEquals('exact', $filter->getType());
    }

    #[Test]
    public function it_creates_exact_filter_with_alias(): void
    {
        $filter = EloquentFilter::exact('property_name', 'alias');

        $this->assertEquals('property_name', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    #[Test]
    public function it_creates_partial_filter(): void
    {
        $filter = EloquentFilter::partial('search');

        $this->assertInstanceOf(PartialFilter::class, $filter);
        $this->assertEquals('search', $filter->getProperty());
        $this->assertEquals('partial', $filter->getType());
    }

    #[Test]
    public function it_creates_partial_filter_with_alias(): void
    {
        $filter = EloquentFilter::partial('name', 'q');

        $this->assertEquals('name', $filter->getProperty());
        $this->assertEquals('q', $filter->getName());
        $this->assertEquals('partial', $filter->getType());
    }

    #[Test]
    public function it_creates_scope_filter(): void
    {
        $filter = EloquentFilter::scope('active');

        $this->assertInstanceOf(ScopeFilter::class, $filter);
        $this->assertEquals('active', $filter->getProperty());
        $this->assertEquals('scope', $filter->getType());
    }

    #[Test]
    public function it_creates_scope_filter_with_alias(): void
    {
        $filter = EloquentFilter::scope('isActive', 'active');

        $this->assertEquals('isActive', $filter->getProperty());
        $this->assertEquals('active', $filter->getName());
    }

    #[Test]
    public function it_creates_trashed_filter(): void
    {
        $filter = EloquentFilter::trashed();

        $this->assertInstanceOf(TrashedFilter::class, $filter);
        $this->assertEquals('trashed', $filter->getProperty());
        $this->assertEquals('trashed', $filter->getType());
        $this->assertEquals('trashed', $filter->getName());
    }

    #[Test]
    public function it_creates_trashed_filter_with_alias(): void
    {
        $filter = EloquentFilter::trashed('deleted');

        $this->assertEquals('trashed', $filter->getProperty());
        $this->assertEquals('deleted', $filter->getName());
    }

    #[Test]
    public function it_creates_callback_filter(): void
    {
        $callback = fn ($query, $value) => $query->where('name', $value);
        $filter = EloquentFilter::callback('name', $callback);

        $this->assertInstanceOf(CallbackFilter::class, $filter);
        $this->assertEquals('name', $filter->getProperty());
        $this->assertEquals('callback', $filter->getType());
    }

    #[Test]
    public function it_creates_callback_filter_with_alias(): void
    {
        $callback = fn ($query, $value) => $query->where('name', $value);
        $filter = EloquentFilter::callback('full_name', $callback, 'name');

        $this->assertEquals('full_name', $filter->getProperty());
        $this->assertEquals('name', $filter->getName());
    }

    #[Test]
    public function it_creates_range_filter(): void
    {
        $filter = EloquentFilter::range('price');

        $this->assertInstanceOf(RangeFilter::class, $filter);
        $this->assertEquals('price', $filter->getProperty());
        $this->assertEquals('range', $filter->getType());
    }

    #[Test]
    public function it_creates_date_range_filter(): void
    {
        $filter = EloquentFilter::dateRange('created_at');

        $this->assertInstanceOf(DateRangeFilter::class, $filter);
        $this->assertEquals('created_at', $filter->getProperty());
        $this->assertEquals('date_range', $filter->getType());
    }

    #[Test]
    public function it_creates_null_filter(): void
    {
        $filter = EloquentFilter::null('deleted_at');

        $this->assertInstanceOf(NullFilter::class, $filter);
        $this->assertEquals('deleted_at', $filter->getProperty());
        $this->assertEquals('null', $filter->getType());
    }

    #[Test]
    public function it_creates_json_contains_filter(): void
    {
        $filter = EloquentFilter::jsonContains('meta.roles');

        $this->assertInstanceOf(JsonContainsFilter::class, $filter);
        $this->assertEquals('meta.roles', $filter->getProperty());
        $this->assertEquals('json_contains', $filter->getType());
    }

    #[Test]
    public function it_creates_passthrough_filter(): void
    {
        $filter = EloquentFilter::passthrough('custom');

        $this->assertInstanceOf(PassthroughFilter::class, $filter);
        $this->assertEquals('custom', $filter->getProperty());
        $this->assertEquals('passthrough', $filter->getType());
    }

    // ========== Filter-specific Options Tests ==========

    #[Test]
    public function range_filter_can_set_keys(): void
    {
        $filter = RangeFilter::make('price')
            ->minKey('min_price')
            ->maxKey('max_price');

        $this->assertInstanceOf(RangeFilter::class, $filter);
    }

    #[Test]
    public function date_range_filter_can_set_keys(): void
    {
        $filter = DateRangeFilter::make('created_at')
            ->fromKey('start')
            ->toKey('end');

        $this->assertInstanceOf(DateRangeFilter::class, $filter);
    }

    #[Test]
    public function date_range_filter_can_set_date_format(): void
    {
        $filter = DateRangeFilter::make('created_at')
            ->dateFormat('Y-m-d');

        $this->assertInstanceOf(DateRangeFilter::class, $filter);
    }

    #[Test]
    public function json_contains_filter_can_use_match_all(): void
    {
        $filter = JsonContainsFilter::make('tags')
            ->matchAll();

        $this->assertInstanceOf(JsonContainsFilter::class, $filter);
    }

    #[Test]
    public function json_contains_filter_can_use_match_any(): void
    {
        $filter = JsonContainsFilter::make('tags')
            ->matchAny();

        $this->assertInstanceOf(JsonContainsFilter::class, $filter);
    }
}

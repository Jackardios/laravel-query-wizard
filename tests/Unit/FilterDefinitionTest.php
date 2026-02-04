<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\FilterDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\DateRangeFilter;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\ExactFilter;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\JsonContainsFilter;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\NullFilter;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\PartialFilter;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\RangeFilter;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\ScopeFilter;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\TrashedFilter;
use Jackardios\QueryWizard\Filters\CallbackFilter;
use Jackardios\QueryWizard\Filters\PassthroughFilter;
use PHPUnit\Framework\TestCase;

class FilterDefinitionTest extends TestCase
{
    #[Test]
    public function it_creates_exact_filter(): void
    {
        $filter = FilterDefinition::exact('name');

        $this->assertInstanceOf(FilterInterface::class, $filter);
        $this->assertInstanceOf(ExactFilter::class, $filter);
        $this->assertEquals('name', $filter->getProperty());
        $this->assertEquals('name', $filter->getName());
        $this->assertEquals('exact', $filter->getType());
        $this->assertNull($filter->getAlias());
        $this->assertNull($filter->getDefault());
    }

    #[Test]
    public function it_creates_exact_filter_with_alias(): void
    {
        $filter = FilterDefinition::exact('property_name', 'alias');

        $this->assertEquals('property_name', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
        $this->assertEquals('alias', $filter->getAlias());
    }

    #[Test]
    public function it_creates_partial_filter(): void
    {
        $filter = FilterDefinition::partial('search');

        $this->assertInstanceOf(PartialFilter::class, $filter);
        $this->assertEquals('search', $filter->getProperty());
        $this->assertEquals('partial', $filter->getType());
    }

    #[Test]
    public function it_creates_partial_filter_with_alias(): void
    {
        $filter = FilterDefinition::partial('name', 'q');

        $this->assertEquals('name', $filter->getProperty());
        $this->assertEquals('q', $filter->getName());
        $this->assertEquals('partial', $filter->getType());
    }

    #[Test]
    public function it_creates_scope_filter(): void
    {
        $filter = FilterDefinition::scope('active');

        $this->assertInstanceOf(ScopeFilter::class, $filter);
        $this->assertEquals('active', $filter->getProperty());
        $this->assertEquals('scope', $filter->getType());
    }

    #[Test]
    public function it_creates_scope_filter_with_alias(): void
    {
        $filter = FilterDefinition::scope('isActive', 'active');

        $this->assertEquals('isActive', $filter->getProperty());
        $this->assertEquals('active', $filter->getName());
    }

    #[Test]
    public function it_creates_trashed_filter(): void
    {
        $filter = FilterDefinition::trashed();

        $this->assertInstanceOf(TrashedFilter::class, $filter);
        $this->assertEquals('trashed', $filter->getProperty());
        $this->assertEquals('trashed', $filter->getType());
        $this->assertEquals('trashed', $filter->getName());
    }

    #[Test]
    public function it_creates_trashed_filter_with_alias(): void
    {
        $filter = FilterDefinition::trashed('deleted');

        $this->assertEquals('trashed', $filter->getProperty());
        $this->assertEquals('deleted', $filter->getName());
    }

    #[Test]
    public function it_creates_callback_filter(): void
    {
        $callback = fn($query, $value) => $query->where('name', $value);
        $filter = FilterDefinition::callback('name', $callback);

        $this->assertInstanceOf(CallbackFilter::class, $filter);
        $this->assertEquals('name', $filter->getProperty());
        $this->assertEquals('callback', $filter->getType());
    }

    #[Test]
    public function it_creates_callback_filter_with_alias(): void
    {
        $callback = fn($query, $value) => $query->where('name', $value);
        $filter = FilterDefinition::callback('full_name', $callback, 'name');

        $this->assertEquals('full_name', $filter->getProperty());
        $this->assertEquals('name', $filter->getName());
    }

    #[Test]
    public function it_creates_range_filter(): void
    {
        $filter = FilterDefinition::range('price');

        $this->assertInstanceOf(RangeFilter::class, $filter);
        $this->assertEquals('price', $filter->getProperty());
        $this->assertEquals('range', $filter->getType());
    }

    #[Test]
    public function it_creates_date_range_filter(): void
    {
        $filter = FilterDefinition::dateRange('created_at');

        $this->assertInstanceOf(DateRangeFilter::class, $filter);
        $this->assertEquals('created_at', $filter->getProperty());
        $this->assertEquals('dateRange', $filter->getType());
    }

    #[Test]
    public function it_creates_null_filter(): void
    {
        $filter = FilterDefinition::null('deleted_at');

        $this->assertInstanceOf(NullFilter::class, $filter);
        $this->assertEquals('deleted_at', $filter->getProperty());
        $this->assertEquals('null', $filter->getType());
    }

    #[Test]
    public function it_creates_json_contains_filter(): void
    {
        $filter = FilterDefinition::jsonContains('meta.roles');

        $this->assertInstanceOf(JsonContainsFilter::class, $filter);
        $this->assertEquals('meta.roles', $filter->getProperty());
        $this->assertEquals('jsonContains', $filter->getType());
    }

    #[Test]
    public function it_creates_passthrough_filter(): void
    {
        $filter = FilterDefinition::passthrough('custom');

        $this->assertInstanceOf(PassthroughFilter::class, $filter);
        $this->assertEquals('custom', $filter->getProperty());
        $this->assertEquals('passthrough', $filter->getType());
    }

    #[Test]
    public function it_sets_default_value(): void
    {
        $filter = FilterDefinition::exact('status')->default('active');

        $this->assertEquals('active', $filter->getDefault());
    }

    #[Test]
    public function it_sets_default_value_immutably(): void
    {
        $original = FilterDefinition::exact('status');
        $withDefault = $original->default('active');

        $this->assertNull($original->getDefault());
        $this->assertEquals('active', $withDefault->getDefault());
        $this->assertNotSame($original, $withDefault);
    }

    #[Test]
    public function it_sets_prepare_value_callback(): void
    {
        $filter = FilterDefinition::exact('status')
            ->prepareValueWith(fn($value) => strtoupper($value));

        $this->assertEquals('ACTIVE', $filter->prepareValue('active'));
    }

    #[Test]
    public function it_returns_value_unchanged_without_prepare_callback(): void
    {
        $filter = FilterDefinition::exact('status');

        $this->assertEquals('active', $filter->prepareValue('active'));
    }

    #[Test]
    public function it_sets_alias_via_method(): void
    {
        $filter = FilterDefinition::exact('name')->alias('alias');

        $this->assertEquals('alias', $filter->getName());
        $this->assertEquals('alias', $filter->getAlias());
    }

    #[Test]
    public function it_sets_alias_immutably(): void
    {
        $original = FilterDefinition::exact('name');
        $withAlias = $original->alias('alias');

        $this->assertNull($original->getAlias());
        $this->assertEquals('alias', $withAlias->getAlias());
        $this->assertNotSame($original, $withAlias);
    }

    #[Test]
    public function it_chains_multiple_modifiers(): void
    {
        $filter = FilterDefinition::exact('status')
            ->alias('state')
            ->default('pending')
            ->prepareValueWith(fn($v) => strtolower($v));

        $this->assertEquals('status', $filter->getProperty());
        $this->assertEquals('state', $filter->getName());
        $this->assertEquals('pending', $filter->getDefault());
        $this->assertEquals('test', $filter->prepareValue('TEST'));
    }

    #[Test]
    public function it_handles_null_default_value(): void
    {
        $filter = FilterDefinition::exact('status')->default(null);

        $this->assertNull($filter->getDefault());
    }

    #[Test]
    public function it_handles_array_default_value(): void
    {
        $filter = FilterDefinition::exact('statuses')->default(['active', 'pending']);

        $this->assertEquals(['active', 'pending'], $filter->getDefault());
    }

    #[Test]
    public function it_handles_boolean_default_value(): void
    {
        $filter = FilterDefinition::exact('is_active')->default(true);

        $this->assertTrue($filter->getDefault());
    }

    #[Test]
    public function it_handles_integer_default_value(): void
    {
        $filter = FilterDefinition::exact('count')->default(0);

        $this->assertSame(0, $filter->getDefault());
    }

    #[Test]
    public function it_prepares_complex_values(): void
    {
        $filter = FilterDefinition::exact('tags')
            ->prepareValueWith(fn($value) => is_array($value) ? array_map('trim', $value) : trim($value));

        $this->assertEquals(['a', 'b', 'c'], $filter->prepareValue([' a ', ' b ', ' c ']));
        $this->assertEquals('test', $filter->prepareValue(' test '));
    }

    #[Test]
    public function exact_filter_can_set_with_relation_constraint(): void
    {
        $filter = FilterDefinition::exact('posts.title')->withRelationConstraint(false);

        $this->assertInstanceOf(ExactFilter::class, $filter);
    }

    #[Test]
    public function scope_filter_can_set_resolve_model_bindings(): void
    {
        $filter = FilterDefinition::scope('active')->resolveModelBindings(false);

        $this->assertInstanceOf(ScopeFilter::class, $filter);
    }

    #[Test]
    public function null_filter_can_set_invert_logic(): void
    {
        $filter = FilterDefinition::null('deleted_at')->invertLogic();

        $this->assertInstanceOf(NullFilter::class, $filter);
    }

    #[Test]
    public function range_filter_can_set_keys(): void
    {
        $filter = FilterDefinition::range('price')->keys('min_price', 'max_price');

        $this->assertInstanceOf(RangeFilter::class, $filter);
    }

    #[Test]
    public function date_range_filter_can_set_keys(): void
    {
        $filter = FilterDefinition::dateRange('created_at')->keys('start', 'end');

        $this->assertInstanceOf(DateRangeFilter::class, $filter);
    }

    #[Test]
    public function date_range_filter_can_set_date_format(): void
    {
        $filter = FilterDefinition::dateRange('created_at')->dateFormat('Y-m-d');

        $this->assertInstanceOf(DateRangeFilter::class, $filter);
    }

    #[Test]
    public function json_contains_filter_can_set_match_all(): void
    {
        $filter = FilterDefinition::jsonContains('tags')->matchAll(false);

        $this->assertInstanceOf(JsonContainsFilter::class, $filter);
    }
}

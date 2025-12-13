<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Closure;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\FilterDefinition;
use PHPUnit\Framework\TestCase;

class FilterDefinitionTest extends TestCase
{
    /** @test */
    public function it_creates_exact_filter(): void
    {
        $filter = FilterDefinition::exact('name');

        $this->assertInstanceOf(FilterDefinitionInterface::class, $filter);
        $this->assertEquals('name', $filter->getProperty());
        $this->assertEquals('name', $filter->getName());
        $this->assertEquals('exact', $filter->getType());
        $this->assertNull($filter->getAlias());
        $this->assertNull($filter->getDefault());
        $this->assertNull($filter->getCallback());
        $this->assertNull($filter->getStrategyClass());
        $this->assertEquals([], $filter->getOptions());
    }

    /** @test */
    public function it_creates_exact_filter_with_alias(): void
    {
        $filter = FilterDefinition::exact('property_name', 'alias');

        $this->assertEquals('property_name', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
        $this->assertEquals('alias', $filter->getAlias());
    }

    /** @test */
    public function it_creates_partial_filter(): void
    {
        $filter = FilterDefinition::partial('search');

        $this->assertEquals('search', $filter->getProperty());
        $this->assertEquals('partial', $filter->getType());
    }

    /** @test */
    public function it_creates_partial_filter_with_alias(): void
    {
        $filter = FilterDefinition::partial('name', 'q');

        $this->assertEquals('name', $filter->getProperty());
        $this->assertEquals('q', $filter->getName());
        $this->assertEquals('partial', $filter->getType());
    }

    /** @test */
    public function it_creates_scope_filter(): void
    {
        $filter = FilterDefinition::scope('active');

        $this->assertEquals('active', $filter->getProperty());
        $this->assertEquals('scope', $filter->getType());
    }

    /** @test */
    public function it_creates_scope_filter_with_alias(): void
    {
        $filter = FilterDefinition::scope('isActive', 'active');

        $this->assertEquals('isActive', $filter->getProperty());
        $this->assertEquals('active', $filter->getName());
    }

    /** @test */
    public function it_creates_trashed_filter(): void
    {
        $filter = FilterDefinition::trashed();

        $this->assertEquals('trashed', $filter->getProperty());
        $this->assertEquals('trashed', $filter->getType());
        $this->assertEquals('trashed', $filter->getName());
    }

    /** @test */
    public function it_creates_trashed_filter_with_alias(): void
    {
        $filter = FilterDefinition::trashed('deleted');

        $this->assertEquals('trashed', $filter->getProperty());
        $this->assertEquals('deleted', $filter->getName());
    }

    /** @test */
    public function it_creates_callback_filter(): void
    {
        $callback = fn($query, $value) => $query->where('name', $value);
        $filter = FilterDefinition::callback('name', $callback);

        $this->assertEquals('name', $filter->getProperty());
        $this->assertEquals('callback', $filter->getType());
        $this->assertInstanceOf(Closure::class, $filter->getCallback());
    }

    /** @test */
    public function it_creates_callback_filter_with_alias(): void
    {
        $callback = fn($query, $value) => $query->where('name', $value);
        $filter = FilterDefinition::callback('full_name', $callback, 'name');

        $this->assertEquals('full_name', $filter->getProperty());
        $this->assertEquals('name', $filter->getName());
    }

    /** @test */
    public function it_creates_custom_filter(): void
    {
        $filter = FilterDefinition::custom('name', 'App\\Filters\\CustomFilter');

        $this->assertEquals('name', $filter->getProperty());
        $this->assertEquals('custom', $filter->getType());
        $this->assertEquals('App\\Filters\\CustomFilter', $filter->getStrategyClass());
    }

    /** @test */
    public function it_creates_range_filter(): void
    {
        $filter = FilterDefinition::range('price');

        $this->assertEquals('price', $filter->getProperty());
        $this->assertEquals('range', $filter->getType());
    }

    /** @test */
    public function it_creates_date_range_filter(): void
    {
        $filter = FilterDefinition::dateRange('created_at');

        $this->assertEquals('created_at', $filter->getProperty());
        $this->assertEquals('dateRange', $filter->getType());
    }

    /** @test */
    public function it_creates_null_filter(): void
    {
        $filter = FilterDefinition::null('deleted_at');

        $this->assertEquals('deleted_at', $filter->getProperty());
        $this->assertEquals('null', $filter->getType());
    }

    /** @test */
    public function it_creates_json_contains_filter(): void
    {
        $filter = FilterDefinition::jsonContains('meta.roles');

        $this->assertEquals('meta.roles', $filter->getProperty());
        $this->assertEquals('jsonContains', $filter->getType());
    }

    /** @test */
    public function it_sets_default_value(): void
    {
        $filter = FilterDefinition::exact('status')->default('active');

        $this->assertEquals('active', $filter->getDefault());
    }

    /** @test */
    public function it_sets_default_value_immutably(): void
    {
        $original = FilterDefinition::exact('status');
        $withDefault = $original->default('active');

        $this->assertNull($original->getDefault());
        $this->assertEquals('active', $withDefault->getDefault());
        $this->assertNotSame($original, $withDefault);
    }

    /** @test */
    public function it_sets_prepare_value_callback(): void
    {
        $filter = FilterDefinition::exact('status')
            ->prepareValueWith(fn($value) => strtoupper($value));

        $this->assertEquals('ACTIVE', $filter->prepareValue('active'));
    }

    /** @test */
    public function it_returns_value_unchanged_without_prepare_callback(): void
    {
        $filter = FilterDefinition::exact('status');

        $this->assertEquals('active', $filter->prepareValue('active'));
    }

    /** @test */
    public function it_sets_options(): void
    {
        $filter = FilterDefinition::exact('name')
            ->withOptions(['key1' => 'value1', 'key2' => 'value2']);

        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $filter->getOptions());
        $this->assertEquals('value1', $filter->getOption('key1'));
        $this->assertEquals('value2', $filter->getOption('key2'));
    }

    /** @test */
    public function it_returns_default_for_missing_option(): void
    {
        $filter = FilterDefinition::exact('name');

        $this->assertNull($filter->getOption('missing'));
        $this->assertEquals('default', $filter->getOption('missing', 'default'));
    }

    /** @test */
    public function it_merges_options(): void
    {
        $filter = FilterDefinition::exact('name')
            ->withOptions(['key1' => 'value1'])
            ->withOptions(['key2' => 'value2']);

        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $filter->getOptions());
    }

    /** @test */
    public function it_sets_with_relation_constraint(): void
    {
        $filter = FilterDefinition::exact('posts.title')->withRelationConstraint(false);

        $this->assertFalse($filter->getOption('withRelationConstraint'));
    }

    /** @test */
    public function it_defaults_with_relation_constraint_to_true(): void
    {
        $filter = FilterDefinition::exact('posts.title')->withRelationConstraint();

        $this->assertTrue($filter->getOption('withRelationConstraint'));
    }

    /** @test */
    public function it_chains_multiple_modifiers(): void
    {
        $filter = FilterDefinition::exact('status', 'state')
            ->default('pending')
            ->prepareValueWith(fn($v) => strtolower($v))
            ->withOptions(['custom' => true])
            ->withRelationConstraint(false);

        $this->assertEquals('status', $filter->getProperty());
        $this->assertEquals('state', $filter->getName());
        $this->assertEquals('pending', $filter->getDefault());
        $this->assertEquals('test', $filter->prepareValue('TEST'));
        $this->assertTrue($filter->getOption('custom'));
        $this->assertFalse($filter->getOption('withRelationConstraint'));
    }

    /** @test */
    public function it_handles_null_default_value(): void
    {
        $filter = FilterDefinition::exact('status')->default(null);

        $this->assertNull($filter->getDefault());
    }

    /** @test */
    public function it_handles_array_default_value(): void
    {
        $filter = FilterDefinition::exact('statuses')->default(['active', 'pending']);

        $this->assertEquals(['active', 'pending'], $filter->getDefault());
    }

    /** @test */
    public function it_handles_boolean_default_value(): void
    {
        $filter = FilterDefinition::exact('is_active')->default(true);

        $this->assertTrue($filter->getDefault());
    }

    /** @test */
    public function it_handles_integer_default_value(): void
    {
        $filter = FilterDefinition::exact('count')->default(0);

        $this->assertSame(0, $filter->getDefault());
    }

    /** @test */
    public function it_prepares_complex_values(): void
    {
        $filter = FilterDefinition::exact('tags')
            ->prepareValueWith(fn($value) => is_array($value) ? array_map('trim', $value) : trim($value));

        $this->assertEquals(['a', 'b', 'c'], $filter->prepareValue([' a ', ' b ', ' c ']));
        $this->assertEquals('test', $filter->prepareValue(' test '));
    }
}

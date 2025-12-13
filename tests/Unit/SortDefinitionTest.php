<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Closure;
use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\SortDefinition;
use PHPUnit\Framework\TestCase;

class SortDefinitionTest extends TestCase
{
    /** @test */
    public function it_creates_field_sort(): void
    {
        $sort = SortDefinition::field('name');

        $this->assertInstanceOf(SortDefinitionInterface::class, $sort);
        $this->assertEquals('name', $sort->getProperty());
        $this->assertEquals('name', $sort->getName());
        $this->assertEquals('field', $sort->getType());
        $this->assertNull($sort->getAlias());
        $this->assertNull($sort->getCallback());
        $this->assertNull($sort->getStrategyClass());
        $this->assertEquals([], $sort->getOptions());
    }

    /** @test */
    public function it_creates_field_sort_with_alias(): void
    {
        $sort = SortDefinition::field('full_name', 'name');

        $this->assertEquals('full_name', $sort->getProperty());
        $this->assertEquals('name', $sort->getName());
        $this->assertEquals('name', $sort->getAlias());
    }

    /** @test */
    public function it_creates_callback_sort(): void
    {
        $callback = fn($query, $direction) => $query->orderBy('name', $direction);
        $sort = SortDefinition::callback('name', $callback);

        $this->assertEquals('name', $sort->getProperty());
        $this->assertEquals('callback', $sort->getType());
        $this->assertInstanceOf(Closure::class, $sort->getCallback());
    }

    /** @test */
    public function it_creates_callback_sort_with_alias(): void
    {
        $callback = fn($query, $direction) => $query->orderBy('popularity_score', $direction);
        $sort = SortDefinition::callback('popularity_score', $callback, 'popularity');

        $this->assertEquals('popularity_score', $sort->getProperty());
        $this->assertEquals('popularity', $sort->getName());
    }

    /** @test */
    public function it_creates_custom_sort(): void
    {
        $sort = SortDefinition::custom('name', 'App\\Sorts\\CustomSort');

        $this->assertEquals('name', $sort->getProperty());
        $this->assertEquals('custom', $sort->getType());
        $this->assertEquals('App\\Sorts\\CustomSort', $sort->getStrategyClass());
    }

    /** @test */
    public function it_creates_custom_sort_with_alias(): void
    {
        $sort = SortDefinition::custom('popularity_score', 'App\\Sorts\\CustomSort', 'popularity');

        $this->assertEquals('popularity_score', $sort->getProperty());
        $this->assertEquals('popularity', $sort->getName());
    }

    /** @test */
    public function it_sets_options(): void
    {
        $sort = SortDefinition::field('name')
            ->withOptions(['nulls' => 'last']);

        $this->assertEquals(['nulls' => 'last'], $sort->getOptions());
        $this->assertEquals('last', $sort->getOption('nulls'));
    }

    /** @test */
    public function it_returns_default_for_missing_option(): void
    {
        $sort = SortDefinition::field('name');

        $this->assertNull($sort->getOption('missing'));
        $this->assertEquals('default', $sort->getOption('missing', 'default'));
    }

    /** @test */
    public function it_merges_options(): void
    {
        $sort = SortDefinition::field('name')
            ->withOptions(['key1' => 'value1'])
            ->withOptions(['key2' => 'value2']);

        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $sort->getOptions());
    }

    /** @test */
    public function it_creates_options_immutably(): void
    {
        $original = SortDefinition::field('name');
        $withOptions = $original->withOptions(['key' => 'value']);

        $this->assertEquals([], $original->getOptions());
        $this->assertEquals(['key' => 'value'], $withOptions->getOptions());
        $this->assertNotSame($original, $withOptions);
    }

    /** @test */
    public function it_handles_descending_sort_alias(): void
    {
        $sort = SortDefinition::field('created_at', '-created_at');

        $this->assertEquals('created_at', $sort->getProperty());
        $this->assertEquals('-created_at', $sort->getName());
    }

    /** @test */
    public function it_handles_relation_property(): void
    {
        $sort = SortDefinition::field('author.name');

        $this->assertEquals('author.name', $sort->getProperty());
    }

    /** @test */
    public function it_handles_snake_case_property(): void
    {
        $sort = SortDefinition::field('created_at');

        $this->assertEquals('created_at', $sort->getProperty());
    }

    /** @test */
    public function it_handles_camelCase_property(): void
    {
        $sort = SortDefinition::field('createdAt');

        $this->assertEquals('createdAt', $sort->getProperty());
    }
}

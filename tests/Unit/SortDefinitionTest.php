<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\Eloquent\Sorts\FieldSort;
use Jackardios\QueryWizard\Sorts\CallbackSort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SortDefinitionTest extends TestCase
{
    // ========== FieldSort Base Tests ==========

    #[Test]
    public function it_creates_field_sort_with_make(): void
    {
        $sort = FieldSort::make('name');

        $this->assertEquals('field', $sort->getType());
        $this->assertEquals('name', $sort->getProperty());
        $this->assertEquals('name', $sort->getName()); // name = alias ?? property
        $this->assertNull($sort->getAlias());
    }

    #[Test]
    public function it_sets_alias(): void
    {
        $sort = FieldSort::make('property_name')->alias('alias');

        $this->assertEquals('property_name', $sort->getProperty());
        $this->assertEquals('alias', $sort->getName());
        $this->assertEquals('alias', $sort->getAlias());
    }

    #[Test]
    public function it_sets_alias_fluently(): void
    {
        $sort = FieldSort::make('name');
        $result = $sort->alias('alias');

        $this->assertSame($sort, $result);
        $this->assertEquals('alias', $sort->getAlias());
    }

    #[Test]
    public function it_creates_callback_sort_with_make(): void
    {
        $cb = fn ($query, $direction, $property) => $query->orderBy('name', $direction);
        $sort = CallbackSort::make('name', $cb);

        $this->assertEquals('callback', $sort->getType());
        $this->assertEquals('name', $sort->getProperty());
        $this->assertEquals('name', $sort->getName());
    }

    // ========== EloquentSort Factory Tests ==========

    #[Test]
    public function it_creates_field_sort(): void
    {
        $sort = EloquentSort::field('name');

        $this->assertInstanceOf(FieldSort::class, $sort);
        $this->assertInstanceOf(SortInterface::class, $sort);
        $this->assertEquals('name', $sort->getProperty());
        $this->assertEquals('name', $sort->getName());
        $this->assertEquals('field', $sort->getType());
        $this->assertNull($sort->getAlias());
    }

    #[Test]
    public function it_creates_field_sort_with_alias(): void
    {
        $sort = EloquentSort::field('full_name', 'name');

        $this->assertEquals('full_name', $sort->getProperty());
        $this->assertEquals('name', $sort->getName());
        $this->assertEquals('name', $sort->getAlias());
    }

    #[Test]
    public function it_creates_callback_sort(): void
    {
        $callback = fn ($query, $direction, $property) => $query->orderBy('name', $direction);
        $sort = EloquentSort::callback('name', $callback);

        $this->assertInstanceOf(CallbackSort::class, $sort);
        $this->assertInstanceOf(SortInterface::class, $sort);
        $this->assertEquals('name', $sort->getProperty());
        $this->assertEquals('callback', $sort->getType());
    }

    #[Test]
    public function it_creates_callback_sort_with_alias(): void
    {
        $callback = fn ($query, $direction, $property) => $query->orderBy('popularity_score', $direction);
        $sort = EloquentSort::callback('popularity_score', $callback, 'popularity');

        $this->assertEquals('popularity_score', $sort->getProperty());
        $this->assertEquals('popularity', $sort->getName());
    }

    #[Test]
    public function it_sets_alias_via_method(): void
    {
        $sort = EloquentSort::field('name')->alias('alias');

        $this->assertEquals('alias', $sort->getName());
        $this->assertEquals('alias', $sort->getAlias());
    }

    #[Test]
    public function factory_sets_alias_fluently(): void
    {
        $sort = EloquentSort::field('name');
        $result = $sort->alias('alias');

        $this->assertSame($sort, $result);
        $this->assertEquals('alias', $sort->getAlias());
    }

    #[Test]
    public function it_handles_descending_sort_alias(): void
    {
        $sort = EloquentSort::field('created_at', '-created_at');

        $this->assertEquals('created_at', $sort->getProperty());
        $this->assertEquals('-created_at', $sort->getName());
    }

    #[Test]
    public function it_handles_relation_property(): void
    {
        $sort = EloquentSort::field('author.name');

        $this->assertEquals('author.name', $sort->getProperty());
    }

    #[Test]
    public function it_handles_snake_case_property(): void
    {
        $sort = EloquentSort::field('created_at');

        $this->assertEquals('created_at', $sort->getProperty());
    }

    #[Test]
    public function it_handles_camel_case_property(): void
    {
        $sort = EloquentSort::field('createdAt');

        $this->assertEquals('createdAt', $sort->getProperty());
    }
}

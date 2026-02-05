<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Enums\SortDirection;
use Jackardios\QueryWizard\Values\Sort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SortValueTest extends TestCase
{
    #[Test]
    public function it_parses_ascending_sort(): void
    {
        $sort = new Sort('name');

        $this->assertEquals('name', $sort->getField());
        $this->assertEquals('asc', $sort->getDirection());
        $this->assertEquals(SortDirection::Ascending, $sort->getSortDirection());
    }

    #[Test]
    public function it_parses_descending_sort(): void
    {
        $sort = new Sort('-name');

        $this->assertEquals('name', $sort->getField());
        $this->assertEquals('desc', $sort->getDirection());
        $this->assertEquals(SortDirection::Descending, $sort->getSortDirection());
    }

    #[Test]
    public function it_strips_minus_prefix_from_field(): void
    {
        $sort = new Sort('-created_at');

        $this->assertEquals('created_at', $sort->getField());
    }

    #[Test]
    public function it_accepts_explicit_direction(): void
    {
        $sort = new Sort('name', SortDirection::Descending);

        $this->assertEquals('name', $sort->getField());
        $this->assertEquals('desc', $sort->getDirection());
    }

    #[Test]
    public function explicit_direction_overrides_prefix(): void
    {
        $sort = new Sort('-name', SortDirection::Ascending);

        $this->assertEquals('name', $sort->getField());
        $this->assertEquals('asc', $sort->getDirection());
    }

    #[Test]
    public function it_handles_multiple_minus_signs(): void
    {
        $sort = new Sort('--name');

        // ltrim removes ALL leading minuses
        $this->assertEquals('name', $sort->getField());
        // Still desc because it starts with -
        $this->assertEquals('desc', $sort->getDirection());
    }

    #[Test]
    public function it_handles_underscore_field_names(): void
    {
        $sort = new Sort('-created_at');

        $this->assertEquals('created_at', $sort->getField());
    }

    #[Test]
    public function it_handles_dot_notation_field_names(): void
    {
        $sort = new Sort('-author.name');

        $this->assertEquals('author.name', $sort->getField());
        $this->assertEquals('desc', $sort->getDirection());
    }

    #[Test]
    public function parse_sort_direction_returns_ascending_for_regular_field(): void
    {
        $direction = Sort::parseSortDirection('name');

        $this->assertEquals(SortDirection::Ascending, $direction);
    }

    #[Test]
    public function parse_sort_direction_returns_descending_for_prefixed_field(): void
    {
        $direction = Sort::parseSortDirection('-name');

        $this->assertEquals(SortDirection::Descending, $direction);
    }

    #[Test]
    public function it_handles_empty_field(): void
    {
        $sort = new Sort('');

        $this->assertEquals('', $sort->getField());
        $this->assertEquals('asc', $sort->getDirection());
    }

    #[Test]
    public function it_handles_just_minus_sign(): void
    {
        $sort = new Sort('-');

        $this->assertEquals('', $sort->getField());
        $this->assertEquals('desc', $sort->getDirection());
    }
}

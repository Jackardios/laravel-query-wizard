<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use InvalidArgumentException;
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
        $this->assertSame(SortDirection::Ascending, $sort->getDirection());
        $this->assertFalse($sort->isDescending());
    }

    #[Test]
    public function it_parses_descending_sort(): void
    {
        $sort = new Sort('-name');

        $this->assertEquals('name', $sort->getField());
        $this->assertSame(SortDirection::Descending, $sort->getDirection());
        $this->assertTrue($sort->isDescending());
    }

    #[Test]
    public function it_accepts_explicit_direction(): void
    {
        $sort = new Sort('name', SortDirection::Descending);

        $this->assertEquals('name', $sort->getField());
        $this->assertSame(SortDirection::Descending, $sort->getDirection());
    }

    #[Test]
    public function a_prefix_and_a_direction_together_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has a leading `-` and a direction');

        new Sort('-name', SortDirection::Ascending);
    }

    #[Test]
    public function more_than_one_leading_minus_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has more than one leading `-`');

        new Sort('--name');
    }

    #[Test]
    public function it_handles_dot_notation_field_names(): void
    {
        $sort = new Sort('-author.name');

        $this->assertEquals('author.name', $sort->getField());
        $this->assertSame(SortDirection::Descending, $sort->getDirection());
    }

    #[Test]
    public function it_handles_empty_field(): void
    {
        $sort = new Sort('');

        $this->assertEquals('', $sort->getField());
        $this->assertSame(SortDirection::Ascending, $sort->getDirection());
    }

    #[Test]
    public function it_handles_just_minus_sign(): void
    {
        $sort = new Sort('-');

        $this->assertEquals('', $sort->getField());
        $this->assertSame(SortDirection::Descending, $sort->getDirection());
    }
}

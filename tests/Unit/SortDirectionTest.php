<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Enums\SortDirection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SortDirectionTest extends TestCase
{
    #[Test]
    public function ascending_has_correct_value(): void
    {
        $this->assertEquals('asc', SortDirection::Ascending->value);
    }

    #[Test]
    public function descending_has_correct_value(): void
    {
        $this->assertEquals('desc', SortDirection::Descending->value);
    }

    #[Test]
    public function it_can_be_created_from_string(): void
    {
        $asc = SortDirection::from('asc');
        $desc = SortDirection::from('desc');

        $this->assertEquals(SortDirection::Ascending, $asc);
        $this->assertEquals(SortDirection::Descending, $desc);
    }

    #[Test]
    public function it_throws_for_invalid_string(): void
    {
        $this->expectException(\ValueError::class);

        SortDirection::from('invalid');
    }

    #[Test]
    public function it_returns_null_for_invalid_try_from(): void
    {
        $result = SortDirection::tryFrom('invalid');

        $this->assertNull($result);
    }

    #[Test]
    #[DataProvider('validDirectionsProvider')]
    public function try_from_returns_correct_enum_for_valid_values(string $value, SortDirection $expected): void
    {
        $result = SortDirection::tryFrom($value);

        $this->assertEquals($expected, $result);
    }

    public static function validDirectionsProvider(): array
    {
        return [
            'asc' => ['asc', SortDirection::Ascending],
            'desc' => ['desc', SortDirection::Descending],
        ];
    }

    #[Test]
    public function it_has_exactly_two_cases(): void
    {
        $cases = SortDirection::cases();

        $this->assertCount(2, $cases);
        $this->assertContains(SortDirection::Ascending, $cases);
        $this->assertContains(SortDirection::Descending, $cases);
    }

}

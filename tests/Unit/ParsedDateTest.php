<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Jackardios\QueryWizard\Support\FilterValueParser;
use Jackardios\QueryWizard\Support\ParsedDate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParsedDateTest extends TestCase
{
    #[Test]
    public function a_date_names_its_whole_day(): void
    {
        $date = self::date('2024-01-31', true);

        [$upTo, $end] = $date->upToBound();
        $this->assertSame('<', $upTo);
        $this->assertSame('2024-02-01 00:00:00', $end->value->format('Y-m-d H:i:s'));
        $this->assertTrue($end->dateOnly);

        [$after, $start] = $date->afterBound();
        $this->assertSame('>=', $after);
        $this->assertSame('2024-02-01 00:00:00', $start->value->format('Y-m-d H:i:s'));
        $this->assertTrue($start->dateOnly);
    }

    #[Test]
    public function an_instant_is_its_own_bound(): void
    {
        $instant = self::date('2024-01-31 10:15:00', false);

        $this->assertSame(['<=', $instant], $instant->upToBound());
        $this->assertSame(['>', $instant], $instant->afterBound());
    }

    #[Test]
    public function the_last_day_of_year_9999_ends_at_its_last_second(): void
    {
        $date = self::date('9999-12-31', true);

        [$upTo, $end] = $date->upToBound();
        $this->assertSame('<=', $upTo);
        $this->assertSame('9999-12-31 23:59:59', $end->value->format('Y-m-d H:i:s'));
        $this->assertFalse($end->dateOnly);

        [$after, $start] = $date->afterBound();
        $this->assertSame('>', $after);
        $this->assertSame('9999-12-31 23:59:59', $start->value->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function bounds_keep_the_timezone_of_the_value(): void
    {
        $date = FilterValueParser::isoDate('2024-03-30', 'day', new DateTimeZone('Europe/Moscow'));

        $this->assertNotNull($date);
        $this->assertSame('2024-03-31T00:00:00+03:00', $date->upToBound()[1]->value->format(DATE_ATOM));
    }

    #[Test]
    public function the_default_timezone_is_the_applications(): void
    {
        $this->assertSame(date_default_timezone_get(), FilterValueParser::defaultTimezone()->getName());
    }

    private static function date(string $value, bool $dateOnly): ParsedDate
    {
        return new ParsedDate(new DateTimeImmutable($value, new DateTimeZone('UTC')), $dateOnly);
    }
}

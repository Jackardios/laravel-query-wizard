<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Enums\FilterOperator;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\Support\FilterValueParser;
use Jackardios\QueryWizard\Support\ParsedDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FilterValueParserTest extends TestCase
{
    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function blankValues(): array
    {
        return [
            'null' => [null, true],
            'empty string' => ['', true],
            'whitespace' => [" \t", true],
            'empty list' => [[], true],
            'list of blanks' => [['', null, ' '], true],
            'nested list of blanks' => [[[''], []], true],
            'zero string' => ['0', false],
            'zero' => [0, false],
            'false' => [false, false],
            'list with a value' => [['', 'a'], false],
            'object' => [new \stdClass, false],
        ];
    }

    #[Test]
    #[DataProvider('blankValues')]
    public function it_detects_blank_values(mixed $value, bool $blank): void
    {
        $this->assertSame($blank, FilterValueParser::isBlank($value));
    }

    /**
     * @return array<string, array{mixed, bool|null}>
     */
    public static function booleans(): array
    {
        return [
            'true' => [true, true],
            'false' => [false, false],
            'one' => [1, true],
            'zero' => [0, false],
            'TRUE string' => [' TRUE ', true],
            'yes' => ['yes', true],
            'on' => ['On', true],
            'false string' => ['false', false],
            'no' => ['NO', false],
            'off' => ['off', false],
            'one string' => ['1', true],
            'zero string' => ['0', false],
            'blank' => ['  ', null],
            'null' => [null, null],
        ];
    }

    #[Test]
    #[DataProvider('booleans')]
    public function it_reads_booleans(mixed $value, ?bool $expected): void
    {
        $this->assertSame($expected, FilterValueParser::boolean($value, 'active'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function notBooleans(): array
    {
        return [
            'word' => ['maybe'],
            'two' => [2],
            'float' => [1.0],
            'list' => [['1']],
        ];
    }

    #[Test]
    #[DataProvider('notBooleans')]
    public function it_rejects_values_that_are_not_booleans(mixed $value): void
    {
        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected a boolean (true, false, 1, 0, yes, no, on or off).');

        FilterValueParser::boolean($value, 'active');
    }

    #[Test]
    public function it_reads_trashed_modes(): void
    {
        $this->assertSame('with', FilterValueParser::trashedMode('With', 'trashed'));
        $this->assertSame('with', FilterValueParser::trashedMode(true, 'trashed'));
        $this->assertSame('with', FilterValueParser::trashedMode('true', 'trashed'));
        $this->assertSame('only', FilterValueParser::trashedMode(' only ', 'trashed'));
        $this->assertSame('without', FilterValueParser::trashedMode('without', 'trashed'));
        $this->assertSame('without', FilterValueParser::trashedMode(false, 'trashed'));
        $this->assertNull(FilterValueParser::trashedMode('', 'trashed'));

        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Filter value `all` is invalid for filter `trashed`. Expected one of: with, only, without.');

        FilterValueParser::trashedMode('all', 'trashed');
    }

    /**
     * @return array<string, array{mixed, int|float|string|null}>
     */
    public static function numbers(): array
    {
        return [
            'int' => [42, 42],
            'float' => [1.5, 1.5],
            'integer string' => [' 42 ', 42],
            'signed integer string' => ['+42', 42],
            'negative integer string' => ['-42', -42],
            'decimal string' => ['19.99', 19.99],
            'leading dot' => ['.5', 0.5],
            'negative leading dot' => ['-.5', -0.5],
            'big integer' => ['123456789012345678901234567890', '123456789012345678901234567890'],
            'signed big integer' => ['+123456789012345678901234567890', '123456789012345678901234567890'],
            'blank' => ['', null],
        ];
    }

    #[Test]
    #[DataProvider('numbers')]
    public function it_reads_decimal_numbers(mixed $value, int|float|string|null $expected): void
    {
        $this->assertSame($expected, FilterValueParser::number($value, 'price'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function notNumbers(): array
    {
        return [
            'exponent' => ['1e5'],
            'hex' => ['0x1A'],
            'trailing dot' => ['1.'],
            'word' => ['ten'],
            'nan' => [NAN],
            'infinity' => [INF],
            'bool' => [true],
            'list' => [['1']],
        ];
    }

    #[Test]
    #[DataProvider('notNumbers')]
    public function it_rejects_values_that_are_not_decimal_numbers(mixed $value): void
    {
        $this->expectException(InvalidFilterValue::class);

        FilterValueParser::number($value, 'price');
    }

    #[Test]
    public function the_key_is_named_in_the_reason(): void
    {
        try {
            FilterValueParser::number('abc', EloquentFilter::range('price'), 'min');
            $this->fail('Expected InvalidFilterValue');
        } catch (InvalidFilterValue $exception) {
            $this->assertSame('Filter value `abc` is invalid for filter `price`. Expected a decimal number for `min`.', $exception->getMessage());
            $this->assertSame('Expected a decimal number for `min`.', $exception->reason);
            $this->assertSame('price', $exception->filterName);
            $this->assertSame('abc', $exception->filterValue);
            $this->assertSame(400, $exception->getStatusCode());
        }
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function isoDates(): array
    {
        return [
            'date' => ['2024-02-29', '2024-02-29 00:00:00.000000 +03:00', true],
            'date-time' => ['2024-01-01T10:00:00', '2024-01-01 10:00:00.000000 +03:00', false],
            'space separator' => ['2024-01-01 10:00:00', '2024-01-01 10:00:00.000000 +03:00', false],
            'without seconds' => ['2024-01-01T10:00', '2024-01-01 10:00:00.000000 +03:00', false],
            'utc' => ['2024-01-01T10:00:00Z', '2024-01-01 13:00:00.000000 +03:00', false],
            'lower case' => ['2024-01-01t10:00:00z', '2024-01-01 13:00:00.000000 +03:00', false],
            'offset' => ['2024-01-01T10:00:00+05:30', '2024-01-01 07:30:00.000000 +03:00', false],
            'compact offset' => ['2024-01-01T10:00:00-0200', '2024-01-01 15:00:00.000000 +03:00', false],
            'hour offset' => ['2024-01-01T10:00:00+03', '2024-01-01 10:00:00.000000 +03:00', false],
            'fraction' => ['2024-01-01T10:00:00.123456789Z', '2024-01-01 13:00:00.123456 +03:00', false],
            'surrounding whitespace' => [' 2024-01-01 ', '2024-01-01 00:00:00.000000 +03:00', true],
        ];
    }

    #[Test]
    #[DataProvider('isoDates')]
    public function it_reads_iso_dates_in_the_given_timezone(string $value, string $expected, bool $dateOnly): void
    {
        $date = FilterValueParser::isoDate($value, 'created_at', new DateTimeZone('Europe/Moscow'));

        $this->assertInstanceOf(ParsedDate::class, $date);
        $this->assertSame($expected, $date->value->format('Y-m-d H:i:s.u P'));
        $this->assertSame($dateOnly, $date->dateOnly);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function notIsoDates(): array
    {
        return [
            'impossible day' => ['2023-02-29'],
            'month 13' => ['2024-13-01'],
            'hour 24' => ['2024-01-01T24:00:00'],
            'minute 60' => ['2024-01-01T10:60:00'],
            'second 60' => ['2024-01-01T10:00:60'],
            'offset hour 24' => ['2024-01-01T10:00:00+24:00'],
            'decoded plus' => ['2024-01-01T10:00:00 03:00'],
            'date with offset' => ['2024-01-01+03:00'],
            'relative' => ['tomorrow'],
            'day first' => ['01.02.2024'],
            'timestamp' => [1700000000],
            'list' => [['2024-01-01']],
        ];
    }

    #[Test]
    #[DataProvider('notIsoDates')]
    public function it_rejects_values_that_are_not_iso_dates(mixed $value): void
    {
        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected a date (Y-m-d) or an ISO 8601 date-time.');

        FilterValueParser::isoDate($value, 'created_at', new DateTimeZone('UTC'));
    }

    #[Test]
    public function date_objects_are_converted_to_the_timezone(): void
    {
        $date = FilterValueParser::isoDate(new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC')), 'created_at', new DateTimeZone('Europe/Moscow'));

        $this->assertSame('2024-01-01 13:00:00 +03:00', $date?->value->format('Y-m-d H:i:s P'));
        $this->assertFalse($date->dateOnly);
        $this->assertNull(FilterValueParser::isoDate(' ', 'created_at', new DateTimeZone('UTC')));
    }

    #[Test]
    public function it_reads_lenient_dates(): void
    {
        $timezone = new DateTimeZone('Europe/Moscow');

        $this->assertTrue(FilterValueParser::lenientDate('2024-01-01', 'created_at', $timezone)?->dateOnly);
        $this->assertSame(
            (new DateTimeImmutable('yesterday', $timezone))->format('Y-m-d H:i:s'),
            FilterValueParser::lenientDate('yesterday', 'created_at', $timezone)?->value->format('Y-m-d H:i:s')
        );
        $this->assertSame('2024-02-01 00:00:00', FilterValueParser::lenientDate('1 February 2024', 'created_at', $timezone)?->value->format('Y-m-d H:i:s'));
        $this->assertNull(FilterValueParser::lenientDate('', 'created_at', $timezone));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function notLenientDates(): array
    {
        return [
            'single letter' => ['a'],
            'military zone letter' => ['Z'],
            'impossible iso day' => ['2023-02-29'],
            'gibberish' => ['not a date'],
            'integer' => [20240101],
        ];
    }

    #[Test]
    #[DataProvider('notLenientDates')]
    public function it_rejects_values_that_are_not_dates(mixed $value): void
    {
        $this->expectException(InvalidFilterValue::class);

        FilterValueParser::lenientDate($value, 'created_at', new DateTimeZone('UTC'));
    }

    #[Test]
    public function it_reads_unix_timestamps(): void
    {
        $this->assertSame(1700000000, FilterValueParser::unixTimestamp(1700000000, 'created_at'));
        $this->assertSame(1700000000, FilterValueParser::unixTimestamp(' 1700000000 ', 'created_at'));
        $this->assertSame(-1, FilterValueParser::unixTimestamp('-1', 'created_at'));
        $this->assertNull(FilterValueParser::unixTimestamp('', 'created_at'));

        foreach (['1700000000.5', '99999999999999999999', 'now', 1.0] as $value) {
            try {
                FilterValueParser::unixTimestamp($value, 'created_at', 'from');
                $this->fail('Expected InvalidFilterValue for '.var_export($value, true));
            } catch (InvalidFilterValue $exception) {
                $this->assertSame('Expected a Unix timestamp in whole seconds for `from`.', $exception->reason);
            }
        }
    }

    /**
     * @return array<string, array{mixed, FilterOperator|null, mixed}>
     */
    public static function dynamicValues(): array
    {
        return [
            'plain value' => ['active', FilterOperator::EQUAL, 'active'],
            'equals sign is not an operator' => ['=5', FilterOperator::EQUAL, '=5'],
            'leading space before an operator' => [' >5', FilterOperator::EQUAL, ' >5'],
            'integer' => [5, FilterOperator::EQUAL, 5],
            'float' => [1.5, FilterOperator::EQUAL, 1.5],
            'bool' => [true, FilterOperator::EQUAL, true],
            'list' => [['a', 'b'], FilterOperator::EQUAL, ['a', 'b']],
            'greater than or equal' => ['>=10', FilterOperator::GREATER_THAN_OR_EQUAL, 10],
            'less than or equal' => ['<=10.5', FilterOperator::LESS_THAN_OR_EQUAL, 10.5],
            'greater than' => ['> 10', FilterOperator::GREATER_THAN, 10],
            'less than' => ['<-3', FilterOperator::LESS_THAN, -3],
            'big integer' => ['>123456789012345678901234567890', FilterOperator::GREATER_THAN, '123456789012345678901234567890'],
            'not equal keeps the raw operand' => ['!= draft ', FilterOperator::NOT_EQUAL, ' draft '],
            'angle not equal' => ['<>draft', FilterOperator::NOT_EQUAL, 'draft'],
            'bare operator' => ['>=', null, null],
            'operator with blank operand' => ['>=  ', null, null],
            'blank' => ['', null, null],
            'blank list' => [['', ' '], null, null],
        ];
    }

    #[Test]
    #[DataProvider('dynamicValues')]
    public function it_reads_dynamic_operators(mixed $value, ?FilterOperator $operator, mixed $operand): void
    {
        $result = FilterValueParser::dynamic($value, 'price', new DateTimeZone('UTC'));

        if ($operator === null) {
            $this->assertNull($result);

            return;
        }

        $this->assertSame([$operator, $operand], $result);
    }

    #[Test]
    public function dynamic_comparison_operands_can_be_iso_dates(): void
    {
        $timezone = new DateTimeZone('Europe/Moscow');

        [$operator, $date] = FilterValueParser::dynamic('<=2024-01-31', 'created_at', $timezone) ?? [null, null];

        $this->assertSame(FilterOperator::LESS_THAN_OR_EQUAL, $operator);
        $this->assertInstanceOf(ParsedDate::class, $date);
        $this->assertTrue($date->dateOnly);
        $this->assertSame('2024-01-31 00:00:00 +03:00', $date->value->format('Y-m-d H:i:s P'));

        [, $instant] = FilterValueParser::dynamic('>2024-01-31T10:00:00Z', 'created_at', $timezone) ?? [null, null];

        $this->assertInstanceOf(ParsedDate::class, $instant);
        $this->assertFalse($instant->dateOnly);
        $this->assertSame('2024-01-31 13:00:00 +03:00', $instant->value->format('Y-m-d H:i:s P'));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidDynamicValues(): array
    {
        return [
            'word after a comparison' => ['>abc', 'Expected a number or an ISO 8601 date after `>`.'],
            'exponent' => ['>=1e5', 'Expected a number or an ISO 8601 date after `>=`.'],
            'impossible date' => ['<2023-02-29', 'Expected a number or an ISO 8601 date after `<`.'],
            'operator inside a list' => [['>=5', '10'], 'Operators are not allowed inside a list.'],
        ];
    }

    #[Test]
    #[DataProvider('invalidDynamicValues')]
    public function it_rejects_unreadable_dynamic_operands(mixed $value, string $reason): void
    {
        try {
            FilterValueParser::dynamic($value, 'price', new DateTimeZone('UTC'));
            $this->fail('Expected InvalidFilterValue');
        } catch (InvalidFilterValue $exception) {
            $this->assertSame($reason, $exception->reason);
        }
    }
}

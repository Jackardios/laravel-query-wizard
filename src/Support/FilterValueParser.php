<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Enums\FilterOperator;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;

/**
 * Reads typed values from filter input.
 *
 * Every reader treats a blank value as absent and returns null, and throws
 * InvalidFilterValue (400) naming the expected format for anything else it
 * cannot read. `$key` names the part of a structured value being read, such as
 * the `min` of a range, in that message.
 *
 * @api
 */
final class FilterValueParser
{
    private const NUMBER_PATTERN = '/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)\z/';

    private const INTEGER_PATTERN = '/^[+-]?\d+\z/';

    private const ISO_DATE_PATTERN = '/^(\d{4})-(\d{2})-(\d{2})(?:[Tt ](\d{2}):(\d{2})(?::(\d{2})(?:\.\d{1,9})?)?([Zz]|[+-](\d{2})(?::?(\d{2}))?)?)?\z/';

    private const DYNAMIC_OPERATOR_PATTERN = '/^(>=|<=|!=|<>|>|<)(.*)\z/s';

    /**
     * Null, a string of whitespace, or a list holding only blank values.
     */
    public static function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (! self::isBlank($item)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * true, false, 1, 0, yes, no, on or off, in any letter case.
     */
    public static function boolean(mixed $value, string|FilterInterface $filter, ?string $key = null): ?bool
    {
        if (self::isBlank($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 0 || $value === 1) {
            return $value === 1;
        }

        if (is_string($value)) {
            $boolean = match (strtolower(trim($value))) {
                'true', '1', 'yes', 'on' => true,
                'false', '0', 'no', 'off' => false,
                default => null,
            };

            if ($boolean !== null) {
                return $boolean;
            }
        }

        throw self::invalid($value, $filter, $key, 'a boolean (true, false, 1, 0, yes, no, on or off)');
    }

    /**
     * with, only or without; true and false stand for with and without.
     *
     * @return 'with'|'only'|'without'|null
     */
    public static function trashedMode(mixed $value, string|FilterInterface $filter, ?string $key = null): ?string
    {
        if (self::isBlank($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'with' : 'without';
        }

        if (is_string($value)) {
            $mode = match (strtolower(trim($value))) {
                'with', 'true' => 'with',
                'without', 'false' => 'without',
                'only' => 'only',
                default => null,
            };

            if ($mode !== null) {
                return $mode;
            }
        }

        throw self::invalid($value, $filter, $key, 'one of: with, only, without');
    }

    /**
     * A decimal number: digits with an optional sign and fraction, no exponent.
     *
     * Integers too large for an int are returned as strings, so no precision is lost.
     */
    public static function number(mixed $value, string|FilterInterface $filter, ?string $key = null): int|float|string|null
    {
        if (self::isBlank($value)) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && is_finite($value)) {
            return $value;
        }

        if (is_string($value) && preg_match(self::NUMBER_PATTERN, $number = trim($value)) === 1) {
            if (preg_match(self::INTEGER_PATTERN, $number) !== 1) {
                return (float) $number;
            }

            $number = self::withoutLeadingZeros($number);
            $integer = filter_var($number, FILTER_VALIDATE_INT);

            return $integer !== false ? $integer : ltrim($number, '+');
        }

        throw self::invalid($value, $filter, $key, 'a decimal number');
    }

    /**
     * A date (Y-m-d) or an ISO 8601 date-time, read in the given timezone.
     *
     * A date-time without an offset is taken to be in that timezone; one with an
     * offset (or Z) is converted to it. A date names the whole day, starting at
     * midnight in the timezone.
     */
    public static function isoDate(
        mixed $value,
        string|FilterInterface $filter,
        DateTimeZone $timezone,
        ?string $key = null
    ): ?ParsedDate {
        if (self::isBlank($value)) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return new ParsedDate(DateTimeImmutable::createFromInterface($value)->setTimezone($timezone), false);
        }

        $date = is_string($value) ? self::readIsoDate(trim($value), $timezone) : null;

        if ($date === null) {
            throw self::invalid($value, $filter, $key, 'a date (Y-m-d) or an ISO 8601 date-time');
        }

        return $date;
    }

    /**
     * An ISO date as isoDate() reads it, or any other date PHP can read, such
     * as "yesterday" or "next monday", except a single letter.
     */
    public static function lenientDate(
        mixed $value,
        string|FilterInterface $filter,
        DateTimeZone $timezone,
        ?string $key = null
    ): ?ParsedDate {
        if (self::isBlank($value)) {
            return null;
        }

        if ($value instanceof DateTimeInterface || (is_string($value) && preg_match(self::ISO_DATE_PATTERN, trim($value)) === 1)) {
            return self::isoDate($value, $filter, $timezone, $key);
        }

        if (is_string($value) && preg_match('/^[a-z]\z/i', trim($value)) !== 1) {
            try {
                return new ParsedDate((new DateTimeImmutable(trim($value), $timezone))->setTimezone($timezone), false);
            } catch (Exception) {
            }
        }

        throw self::invalid($value, $filter, $key, 'a date');
    }

    /**
     * Whole seconds since the Unix epoch.
     */
    public static function unixTimestamp(mixed $value, string|FilterInterface $filter, ?string $key = null): ?int
    {
        if (self::isBlank($value)) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match(self::INTEGER_PATTERN, $timestamp = trim($value)) === 1) {
            $integer = filter_var(self::withoutLeadingZeros($timestamp), FILTER_VALIDATE_INT);

            if ($integer !== false) {
                return $integer;
            }
        }

        throw self::invalid($value, $filter, $key, 'a Unix timestamp in whole seconds');
    }

    /**
     * A value that may start with a comparison operator: >=, <=, >, <, != or <>.
     *
     * The operand of >, >=, < and <= is read as a number (see number()) or an
     * ISO date (see isoDate(), returned as a ParsedDate). The operand of != and
     * <> and a value without an operator are returned as sent. An operator with
     * a blank operand is absent, and a list may not hold operators.
     *
     * @return array{0: FilterOperator, 1: mixed}|null
     */
    public static function dynamic(
        mixed $value,
        string|FilterInterface $filter,
        DateTimeZone $timezone,
        ?string $key = null
    ): ?array {
        if (self::isBlank($value)) {
            return null;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item) && preg_match(self::DYNAMIC_OPERATOR_PATTERN, $item) === 1) {
                    throw InvalidFilterValue::make($value, $filter, 'Operators are not allowed inside a list.');
                }
            }

            return [FilterOperator::EQUAL, $value];
        }

        if (! is_string($value) || preg_match(self::DYNAMIC_OPERATOR_PATTERN, $value, $matches) !== 1) {
            return [FilterOperator::EQUAL, $value];
        }

        $operand = $matches[2];

        if (trim($operand) === '') {
            return null;
        }

        $operator = match ($matches[1]) {
            '>=' => FilterOperator::GREATER_THAN_OR_EQUAL,
            '<=' => FilterOperator::LESS_THAN_OR_EQUAL,
            '>' => FilterOperator::GREATER_THAN,
            '<' => FilterOperator::LESS_THAN,
            default => FilterOperator::NOT_EQUAL,
        };

        if ($operator === FilterOperator::NOT_EQUAL) {
            return [$operator, $operand];
        }

        $operand = trim($operand);

        if (preg_match(self::NUMBER_PATTERN, $operand) === 1) {
            return [$operator, self::number($operand, $filter, $key)];
        }

        $date = self::readIsoDate($operand, $timezone);

        if ($date === null) {
            throw self::invalid($value, $filter, $key, "a number or an ISO 8601 date after `{$matches[1]}`");
        }

        return [$operator, $date];
    }

    private static function readIsoDate(string $value, DateTimeZone $timezone): ?ParsedDate
    {
        if (preg_match(self::ISO_DATE_PATTERN, $value, $matches) !== 1) {
            return null;
        }

        [$year, $month, $day] = [(int) $matches[1], (int) $matches[2], (int) $matches[3]];

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        if (! isset($matches[4])) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);

            return $date === false ? null : new ParsedDate($date, true);
        }

        if (
            (int) $matches[4] > 23
            || (int) ($matches[5] ?? 0) > 59
            || (int) ($matches[6] ?? 0) > 59
            || (int) ($matches[8] ?? 0) > 23
            || (int) ($matches[9] ?? 0) > 59
        ) {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value, $timezone);
        } catch (Exception) {
            return null;
        }

        return new ParsedDate($date->setTimezone($timezone), false);
    }

    private static function invalid(mixed $value, string|FilterInterface $filter, ?string $key, string $expected): InvalidFilterValue
    {
        $reason = $key === null ? "Expected {$expected}." : "Expected {$expected} for `{$key}`.";

        return InvalidFilterValue::make($value, $filter, $reason);
    }

    /**
     * FILTER_VALIDATE_INT rejects leading zeros, which a request may carry ('007').
     */
    private static function withoutLeadingZeros(string $integer): string
    {
        return (string) preg_replace('/^([+-]?)0+(?=\d)/', '$1', $integer);
    }
}

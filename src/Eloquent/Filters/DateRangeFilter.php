<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Support\FilterValueParser;

/**
 * Filter by date range (from/to).
 *
 * Expects: ?filter[property][from]=X&filter[property][to]=Y
 *
 * A bound is a date (Y-m-d) or an ISO 8601 date-time; anything else is
 * rejected with a 400 unless lenient() is used. Bounds are read in the
 * application timezone, and a date-time with an offset is converted to it.
 * A date names the whole day, so `to=2024-01-31` matches all of January 31.
 *
 * @extends AbstractRangeFilter<non-empty-list<array{0: string, 1: mixed}>>
 */
final class DateRangeFilter extends AbstractRangeFilter
{
    private const UNIX_TIMESTAMP_FORMAT = 'U';

    protected string $minKey = 'from';

    protected string $maxKey = 'to';

    protected ?string $dateFormat = null;

    protected bool $lenient = false;

    /**
     * Create a new date range filter.
     *
     * @param  string  $property  The column name to filter on
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /**
     * Set the key used for start date in the request.
     *
     * Note: This method mutates the current instance.
     */
    public function fromKey(string $key): static
    {
        $this->minKey = $key;

        return $this;
    }

    /**
     * Set the key used for end date in the request.
     *
     * Note: This method mutates the current instance.
     */
    public function toKey(string $key): static
    {
        $this->maxKey = $key;

        return $this;
    }

    /**
     * Set the format bounds are compared in, for a column that does not hold
     * dates in the database's own format.
     *
     * 'U' compares whole seconds since the Unix epoch and also accepts
     * timestamps in the request (see asUnixTimestamp()).
     *
     * Note: This method mutates the current instance.
     */
    public function dateFormat(string $format): static
    {
        $this->dateFormat = $format;

        return $this;
    }

    /**
     * Compare an integer column holding Unix timestamps; the request may send
     * timestamps as well as dates. Same as dateFormat('U').
     *
     * Note: This method mutates the current instance.
     */
    public function asUnixTimestamp(): static
    {
        return $this->dateFormat(self::UNIX_TIMESTAMP_FORMAT);
    }

    /**
     * Also accept any date PHP can read, such as "yesterday" or "-1 week".
     *
     * Note: This method mutates the current instance.
     */
    public function lenient(): static
    {
        $this->lenient = true;

        return $this;
    }

    public function getType(): string
    {
        return 'date_range';
    }

    /**
     * @return non-empty-list<array{0: string, 1: mixed}>|null
     */
    protected function resolveConstraint(mixed $value): ?array
    {
        $bounds = $this->resolveBounds($value);

        return $bounds === [] ? null : $bounds;
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  non-empty-list<array{0: string, 1: mixed}>  $value  The bound comparisons
     * @return Builder<Model>
     */
    protected function applyOnQuery(Builder $builder, mixed $value, string $column): Builder
    {
        $qualifiedColumn = $builder->qualifyColumn($column);

        foreach ($value as [$operator, $bound]) {
            $builder->where($qualifiedColumn, $operator, $bound);
        }

        return $builder;
    }

    /**
     * @return list<array{0: string, 1: mixed}>
     */
    private function resolveBounds(mixed $value): array
    {
        [$from, $to] = $this->parseRangeValue($value, $this->minKey, $this->maxKey);

        if ($from === null && $to === null) {
            return [];
        }

        $timezone = new DateTimeZone(date_default_timezone_get());

        return array_values(array_filter([
            $this->resolveBound($from, $this->minKey, false, $timezone),
            $this->resolveBound($to, $this->maxKey, true, $timezone),
        ]));
    }

    /**
     * @return array{0: string, 1: mixed}|null
     */
    private function resolveBound(mixed $value, string $key, bool $upper, DateTimeZone $timezone): ?array
    {
        if ($this->dateFormat === self::UNIX_TIMESTAMP_FORMAT && self::looksLikeTimestamp($value)) {
            $timestamp = FilterValueParser::unixTimestamp($value, $this, $key);

            return $timestamp === null ? null : [$upper ? '<=' : '>=', $timestamp];
        }

        $date = $this->lenient
            ? FilterValueParser::lenientDate($value, $this, $timezone, $key)
            : FilterValueParser::isoDate($value, $this, $timezone, $key);

        if ($date === null) {
            return null;
        }

        if ($date->dateOnly && $upper) {
            $nextDay = $date->value->modify('+1 day');

            // 10000-01-01 sorts before every four-digit date as text, so the last day ends at its last second.
            if ((int) $nextDay->format('Y') > 9999 && $this->dateFormat !== self::UNIX_TIMESTAMP_FORMAT) {
                return ['<=', $this->formatBound($date->value->setTime(23, 59, 59), false)];
            }

            return ['<', $this->formatBound($nextDay, true)];
        }

        return [$upper ? '<=' : '>=', $this->formatBound($date->value, $date->dateOnly)];
    }

    private function formatBound(DateTimeImmutable $instant, bool $dateOnly): DateTimeInterface|int|string
    {
        return match (true) {
            $this->dateFormat === self::UNIX_TIMESTAMP_FORMAT => $instant->getTimestamp(),
            $this->dateFormat !== null => $instant->format($this->dateFormat),
            $dateOnly => $instant->format('Y-m-d'),
            default => $instant,
        };
    }

    private static function looksLikeTimestamp(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && preg_match('/^[+-]?\d+\z/', trim($value)) === 1);
    }
}

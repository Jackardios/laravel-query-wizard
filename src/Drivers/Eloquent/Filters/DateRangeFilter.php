<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Filters;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\Concerns\ParsesRangeValues;
use Jackardios\QueryWizard\Filters\AbstractFilter;

class DateRangeFilter extends AbstractFilter
{
    use ParsesRangeValues;

    protected string $fromKey = 'from';
    protected string $toKey = 'to';
    protected ?string $dateFormat = null;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    /**
     * Set custom key names for the date range filter parameters.
     *
     * By default, the filter expects `?filter[property][from]=X&filter[property][to]=Y`.
     * Use this method to change the key names.
     *
     * Note: Filters are immutable. This method returns a new instance.
     *
     * @example Use 'start' and 'end' instead of 'from' and 'to'
     * ```php
     * FilterDefinition::dateRange('created_at')->keys('start', 'end')
     * // Request: ?filter[created_at][start]=2024-01-01&filter[created_at][end]=2024-12-31
     * ```
     *
     * @param string $fromKey The key name for start date (default: 'from')
     * @param string $toKey The key name for end date (default: 'to')
     * @return static New filter instance with custom keys
     */
    public function keys(string $fromKey = 'from', string $toKey = 'to'): static
    {
        $clone = clone $this;
        $clone->fromKey = $fromKey;
        $clone->toKey = $toKey;
        return $clone;
    }

    /**
     * Set the date format for formatting DateTime objects.
     *
     * When the filter value is a DateTimeInterface object (e.g., Carbon instance),
     * this format will be used to convert it to a string for the database query.
     *
     * If not set, DateTime objects are passed directly to the query builder.
     *
     * Note: Filters are immutable. This method returns a new instance.
     *
     * @example Format DateTime objects as date strings
     * ```php
     * FilterDefinition::dateRange('created_at')->dateFormat('Y-m-d')
     * ```
     *
     * @example Include time in format
     * ```php
     * FilterDefinition::dateRange('published_at')->dateFormat('Y-m-d H:i:s')
     * ```
     *
     * @param string|null $dateFormat The date format string (see PHP date() formats)
     * @return static New filter instance with the date format set
     */
    public function dateFormat(?string $dateFormat): static
    {
        $clone = clone $this;
        $clone->dateFormat = $dateFormat;
        return $clone;
    }

    public function getType(): string
    {
        return 'dateRange';
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @param array<string, mixed>|mixed $value
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        $column = $subject->qualifyColumn($this->property);

        [$from, $to] = $this->parseRangeValue($value, $this->fromKey, $this->toKey);

        if ($from !== null) {
            $subject->where($column, '>=', $this->formatDate($from));
        }

        if ($to !== null) {
            $subject->where($column, '<=', $this->formatDate($to));
        }

        return $subject;
    }

    /**
     * @param DateTimeInterface|mixed $value
     */
    protected function formatDate(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface && $this->dateFormat !== null) {
            return $value->format($this->dateFormat);
        }

        return $value;
    }
}

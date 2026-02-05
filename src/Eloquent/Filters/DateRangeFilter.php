<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Eloquent\Filters\Concerns\ParsesRangeValues;
use Jackardios\QueryWizard\Filters\AbstractFilter;

/**
 * Filter by date range (from/to).
 *
 * Expects: ?filter[property][from]=X&filter[property][to]=Y
 */
final class DateRangeFilter extends AbstractFilter
{
    use ParsesRangeValues;

    protected string $fromKey = 'from';
    protected string $toKey = 'to';
    protected ?string $dateFormat = null;

    /**
     * Create a new date range filter.
     *
     * @param string $property The column name to filter on
     * @param string|null $alias Optional alias for URL parameter name
     */
    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    /**
     * Set the key used for start date in the request.
     */
    public function fromKey(string $key): static
    {
        $clone = clone $this;
        $clone->fromKey = $key;
        return $clone;
    }

    /**
     * Set the key used for end date in the request.
     */
    public function toKey(string $key): static
    {
        $clone = clone $this;
        $clone->toKey = $key;
        return $clone;
    }

    /**
     * Set the date format for DateTime values.
     */
    public function dateFormat(string $format): static
    {
        $clone = clone $this;
        $clone->dateFormat = $format;
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

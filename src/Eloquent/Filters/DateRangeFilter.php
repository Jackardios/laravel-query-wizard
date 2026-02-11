<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use DateTimeInterface;

/**
 * Filter by date range (from/to).
 *
 * Expects: ?filter[property][from]=X&filter[property][to]=Y
 */
final class DateRangeFilter extends AbstractRangeFilter
{
    protected string $minKey = 'from';

    protected string $maxKey = 'to';

    protected ?string $dateFormat = null;

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
     * Set the date format for DateTime values.
     *
     * Note: This method mutates the current instance.
     */
    public function dateFormat(string $format): static
    {
        $this->dateFormat = $format;

        return $this;
    }

    public function getType(): string
    {
        return 'date_range';
    }

    protected function normalizeRangeValue(mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (is_string($value) && strtotime($value) === false) {
            return null;
        }

        return $value;
    }

    protected function formatValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface && $this->dateFormat !== null) {
            return $value->format($this->dateFormat);
        }

        return $value;
    }
}

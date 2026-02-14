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

    protected bool $strictDateParsing = false;

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

    /**
     * Enable strict date parsing mode.
     *
     * By default, strtotime() is used which accepts relative dates like "tomorrow", "+1 week".
     * With strict mode, only standard date formats (Y-m-d, Y-m-d H:i:s, ISO 8601) are accepted.
     *
     * Note: This method mutates the current instance.
     */
    public function strict(): static
    {
        $this->strictDateParsing = true;

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

        if (is_string($value)) {
            if ($this->strictDateParsing) {
                return $this->parseStrictDate($value);
            }

            return strtotime($value) === false ? null : $value;
        }

        return null;
    }

    protected function parseStrictDate(string $value): ?string
    {
        $formats = ['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:sP'];

        foreach ($formats as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $value);
            if ($parsed !== false && $parsed->format($format) === $value) {
                return $value;
            }
        }

        return null;
    }

    protected function formatValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface && $this->dateFormat !== null) {
            return $value->format($this->dateFormat);
        }

        return $value;
    }
}

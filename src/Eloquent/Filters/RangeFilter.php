<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

/**
 * Filter by numeric range (min/max).
 *
 * Expects: ?filter[property][min]=X&filter[property][max]=Y
 */
final class RangeFilter extends AbstractRangeFilter
{
    protected string $minKey = 'min';

    protected string $maxKey = 'max';

    /**
     * Create a new range filter.
     *
     * @param  string  $property  The column name to filter on
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    public function getType(): string
    {
        return 'range';
    }

    /**
     * Reject non-numeric values for range boundaries.
     */
    protected function normalizeRangeValue(mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Jackardios\QueryWizard\Support\FilterValueParser;

/**
 * Filter by numeric range (min/max).
 *
 * Expects: ?filter[property][min]=X&filter[property][max]=Y
 *
 * A bound that is not a decimal number is rejected with a 400.
 */
final class RangeFilter extends AbstractRangeFilter
{
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
     * Read a bound as a decimal number.
     */
    protected function normalizeRangeValue(mixed $value, ?string $key = null): mixed
    {
        return FilterValueParser::number($value, $this, $key);
    }
}

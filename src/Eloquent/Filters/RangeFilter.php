<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Support\FilterValueParser;
use Jackardios\QueryWizard\Support\NumericComparison;

/**
 * Filter by numeric range (min/max).
 *
 * Expects: ?filter[property][min]=X&filter[property][max]=Y
 *
 * A bound that is not a decimal number is rejected with a 400.
 *
 * @extends AbstractRangeFilter<array{0: mixed, 1: mixed}>
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

    /**
     * @return array{0: mixed, 1: mixed}|null The minimum and maximum, at least one of them set
     */
    protected function resolveConstraint(mixed $value): ?array
    {
        $range = $this->parseRangeValue($value, $this->minKey, $this->maxKey);

        return $range === [null, null] ? null : $range;
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  array{0: mixed, 1: mixed}  $value  The minimum and maximum
     * @return Builder<Model>
     */
    protected function applyOnQuery(Builder $builder, mixed $value, string $column): Builder
    {
        $qualifiedColumn = $builder->qualifyColumn($column);
        [$min, $max] = $value;

        foreach (['>=' => $min, '<=' => $max] as $operator => $bound) {
            match (true) {
                is_int($bound), is_float($bound), is_string($bound) => NumericComparison::where($builder, $qualifiedColumn, $operator, $bound),
                $bound !== null => $builder->where($qualifiedColumn, $operator, $bound),
                default => null,
            };
        }

        return $builder;
    }
}

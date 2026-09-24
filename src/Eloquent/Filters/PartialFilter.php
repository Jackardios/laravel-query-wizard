<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\Support\FilterValueParser;
use Jackardios\QueryWizard\Support\LikeClause;

/**
 * Filter by partial match (LIKE %value%).
 *
 * Case-insensitive search that matches any part of the column value.
 * If all values in array are empty strings or null, the filter
 * silently returns without modifying the query. A value that is not text or
 * a number, such as a boolean, is rejected with a 400.
 *
 * The request value is a search phrase, so it is not split by the filters
 * separator: `?filter[name]=Moscow, Russia` matches that whole phrase. Pass
 * a list (`?filter[name][]=a&filter[name][]=b`) to match any of several
 * phrases, or call withValueSplitting() to restore separator splitting.
 */
final class PartialFilter extends ExactFilter
{
    protected bool $splitValues = false;

    /**
     * Create a new partial filter.
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
        return 'partial';
    }

    protected function hasEffectiveConstraint(mixed $value): bool
    {
        if (is_array($value)) {
            return $this->searchableValues($value) !== [];
        }

        $this->searchableValue($value);

        return true;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<string|int|float>
     */
    private function searchableValues(array $values): array
    {
        $searchable = [];

        foreach ($values as $value) {
            if (! FilterValueParser::isBlank($value)) {
                $searchable[] = $this->searchableValue($value);
            }
        }

        return $searchable;
    }

    private function searchableValue(mixed $value): string|int|float
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        throw InvalidFilterValue::make($value, $this, 'Expected text.');
    }

    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    protected function applyOnQuery(Builder $builder, mixed $value, string $column): Builder
    {
        $sql = LikeClause::for($builder, $column, lowercase: true);

        if (is_array($value)) {
            $filteredValues = $this->searchableValues($value);
            if (count($filteredValues) === 0) {
                return $builder;
            }

            $builder->where(function (Builder $query) use ($filteredValues, $sql): void {
                foreach ($filteredValues as $partialValue) {
                    $query->whereRaw($sql, [LikeClause::containing((string) $partialValue, lowercase: true)], 'or');
                }
            });

            return $builder;
        }

        $builder->whereRaw($sql, [LikeClause::containing((string) $this->searchableValue($value), lowercase: true)]);

        return $builder;
    }
}

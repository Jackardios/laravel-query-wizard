<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Support\LikeClause;

/**
 * Filter by partial match (LIKE %value%).
 *
 * Case-insensitive search that matches any part of the column value.
 * If all values in array are empty strings or null, the filter
 * silently returns without modifying the query.
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
        return ! is_array($value) || $this->searchableValues($value) !== [];
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private function searchableValues(array $values): array
    {
        return array_filter($values, static fn ($v): bool => $v !== '' && $v !== null);
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

        $builder->whereRaw($sql, [LikeClause::containing((string) $value, lowercase: true)]);

        return $builder;
    }
}

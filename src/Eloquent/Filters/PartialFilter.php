<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Filter by partial match (LIKE %value%).
 *
 * Case-insensitive search that matches any part of the column value.
 * If all values in array are empty strings or null, the filter
 * silently returns without modifying the query.
 */
final class PartialFilter extends ExactFilter
{
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

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyOnQuery(Builder $builder, mixed $value, string $column): Builder
    {
        $wrappedColumn = $builder
            ->getQuery()
            ->getGrammar()
            ->wrap($builder->qualifyColumn($column));

        $sql = "LOWER({$wrappedColumn}) LIKE ? ESCAPE '\\'";

        if (is_array($value)) {
            $filteredValues = array_filter($value, static fn ($v): bool => $v !== '' && $v !== null);
            if (count($filteredValues) === 0) {
                return $builder;
            }

            $builder->where(function (Builder $query) use ($filteredValues, $sql): void {
                foreach ($filteredValues as $partialValue) {
                    $partialValue = mb_strtolower((string) $partialValue, 'UTF8');
                    $query->orWhereRaw($sql, ['%'.$this->escapeLikeValue($partialValue).'%']);
                }
            });

            return $builder;
        }

        $value = mb_strtolower((string) $value, 'UTF8');
        $builder->whereRaw($sql, ['%'.$this->escapeLikeValue($value).'%']);

        return $builder;
    }

    /**
     * Escape LIKE metacharacters so they are treated as literals.
     */
    private function escapeLikeValue(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }
}

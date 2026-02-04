<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Filter by partial match (LIKE %value%).
 *
 * Note: If all values in array are empty strings or null, the filter
 * silently returns without modifying the query. This is intentional
 * to handle empty form submissions gracefully.
 */
class PartialFilter extends ExactFilter
{
    public function getType(): string
    {
        return 'partial';
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyOnQuery(Builder $builder, mixed $value, string $column): Builder
    {
        $wrappedColumn = $builder
            ->getQuery()
            ->getGrammar()
            ->wrap($builder->qualifyColumn($column));

        $sql = "LOWER({$wrappedColumn}) LIKE ?";

        if (is_array($value)) {
            $filteredValues = array_filter($value, static fn($v): bool => $v !== '' && $v !== null);
            if (count($filteredValues) === 0) {
                return $builder;
            }

            $builder->where(function (Builder $query) use ($filteredValues, $sql): void {
                foreach ($filteredValues as $partialValue) {
                    $partialValue = mb_strtolower((string) $partialValue, 'UTF8');
                    $query->orWhereRaw($sql, ["%{$partialValue}%"]);
                }
            });

            return $builder;
        }

        $value = mb_strtolower((string) $value, 'UTF8');
        $builder->whereRaw($sql, ["%{$value}%"]);

        return $builder;
    }
}

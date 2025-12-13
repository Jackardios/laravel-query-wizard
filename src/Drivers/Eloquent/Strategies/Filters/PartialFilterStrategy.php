<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters;

use Illuminate\Database\Eloquent\Builder;

class PartialFilterStrategy extends ExactFilterStrategy
{
    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $queryBuilder
     * @param array|string|mixed $value
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyOnQuery(Builder $queryBuilder, mixed $value, string $propertyName): Builder
    {
        $wrappedPropertyName = $queryBuilder
            ->getQuery()
            ->getGrammar()
            ->wrap($queryBuilder->qualifyColumn($propertyName));

        $sql = "LOWER({$wrappedPropertyName}) LIKE ?";

        if (is_array($value)) {
            $filteredValues = array_filter($value, static fn($v): bool => $v !== '' && $v !== null);
            if (count($filteredValues) === 0) {
                return $queryBuilder;
            }

            $queryBuilder->where(function (Builder $query) use ($filteredValues, $sql): void {
                foreach ($filteredValues as $partialValue) {
                    $partialValue = mb_strtolower((string) $partialValue, 'UTF8');
                    $query->orWhereRaw($sql, ["%{$partialValue}%"]);
                }
            });

            return $queryBuilder;
        }

        $value = mb_strtolower((string) $value, 'UTF8');
        $queryBuilder->whereRaw($sql, ["%{$value}%"]);

        return $queryBuilder;
    }
}

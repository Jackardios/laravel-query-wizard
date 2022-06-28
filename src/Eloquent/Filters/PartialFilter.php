<?php

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;

class PartialFilter extends ExactFilter
{
    protected function applyOnQuery(Builder $queryBuilder, $value, string $propertyName): void
    {
        $wrappedPropertyName = $queryBuilder
            ->getQuery()
            ->getGrammar()
            ->wrap($queryBuilder->qualifyColumn($propertyName));

        $sql = "LOWER({$wrappedPropertyName}) LIKE ?";

        if (is_array($value)) {
            if (count(array_filter($value, 'strlen')) === 0) {
                return;
            }

            $queryBuilder->where(function (Builder $query) use ($value, $sql) {
                foreach (array_filter($value, 'strlen') as $partialValue) {
                    $partialValue = mb_strtolower($partialValue, 'UTF8');

                    $query->orWhereRaw($sql, ["%{$partialValue}%"]);
                }
            });

            return;
        }

        $value = mb_strtolower($value, 'UTF8');

        $queryBuilder->whereRaw($sql, ["%{$value}%"]);
    }
}

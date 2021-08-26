<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;

class FiltersPartial extends FiltersExact
{
    protected function handleForQuery($query, $value, string $propertyName): void
    {
        if ($this->withRelationConstraint && $this->isRelationProperty($query, $propertyName)) {
            $this->addRelationConstraint($query, $value, $propertyName);

            return;
        }

        $wrappedPropertyName = $query->getQuery()->getGrammar()->wrap($query->qualifyColumn($propertyName));

        $sql = "LOWER({$wrappedPropertyName}) LIKE ?";

        if (is_array($value)) {
            if (count(array_filter($value, 'strlen')) === 0) {
                return;
            }

            $query->where(function (Builder $query) use ($value, $sql) {
                foreach (array_filter($value, 'strlen') as $partialValue) {
                    $partialValue = mb_strtolower($partialValue, 'UTF8');

                    $query->orWhereRaw($sql, ["%{$partialValue}%"]);
                }
            });

            return;
        }

        $value = mb_strtolower($value, 'UTF8');

        $query->whereRaw($sql, ["%{$value}%"]);
    }
}

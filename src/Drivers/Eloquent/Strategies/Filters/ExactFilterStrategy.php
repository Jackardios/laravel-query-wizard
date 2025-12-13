<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\FilterStrategyInterface;

class ExactFilterStrategy implements FilterStrategyInterface
{
    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, FilterDefinitionInterface $filter, mixed $value): mixed
    {
        $propertyName = $filter->getProperty();
        $addRelationConstraint = $filter->getOption('withRelationConstraint', true);

        if ($addRelationConstraint && $this->isRelationProperty($subject, $propertyName)) {
            return $this->applyRelationFilter($subject, $propertyName, $value);
        }

        return $this->applyOnQuery($subject, $value, $propertyName);
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $queryBuilder
     * @param array|mixed $value
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyOnQuery(Builder $queryBuilder, mixed $value, string $propertyName): Builder
    {
        if (is_array($value)) {
            $queryBuilder->whereIn($queryBuilder->qualifyColumn($propertyName), $value);

            return $queryBuilder;
        }

        $queryBuilder->where($queryBuilder->qualifyColumn($propertyName), '=', $value);

        return $queryBuilder;
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $queryBuilder
     */
    protected function isRelationProperty(Builder $queryBuilder, string $propertyName): bool
    {
        if (!Str::contains($propertyName, '.')) {
            return false;
        }

        $firstRelationship = explode('.', $propertyName)[0];

        if (!method_exists($queryBuilder->getModel(), $firstRelationship)) {
            return false;
        }

        return is_a($queryBuilder->getModel()->{$firstRelationship}(), Relation::class);
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $queryBuilder
     * @param array|mixed $value
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyRelationFilter(Builder $queryBuilder, string $propertyName, mixed $value): Builder
    {
        $relation = Str::beforeLast($propertyName, '.');
        $propertyName = Str::afterLast($propertyName, '.');

        $queryBuilder->whereHas($relation, function (Builder $query) use ($value, $propertyName): void {
            $this->applyOnQuery($query, $value, $propertyName);
        });

        return $queryBuilder;
    }
}

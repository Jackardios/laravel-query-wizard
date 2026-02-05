<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * Trait for filters that support automatic relation filtering.
 *
 * When a filter property uses dot notation (e.g., 'posts.status'), this trait
 * automatically applies the filter within a whereHas clause for the relation.
 */
trait HandlesRelationFiltering
{
    protected bool $withRelationConstraint = true;

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     */
    protected function isRelationProperty(Builder $builder, string $property): bool
    {
        if (! Str::contains($property, '.')) {
            return false;
        }

        $firstRelation = explode('.', $property)[0];
        if (! method_exists($builder->getModel(), $firstRelation)) {
            return false;
        }

        try {
            $relation = $builder->getModel()->{$firstRelation}();

            return $relation instanceof Relation;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyRelationFilter(Builder $builder, string $property, mixed $value): Builder
    {
        $relation = Str::beforeLast($property, '.');
        $column = Str::afterLast($property, '.');

        $builder->whereHas($relation, function (Builder $query) use ($value, $column): void {
            $this->applyOnQuery($query, $value, $column);
        });

        return $builder;
    }

    /**
     * Apply the filter logic on a specific query and column.
     * This method is called both for direct filtering and for relation filtering (inside whereHas).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder  The query builder
     * @param  mixed  $value  The filter value (can be scalar, array, or null)
     * @param  string  $column  The column name to filter on
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    abstract protected function applyOnQuery(Builder $builder, mixed $value, string $column): Builder;
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Filters\Concerns;

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
     * Enable or disable automatic relation constraint (whereHas) for dot notation properties.
     *
     * When enabled (default), a property like 'posts.status' will automatically
     * be wrapped in a whereHas('posts', ...) clause.
     *
     * When disabled, the dot notation is treated as a literal column name,
     * which may be useful for JSON columns or other non-relation scenarios.
     *
     * Note: Filters are immutable. This method returns a new instance.
     *
     * @example Enable relation filtering (default)
     * ```php
     * FilterDefinition::exact('posts.status')
     * // Produces: whereHas('posts', fn($q) => $q->where('status', $value))
     * ```
     *
     * @example Disable relation filtering
     * ```php
     * FilterDefinition::exact('data.field')->withRelationConstraint(false)
     * // Treats 'data.field' as literal column name
     * ```
     *
     * @param bool $value Whether to apply relation constraints (default: true)
     * @return static New filter instance with the setting applied
     */
    public function withRelationConstraint(bool $value = true): static
    {
        $clone = clone $this;
        $clone->withRelationConstraint = $value;
        return $clone;
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $builder
     */
    protected function isRelationProperty(Builder $builder, string $property): bool
    {
        if (!Str::contains($property, '.')) {
            return false;
        }

        $firstRelation = explode('.', $property)[0];
        if (!method_exists($builder->getModel(), $firstRelation)) {
            return false;
        }

        return is_a($builder->getModel()->{$firstRelation}(), Relation::class);
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $builder
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
     * @param Builder<\Illuminate\Database\Eloquent\Model> $builder The query builder
     * @param mixed $value The filter value (can be scalar, array, or null)
     * @param string $column The column name to filter on
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    abstract protected function applyOnQuery(Builder $builder, mixed $value, string $column): Builder;
}

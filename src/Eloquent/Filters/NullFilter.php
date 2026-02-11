<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Eloquent\Filters\Concerns\HandlesRelationFiltering;
use Jackardios\QueryWizard\Filters\AbstractFilter;

/**
 * Filter by NULL/NOT NULL values.
 *
 * Supports dot notation for relation filtering (e.g., 'posts.deleted_at').
 *
 * By default:
 * - Truthy value → WHERE column IS NULL
 * - Falsy value → WHERE column IS NOT NULL
 *
 * When invertLogic is true, the behavior is reversed.
 */
final class NullFilter extends AbstractFilter
{
    use HandlesRelationFiltering;

    protected bool $invertLogic = false;

    /**
     * Create a new null filter.
     *
     * @param  string  $property  The column name to check for NULL
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /**
     * Invert the filter logic.
     * When inverted: truthy → NOT NULL, falsy → NULL
     *
     * Note: This method mutates the current instance.
     */
    public function withInvertedLogic(): static
    {
        $this->invertLogic = true;

        return $this;
    }

    /**
     * Use normal filter logic (default).
     * Normal: truthy → NULL, falsy → NOT NULL
     *
     * Note: This method mutates the current instance.
     */
    public function withoutInvertedLogic(): static
    {
        $this->invertLogic = false;

        return $this;
    }

    public function getType(): string
    {
        return 'null';
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $subject
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        if ($this->withRelationConstraint && $this->isRelationProperty($subject, $this->property)) {
            return $this->applyRelationFilter($subject, $this->property, $value);
        }

        return $this->applyOnQuery($subject, $value, $this->property);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyOnQuery(Builder $builder, mixed $value, string $column): Builder
    {
        $qualifiedColumn = $builder->qualifyColumn($column);

        $isTruthy = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($isTruthy === null) {
            return $builder;
        }

        $shouldBeNull = $this->invertLogic ? ! $isTruthy : $isTruthy;

        if ($shouldBeNull) {
            $builder->whereNull($qualifiedColumn);
        } else {
            $builder->whereNotNull($qualifiedColumn);
        }

        return $builder;
    }
}

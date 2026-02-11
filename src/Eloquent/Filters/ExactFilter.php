<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Eloquent\Filters\Concerns\HandlesRelationFiltering;
use Jackardios\QueryWizard\Filters\AbstractFilter;

/**
 * Filter by exact value match.
 *
 * Supports array values (uses whereIn) and dot notation for relation filtering.
 *
 * @phpstan-consistent-constructor
 */
class ExactFilter extends AbstractFilter
{
    use HandlesRelationFiltering;

    /**
     * Create a new exact filter.
     *
     * @param  string  $property  The column name to filter on
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'exact';
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

        if (is_array($value)) {
            if (empty($value)) {
                return $builder;
            }

            $builder->whereIn($qualifiedColumn, $value);

            return $builder;
        }

        $builder->where($qualifiedColumn, '=', $value);

        return $builder;
    }
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\Concerns\HandlesRelationFiltering;
use Jackardios\QueryWizard\Filters\AbstractFilter;

class ExactFilter extends AbstractFilter
{
    use HandlesRelationFiltering;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'exact';
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
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
     * @param Builder<\Illuminate\Database\Eloquent\Model> $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyOnQuery(Builder $builder, mixed $value, string $column): Builder
    {
        $qualifiedColumn = $builder->qualifyColumn($column);

        if (is_array($value)) {
            $builder->whereIn($qualifiedColumn, $value);

            return $builder;
        }

        $builder->where($qualifiedColumn, '=', $value);

        return $builder;
    }
}

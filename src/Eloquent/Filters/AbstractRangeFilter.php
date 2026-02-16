<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Eloquent\Filters\Concerns\HandlesRelationFiltering;
use Jackardios\QueryWizard\Eloquent\Filters\Concerns\ParsesRangeValues;
use Jackardios\QueryWizard\Filters\AbstractFilter;

/**
 * Base class for range-based filters (numeric, date, etc.).
 *
 * Supports dot notation for relation filtering (e.g., 'posts.created_at').
 *
 * Expects: ?filter[property][minKey]=X&filter[property][maxKey]=Y
 */
abstract class AbstractRangeFilter extends AbstractFilter
{
    use HandlesRelationFiltering;
    use ParsesRangeValues;

    protected string $minKey = 'min';

    protected string $maxKey = 'max';

    /**
     * Set the key used for minimum value in the request.
     *
     * Note: This method mutates the current instance.
     */
    public function minKey(string $key): static
    {
        $this->minKey = $key;

        return $this;
    }

    /**
     * Set the key used for maximum value in the request.
     *
     * Note: This method mutates the current instance.
     */
    public function maxKey(string $key): static
    {
        $this->maxKey = $key;

        return $this;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $subject
     * @param  array<string, mixed>|mixed  $value
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
     * @param  array<string, mixed>|mixed  $value
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyOnQuery(Builder $builder, mixed $value, string $column): Builder
    {
        $qualifiedColumn = $builder->qualifyColumn($column);

        [$min, $max] = $this->parseRangeValue($value, $this->minKey, $this->maxKey);

        if ($min !== null) {
            $builder->where($qualifiedColumn, '>=', $this->formatValue($min));
        }

        if ($max !== null) {
            $builder->where($qualifiedColumn, '<=', $this->formatValue($max));
        }

        return $builder;
    }

    /**
     * Format the value before applying to query.
     *
     * Override this method to customize value formatting (e.g., date formatting).
     */
    protected function formatValue(mixed $value): mixed
    {
        return $value;
    }
}

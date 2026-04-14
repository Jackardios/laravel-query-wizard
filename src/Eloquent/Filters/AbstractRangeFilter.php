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

    public function validateValueShape(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            return $this->invalidRangeValueShapeMessage();
        }

        if (array_is_list($value)) {
            if (count($value) < 2) {
                return $this->invalidRangeValueShapeMessage();
            }

            foreach ($value as $boundaryValue) {
                if (is_array($boundaryValue)) {
                    return $this->invalidRangeValueShapeMessage();
                }
            }

            return null;
        }

        $hasBoundaryKey = false;

        foreach ($value as $key => $boundaryValue) {
            if ($key === $this->minKey || $key === $this->maxKey) {
                $hasBoundaryKey = true;
            }

            if (is_array($boundaryValue)) {
                return $this->invalidRangeValueShapeMessage();
            }
        }

        return $hasBoundaryKey ? null : $this->invalidRangeValueShapeMessage();
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

    protected function invalidRangeValueShapeMessage(): string
    {
        return "Filter `{$this->getName()}` expects an array with `{$this->minKey}`/`{$this->maxKey}` keys or a flat list with at least two values.";
    }
}

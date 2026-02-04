<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\Concerns\ParsesRangeValues;
use Jackardios\QueryWizard\Filters\AbstractFilter;

class RangeFilter extends AbstractFilter
{
    use ParsesRangeValues;

    protected string $minKey = 'min';
    protected string $maxKey = 'max';

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    /**
     * Set custom key names for the range filter parameters.
     *
     * By default, the filter expects `?filter[property][min]=X&filter[property][max]=Y`.
     * Use this method to change the key names.
     *
     * Note: Filters are immutable. This method returns a new instance.
     *
     * @example Use 'from' and 'to' instead of 'min' and 'max'
     * ```php
     * FilterDefinition::range('price')->keys('from', 'to')
     * // Request: ?filter[price][from]=100&filter[price][to]=500
     * ```
     *
     * @param string $minKey The key name for minimum value (default: 'min')
     * @param string $maxKey The key name for maximum value (default: 'max')
     * @return static New filter instance with custom keys
     */
    public function keys(string $minKey = 'min', string $maxKey = 'max'): static
    {
        $clone = clone $this;
        $clone->minKey = $minKey;
        $clone->maxKey = $maxKey;
        return $clone;
    }

    public function getType(): string
    {
        return 'range';
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @param array<string, mixed>|mixed $value
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        $column = $subject->qualifyColumn($this->property);

        [$min, $max] = $this->parseRangeValue($value, $this->minKey, $this->maxKey);

        if ($min !== null) {
            $subject->where($column, '>=', $min);
        }

        if ($max !== null) {
            $subject->where($column, '<=', $max);
        }

        return $subject;
    }
}

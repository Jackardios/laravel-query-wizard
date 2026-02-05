<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Eloquent\Filters\Concerns\ParsesRangeValues;
use Jackardios\QueryWizard\Filters\AbstractFilter;

/**
 * Filter by numeric range (min/max).
 *
 * Expects: ?filter[property][min]=X&filter[property][max]=Y
 */
final class RangeFilter extends AbstractFilter
{
    use ParsesRangeValues;

    protected string $minKey = 'min';

    protected string $maxKey = 'max';

    /**
     * Create a new range filter.
     *
     * @param  string  $property  The column name to filter on
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

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

    public function getType(): string
    {
        return 'range';
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $subject
     * @param  array<string, mixed>|mixed  $value
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

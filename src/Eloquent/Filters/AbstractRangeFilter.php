<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Eloquent\Filters\Concerns\ParsesRangeValues;
use Jackardios\QueryWizard\Filters\AbstractFilter;

/**
 * Base class for range-based filters (numeric, date, etc.).
 *
 * Expects: ?filter[property][minKey]=X&filter[property][maxKey]=Y
 */
abstract class AbstractRangeFilter extends AbstractFilter
{
    use ParsesRangeValues;

    protected string $minKey;

    protected string $maxKey;

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
        $column = $subject->qualifyColumn($this->property);

        [$min, $max] = $this->parseRangeValue($value, $this->minKey, $this->maxKey);

        if ($min !== null) {
            $subject->where($column, '>=', $this->formatValue($min));
        }

        if ($max !== null) {
            $subject->where($column, '<=', $this->formatValue($max));
        }

        return $subject;
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

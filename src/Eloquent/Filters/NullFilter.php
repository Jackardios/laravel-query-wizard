<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Filters\AbstractFilter;

/**
 * Filter by NULL/NOT NULL values.
 *
 * By default:
 * - Truthy value → WHERE column IS NULL
 * - Falsy value → WHERE column IS NOT NULL
 *
 * When invertLogic is true, the behavior is reversed.
 */
final class NullFilter extends AbstractFilter
{
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
        $column = $subject->qualifyColumn($this->property);

        // Use FILTER_NULL_ON_FAILURE to properly handle invalid values
        $isTruthy = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        $shouldBeNull = $this->invertLogic ? ! $isTruthy : $isTruthy;

        if ($shouldBeNull) {
            $subject->whereNull($column);
        } else {
            $subject->whereNotNull($column);
        }

        return $subject;
    }
}

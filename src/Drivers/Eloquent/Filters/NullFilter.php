<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Filters\AbstractFilter;

class NullFilter extends AbstractFilter
{
    protected bool $invertLogic = false;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    /**
     * Invert the null check logic.
     *
     * By default:
     * - Truthy value (`true`, `"1"`, `"yes"`) → WHERE column IS NULL
     * - Falsy value (`false`, `"0"`, `"no"`) → WHERE column IS NOT NULL
     *
     * When inverted:
     * - Truthy value → WHERE column IS NOT NULL
     * - Falsy value → WHERE column IS NULL
     *
     * This is useful when you want to check for existence rather than absence.
     *
     * Note: Filters are immutable. This method returns a new instance.
     *
     * @example Check for verified users (verified_at IS NOT NULL)
     * ```php
     * FilterDefinition::null('verified_at')->invertLogic()
     * // ?filter[verified_at]=true → WHERE verified_at IS NOT NULL
     * // ?filter[verified_at]=false → WHERE verified_at IS NULL
     * ```
     *
     * @param bool $value Whether to invert the logic (default: true)
     * @return static New filter instance with inverted logic
     */
    public function invertLogic(bool $value = true): static
    {
        $clone = clone $this;
        $clone->invertLogic = $value;
        return $clone;
    }

    public function getType(): string
    {
        return 'null';
    }

    /**
     * Apply null filter to the query.
     *
     * Value interpretation:
     * - true, "true", "1", "yes", "on" → whereNull (or whereNotNull if invertLogic)
     * - false, "false", "0", "no", "off", "" → whereNotNull (or whereNull if invertLogic)
     * - Any other value (e.g., "invalid") → treated as false
     *
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        $column = $subject->qualifyColumn($this->property);

        // Use FILTER_NULL_ON_FAILURE to properly handle invalid values
        // Without it, filter_var returns null for invalid inputs which would be treated as falsy
        $isTruthy = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        $shouldBeNull = $this->invertLogic ? !$isTruthy : $isTruthy;

        if ($shouldBeNull) {
            $subject->whereNull($column);
        } else {
            $subject->whereNotNull($column);
        }

        return $subject;
    }
}

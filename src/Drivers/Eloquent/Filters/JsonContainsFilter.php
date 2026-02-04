<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Filters\AbstractFilter;

class JsonContainsFilter extends AbstractFilter
{
    protected bool $matchAll = true;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    /**
     * Set whether all values must match (AND) or any value can match (OR).
     *
     * By default (matchAll: true), when filtering with multiple values,
     * all values must be present in the JSON column (AND logic).
     *
     * When set to false, any of the values can match (OR logic).
     *
     * Note: Filters are immutable. This method returns a new instance.
     *
     * @example Match ALL values (default behavior)
     * ```php
     * FilterDefinition::jsonContains('tags')->matchAll(true)
     * // ?filter[tags]=php,laravel → JSON contains BOTH "php" AND "laravel"
     * ```
     *
     * @example Match ANY value
     * ```php
     * FilterDefinition::jsonContains('tags')->matchAll(false)
     * // ?filter[tags]=php,laravel → JSON contains "php" OR "laravel"
     * ```
     *
     * @param bool $value Whether all values must match (default: true)
     * @return static New filter instance with the match mode set
     */
    public function matchAll(bool $value = true): static
    {
        $clone = clone $this;
        $clone->matchAll = $value;
        return $clone;
    }

    public function getType(): string
    {
        return 'jsonContains';
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @param array|mixed $value
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        $column = $this->resolveJsonColumn($this->property);
        $values = is_array($value) ? $value : [$value];

        if ($this->matchAll) {
            foreach ($values as $val) {
                $subject->whereJsonContains($column, $val);
            }
        } else {
            $subject->where(function (Builder $query) use ($column, $values): void {
                foreach ($values as $val) {
                    $query->orWhereJsonContains($column, $val);
                }
            });
        }

        return $subject;
    }

    /**
     * Convert dot notation to JSON arrow notation.
     * e.g., 'meta.roles' becomes 'meta->roles'
     */
    protected function resolveJsonColumn(string $propertyName): string
    {
        if (!str_contains($propertyName, '.')) {
            return $propertyName;
        }

        $parts = explode('.', $propertyName);
        $column = array_shift($parts);

        return $column . '->' . implode('->', $parts);
    }
}

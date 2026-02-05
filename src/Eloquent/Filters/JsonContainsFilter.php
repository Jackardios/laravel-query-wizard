<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Filters\AbstractFilter;

/**
 * Filter by JSON column containment.
 *
 * By default, all values must match (AND logic).
 * Set matchAll to false for OR logic.
 */
final class JsonContainsFilter extends AbstractFilter
{
    protected bool $matchAll = true;

    /**
     * Create a new JSON contains filter.
     *
     * @param string $property The JSON column name (dot notation for nested paths)
     * @param string|null $alias Optional alias for URL parameter name
     */
    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    /**
     * Set whether all values must match (AND) or any value (OR).
     */
    public function matchAll(bool $value = true): static
    {
        $clone = clone $this;
        $clone->matchAll = $value;
        return $clone;
    }

    /**
     * Use OR logic instead of AND.
     */
    public function matchAny(): static
    {
        return $this->matchAll(false);
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

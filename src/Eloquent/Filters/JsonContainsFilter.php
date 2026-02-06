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
 *
 * Note: Dot notation in the column name is always interpreted as JSON path
 * (e.g., "data.tags" becomes "data->tags"). Relation-based filtering
 * (e.g., "relation.jsonColumn") is not supported for this filter type.
 */
final class JsonContainsFilter extends AbstractFilter
{
    protected bool $matchAll = true;

    /**
     * Create a new JSON contains filter.
     *
     * @param  string  $property  The JSON column name (dot notation for nested paths)
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /**
     * Require all values to match (AND logic, default).
     *
     * Note: This method mutates the current instance.
     */
    public function matchAll(): static
    {
        $this->matchAll = true;

        return $this;
    }

    /**
     * Require any value to match (OR logic).
     *
     * Note: This method mutates the current instance.
     */
    public function matchAny(): static
    {
        $this->matchAll = false;

        return $this;
    }

    public function getType(): string
    {
        return 'json_contains';
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $subject
     * @param  array|mixed  $value
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        $column = $this->resolveQualifiedJsonColumn($subject, $this->property);
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
     * Qualify base column and convert dot notation to JSON arrow notation.
     * e.g., 'meta.roles' becomes 'table.meta->roles'
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $subject
     */
    protected function resolveQualifiedJsonColumn(mixed $subject, string $propertyName): string
    {
        if (! str_contains($propertyName, '.')) {
            return $subject->qualifyColumn($propertyName);
        }

        $parts = explode('.', $propertyName);
        $baseColumn = array_shift($parts);
        $qualifiedBase = $subject->qualifyColumn($baseColumn);

        return $qualifiedBase.'->'.implode('->', $parts);
    }
}

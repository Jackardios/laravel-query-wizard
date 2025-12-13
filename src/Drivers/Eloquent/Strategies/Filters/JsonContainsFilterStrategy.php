<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\FilterStrategyInterface;

class JsonContainsFilterStrategy implements FilterStrategyInterface
{
    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @param array|mixed $value
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, FilterDefinitionInterface $filter, mixed $value): mixed
    {
        $column = $this->resolveJsonColumn($filter->getProperty());
        $values = is_array($value) ? $value : [$value];
        $matchAll = $filter->getOption('matchAll', true);

        if ($matchAll) {
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

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Eloquent\Filters\Concerns\HandlesRelationFiltering;
use Jackardios\QueryWizard\Enums\FilterOperator;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\Filters\AbstractFilter;

/**
 * Filter with configurable SQL operators.
 *
 * Supports static operators (=, !=, >, >=, <, <=, LIKE, NOT LIKE) or dynamic
 * operator parsing from the filter value itself.
 *
 * @phpstan-consistent-constructor
 */
class OperatorFilter extends AbstractFilter
{
    use HandlesRelationFiltering;

    protected FilterOperator $operator;

    public function __construct(string $property, ?string $alias = null, FilterOperator $operator = FilterOperator::EQUAL)
    {
        parent::__construct($property, $alias);
        $this->operator = $operator;
    }

    /**
     * @param  string  $property  The column name to filter on
     * @param  string|null  $alias  Optional alias for URL parameter name
     * @param  FilterOperator  $operator  The comparison operator (default: EQUAL)
     */
    public static function make(string $property, ?string $alias = null, FilterOperator $operator = FilterOperator::EQUAL): static
    {
        return new static($property, $alias, $operator);
    }

    public function getType(): string
    {
        return 'operator';
    }

    public function getOperator(): FilterOperator
    {
        return $this->operator;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $subject
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        if ($this->withRelationConstraint && $this->isRelationProperty($subject, $this->property)) {
            return $this->applyRelationFilter($subject, $this->property, $value);
        }

        return $this->applyOnQuery($subject, $value, $this->property);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyOnQuery(Builder $builder, mixed $value, string $column): Builder
    {
        $qualifiedColumn = $builder->qualifyColumn($column);
        $operator = $this->operator;
        $actualValue = $value;

        if ($operator === FilterOperator::DYNAMIC) {
            [$operator, $actualValue] = $this->parseDynamicOperator($value);

            if ($operator === null) {
                return $builder;
            }
        }

        if (is_array($actualValue)) {
            return $this->applyArrayValue($builder, $qualifiedColumn, $operator, $actualValue);
        }

        if ($operator === FilterOperator::LIKE || $operator === FilterOperator::NOT_LIKE) {
            $actualValue = '%'.$actualValue.'%';
        }

        $builder->where($qualifiedColumn, $operator->getSqlOperator(), $actualValue);

        return $builder;
    }

    /**
     * Parse dynamic operator from value string.
     *
     * Supports: >=, <=, !=, <>, >, <
     *
     * @return array{0: FilterOperator|null, 1: mixed}
     */
    protected function parseDynamicOperator(mixed $value): array
    {
        if (is_array($value)) {
            return [FilterOperator::EQUAL, $value];
        }

        if (! is_string($value) || $value === '') {
            return [null, null];
        }

        if (preg_match('/^(>=|<=|!=|<>|>|<)(.*)$/', $value, $matches)) {
            $operatorString = $matches[1];
            $actualValue = $matches[2];

            if ($actualValue === '') {
                return [null, null];
            }

            $operator = match ($operatorString) {
                '>=' => FilterOperator::GREATER_THAN_OR_EQUAL,
                '<=' => FilterOperator::LESS_THAN_OR_EQUAL,
                '!=' => FilterOperator::NOT_EQUAL,
                '<>' => FilterOperator::NOT_EQUAL,
                '>' => FilterOperator::GREATER_THAN,
                default => FilterOperator::LESS_THAN,
            };

            if ($this->requiresNumericValue($operator) && ! is_numeric($actualValue)) {
                return [null, null];
            }

            return [$operator, $actualValue];
        }

        return [FilterOperator::EQUAL, $value];
    }

    protected function requiresNumericValue(FilterOperator $operator): bool
    {
        return in_array($operator, [
            FilterOperator::GREATER_THAN,
            FilterOperator::GREATER_THAN_OR_EQUAL,
            FilterOperator::LESS_THAN,
            FilterOperator::LESS_THAN_OR_EQUAL,
        ], true);
    }

    /**
     * Apply filter for array values.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @param  array<mixed>  $values
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     *
     * @throws InvalidFilterValue
     */
    protected function applyArrayValue(Builder $builder, string $qualifiedColumn, FilterOperator $operator, array $values): Builder
    {
        if (empty($values)) {
            return $builder;
        }

        if (! $operator->supportsArrayValues()) {
            throw InvalidFilterValue::make(
                'Array values are only supported for = and != operators',
                $this->getName()
            );
        }

        if ($operator === FilterOperator::EQUAL) {
            $builder->whereIn($qualifiedColumn, $values);
        } else {
            $builder->whereNotIn($qualifiedColumn, $values);
        }

        return $builder;
    }
}

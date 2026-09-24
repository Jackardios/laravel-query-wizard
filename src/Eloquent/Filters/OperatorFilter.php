<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Jackardios\QueryWizard\Eloquent\Filters\Concerns\HandlesRelationFiltering;
use Jackardios\QueryWizard\Enums\FilterOperator;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\Filters\AbstractFilter;
use Jackardios\QueryWizard\Support\FilterValueParser;
use Jackardios\QueryWizard\Support\LikeClause;
use Jackardios\QueryWizard\Support\ParsedDate;
use Stringable;

/**
 * Filter with configurable SQL operators.
 *
 * Supports static operators (=, !=, >, >=, <, <=, LIKE, NOT LIKE) or dynamic
 * operator parsing from the filter value itself.
 *
 * With DYNAMIC, the operand of >, >=, < and <= must be a decimal number or an
 * ISO 8601 date, read in the application timezone; anything else is rejected
 * with a 400. A date names the whole day, so `<=2024-01-31` matches all of
 * January 31. An operator without an operand is absent.
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
        $this->splitValues = ! self::isLike($operator);
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

    public function validateValueShape(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return null;
        }

        return $this->validateScalarOrFlatListValueShape($value);
    }

    public function getOperator(): FilterOperator
    {
        return $this->operator;
    }

    /**
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        return $this->applyToSubject($subject, $value);
    }

    protected function hasEffectiveConstraint(mixed $value): bool
    {
        if ($this->operator === FilterOperator::DYNAMIC) {
            [$operator, $value] = $this->parseDynamicOperator($value);

            if ($operator === null) {
                return false;
            }
        }

        return $value !== [];
    }

    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
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

        if (self::isLike($operator)) {
            return $this->applyLike($builder, $column, $operator === FilterOperator::NOT_LIKE, (array) $actualValue);
        }

        if (is_array($actualValue)) {
            return $this->applyArrayValue($builder, $qualifiedColumn, $operator, $actualValue);
        }

        $builder->where($qualifiedColumn, $operator->getSqlOperator(), $actualValue);

        return $builder;
    }

    private static function isLike(FilterOperator $operator): bool
    {
        return $operator === FilterOperator::LIKE || $operator === FilterOperator::NOT_LIKE;
    }

    /**
     * Match values containing each search value literally: any of them for
     * LIKE, none of them for NOT LIKE.
     *
     * @param  Builder<Model>  $builder
     * @param  array<mixed>  $values
     * @return Builder<Model>
     *
     * @api
     */
    protected function applyLike(Builder $builder, string $column, bool $not, array $values): Builder
    {
        $values = array_values(array_filter($values, static fn (mixed $value): bool => ! FilterValueParser::isBlank($value)));

        if ($values === []) {
            return $builder;
        }

        $sql = LikeClause::for($builder, $column, not: $not);

        if (count($values) === 1) {
            return $builder->whereRaw($sql, [LikeClause::containing($this->likeText($values[0]))]);
        }

        return $builder->where(function (Builder $query) use ($values, $sql, $not): void {
            foreach ($values as $value) {
                $query->whereRaw($sql, [LikeClause::containing($this->likeText($value))], $not ? 'and' : 'or');
            }
        });
    }

    private function likeText(mixed $value): string
    {
        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw InvalidFilterValue::make($value, $this, 'Expected text.');
    }

    /**
     * Parse dynamic operator from value string.
     *
     * Supports: >=, <=, !=, <>, >, <. Lists, numbers, booleans and dates
     * are compared for equality. See FilterValueParser::dynamic().
     *
     * @return array{0: FilterOperator|null, 1: mixed}
     *
     * @api
     */
    protected function parseDynamicOperator(mixed $value): array
    {
        $parsed = FilterValueParser::dynamic($value, $this, new DateTimeZone(date_default_timezone_get()));

        if ($parsed === null) {
            return [null, null];
        }

        [$operator, $operand] = $parsed;

        return $operand instanceof ParsedDate ? self::dateComparison($operator, $operand) : [$operator, $operand];
    }

    /**
     * A date names the whole day: `>D` starts the next day and `<=D` ends before it.
     *
     * @return array{0: FilterOperator, 1: DateTimeInterface|string}
     */
    private static function dateComparison(FilterOperator $operator, ParsedDate $date): array
    {
        if (! $date->dateOnly) {
            return [$operator, $date->value];
        }

        $nextDay = $date->value->modify('+1 day');

        // 10000-01-01 sorts before every four-digit date as text, so the last day compares by its last second.
        if ((int) $nextDay->format('Y') > 9999) {
            return match ($operator) {
                FilterOperator::GREATER_THAN, FilterOperator::LESS_THAN_OR_EQUAL => [$operator, $date->value->setTime(23, 59, 59)],
                default => [$operator, $date->value->format('Y-m-d')],
            };
        }

        return match ($operator) {
            FilterOperator::GREATER_THAN => [FilterOperator::GREATER_THAN_OR_EQUAL, $nextDay->format('Y-m-d')],
            FilterOperator::LESS_THAN_OR_EQUAL => [FilterOperator::LESS_THAN, $nextDay->format('Y-m-d')],
            default => [$operator, $date->value->format('Y-m-d')],
        };
    }

    /**
     * Apply filter for array values.
     *
     * @param  Builder<Model>  $builder
     * @param  array<mixed>  $values
     * @return Builder<Model>
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
                $values,
                $this,
                'Lists of values are only supported by the = and != operators.'
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

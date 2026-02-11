<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent;

use Jackardios\QueryWizard\Eloquent\Filters\DateRangeFilter;
use Jackardios\QueryWizard\Eloquent\Filters\ExactFilter;
use Jackardios\QueryWizard\Eloquent\Filters\JsonContainsFilter;
use Jackardios\QueryWizard\Eloquent\Filters\NullFilter;
use Jackardios\QueryWizard\Eloquent\Filters\OperatorFilter;
use Jackardios\QueryWizard\Eloquent\Filters\PartialFilter;
use Jackardios\QueryWizard\Eloquent\Filters\RangeFilter;
use Jackardios\QueryWizard\Eloquent\Filters\ScopeFilter;
use Jackardios\QueryWizard\Eloquent\Filters\TrashedFilter;
use Jackardios\QueryWizard\Enums\FilterOperator;
use Jackardios\QueryWizard\Filters\CallbackFilter;
use Jackardios\QueryWizard\Filters\PassthroughFilter;

/**
 * Factory class for creating Eloquent filter instances.
 *
 * Provides a convenient API for creating filter instances
 * for Eloquent-specific filters.
 */
final class EloquentFilter
{
    /**
     * Create an exact match filter.
     *
     * @param  string  $property  The column name to filter on
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function exact(string $property, ?string $alias = null): ExactFilter
    {
        return ExactFilter::make($property, $alias);
    }

    /**
     * Create a partial match filter (LIKE %value%).
     *
     * @param  string  $property  The column name to filter on
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function partial(string $property, ?string $alias = null): PartialFilter
    {
        return PartialFilter::make($property, $alias);
    }

    /**
     * Create a scope filter.
     *
     * @param  string  $scope  The scope method name (without 'scope' prefix)
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function scope(string $scope, ?string $alias = null): ScopeFilter
    {
        return ScopeFilter::make($scope, $alias);
    }

    /**
     * Create a trashed filter for soft-deleted models.
     *
     * @param  string|null  $alias  Optional alias for URL parameter name (default: 'trashed')
     */
    public static function trashed(?string $alias = null): TrashedFilter
    {
        return TrashedFilter::make($alias);
    }

    /**
     * Create a null/not null filter.
     *
     * @param  string  $property  The column name to check for NULL
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function null(string $property, ?string $alias = null): NullFilter
    {
        return NullFilter::make($property, $alias);
    }

    /**
     * Create a numeric range filter.
     *
     * @param  string  $property  The column name to filter on
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function range(string $property, ?string $alias = null): RangeFilter
    {
        return RangeFilter::make($property, $alias);
    }

    /**
     * Create a date range filter.
     *
     * @param  string  $property  The column name to filter on
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function dateRange(string $property, ?string $alias = null): DateRangeFilter
    {
        return DateRangeFilter::make($property, $alias);
    }

    /**
     * Create a JSON contains filter.
     *
     * @param  string  $property  The JSON column name (dot notation for nested paths)
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function jsonContains(string $property, ?string $alias = null): JsonContainsFilter
    {
        return JsonContainsFilter::make($property, $alias);
    }

    /**
     * Create a callback filter for custom logic.
     *
     * @param  string  $name  The filter name (also used as property)
     * @param  callable(mixed $query, mixed $value, string $property): void  $callback
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function callback(string $name, callable $callback, ?string $alias = null): CallbackFilter
    {
        return CallbackFilter::make($name, $callback, $alias);
    }

    /**
     * Create a passthrough filter that doesn't modify the query.
     *
     * @param  string  $name  The filter name
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function passthrough(string $name, ?string $alias = null): PassthroughFilter
    {
        return PassthroughFilter::make($name, $alias);
    }

    /**
     * Create an operator filter with configurable comparison operator.
     *
     * Supports =, !=, >, >=, <, <=, LIKE, NOT LIKE operators.
     * Use FilterOperator::DYNAMIC to parse operator from filter value.
     *
     * Examples:
     *   Static: operator('price', FilterOperator::GREATER_THAN) + ?filter[price]=100 → price > 100
     *   Dynamic: operator('price', FilterOperator::DYNAMIC) + ?filter[price]=>=100 → price >= 100
     *
     * @param  string  $property  The column name to filter on
     * @param  FilterOperator  $operator  The comparison operator (default: EQUAL)
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function operator(string $property, FilterOperator $operator = FilterOperator::EQUAL, ?string $alias = null): OperatorFilter
    {
        return OperatorFilter::make($property, $alias, $operator);
    }
}

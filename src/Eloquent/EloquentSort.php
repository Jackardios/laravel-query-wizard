<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent;

use Jackardios\QueryWizard\Eloquent\Sorts\AggregateSort;
use Jackardios\QueryWizard\Eloquent\Sorts\CountSort;
use Jackardios\QueryWizard\Eloquent\Sorts\FieldSort;
use Jackardios\QueryWizard\Sorts\CallbackSort;

/**
 * Factory class for creating Eloquent sort instances.
 *
 * Provides a convenient API for creating sort instances
 * for Eloquent-specific sorts.
 */
final class EloquentSort
{
    /**
     * Create a field sort.
     *
     * @param  string  $property  The column name to sort on
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function field(string $property, ?string $alias = null): FieldSort
    {
        return FieldSort::make($property, $alias);
    }

    /**
     * Create a count sort (sort by relationship count).
     *
     * Example: EloquentSort::count('posts') for ?sort=posts or ?sort=-posts
     *
     * @param  string  $relation  The relationship name to count
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function count(string $relation, ?string $alias = null): CountSort
    {
        return CountSort::make($relation, $alias);
    }

    /**
     * Create a sort by the largest value of a related model's column (`withMax`).
     *
     * Example: EloquentSort::max('posts', 'created_at') to sort by the newest post
     *
     * @param  string  $relation  The relationship name
     * @param  string  $column  The column on the related model
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function max(string $relation, string $column, ?string $alias = null): AggregateSort
    {
        return AggregateSort::make($relation, $column, 'max', $alias);
    }

    /**
     * Create a sort by the smallest value of a related model's column (`withMin`).
     *
     * Example: EloquentSort::min('orders', 'created_at') to sort by the first order
     *
     * @param  string  $relation  The relationship name
     * @param  string  $column  The column on the related model
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function min(string $relation, string $column, ?string $alias = null): AggregateSort
    {
        return AggregateSort::make($relation, $column, 'min', $alias);
    }

    /**
     * Create a sort by the sum of a related model's column (`withSum`).
     *
     * Example: EloquentSort::sum('orders', 'total') to sort by the total order amount
     *
     * @param  string  $relation  The relationship name
     * @param  string  $column  The column on the related model
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function sum(string $relation, string $column, ?string $alias = null): AggregateSort
    {
        return AggregateSort::make($relation, $column, 'sum', $alias);
    }

    /**
     * Create a sort by the average of a related model's column (`withAvg`).
     *
     * Example: EloquentSort::avg('reviews', 'rating') to sort by the average rating
     *
     * @param  string  $relation  The relationship name
     * @param  string  $column  The column on the related model
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function avg(string $relation, string $column, ?string $alias = null): AggregateSort
    {
        return AggregateSort::make($relation, $column, 'avg', $alias);
    }

    /**
     * Create a callback sort for custom logic.
     *
     * @param  string  $name  The sort name
     * @param  callable(mixed $query, string $direction, string $property): mixed  $callback
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function callback(string $name, callable $callback, ?string $alias = null): CallbackSort
    {
        return CallbackSort::make($name, $callback, $alias);
    }
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent;

use Jackardios\QueryWizard\Eloquent\Sorts\CountSort;
use Jackardios\QueryWizard\Eloquent\Sorts\FieldSort;
use Jackardios\QueryWizard\Eloquent\Sorts\RelationSort;
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
     * Create a relation sort (sort by related model's field using aggregate).
     *
     * Example: EloquentSort::relation('orders', 'total', 'sum') to sort by sum of order totals
     *
     * @param  string  $relation  The relationship name
     * @param  string  $column  The column on the related model
     * @param  string  $aggregate  The aggregate function (max, min, sum, avg)
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function relation(
        string $relation,
        string $column,
        string $aggregate = 'max',
        ?string $alias = null
    ): RelationSort {
        return RelationSort::make($relation, $column, $aggregate, $alias);
    }

    /**
     * Create a callback sort for custom logic.
     *
     * @param  string  $name  The sort name
     * @param  callable(mixed $query, string $direction, string $property): void  $callback
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function callback(string $name, callable $callback, ?string $alias = null): CallbackSort
    {
        return CallbackSort::make($name, $callback, $alias);
    }
}

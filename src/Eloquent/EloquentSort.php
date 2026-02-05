<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent;

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
     * @param string $property The column name to sort on
     * @param string|null $alias Optional alias for URL parameter name
     */
    public static function field(string $property, ?string $alias = null): FieldSort
    {
        return FieldSort::make($property, $alias);
    }

    /**
     * Create a callback sort for custom logic.
     *
     * @param string $name The sort name
     * @param callable(mixed $query, string $direction, string $property): void $callback
     * @param string|null $alias Optional alias for URL parameter name
     */
    public static function callback(string $name, callable $callback, ?string $alias = null): CallbackSort
    {
        return CallbackSort::make($name, $callback, $alias);
    }
}

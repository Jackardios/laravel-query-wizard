<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent;

use Jackardios\QueryWizard\Eloquent\Includes\CountInclude;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Includes\CallbackInclude;

/**
 * Factory class for creating Eloquent include instances.
 *
 * Provides a convenient API for creating include instances
 * for Eloquent-specific includes.
 */
final class EloquentInclude
{
    /**
     * Create a relationship include (eager load with with()).
     *
     * @param string $relation The relationship name
     * @param string|null $alias Optional alias for URL parameter name
     */
    public static function relationship(string $relation, ?string $alias = null): RelationshipInclude
    {
        return RelationshipInclude::make($relation, $alias);
    }

    /**
     * Create a count include (eager load with withCount()).
     *
     * @param string $relation The relationship name
     * @param string|null $alias Optional alias for URL parameter name (default: {relation}Count)
     */
    public static function count(string $relation, ?string $alias = null): CountInclude
    {
        return CountInclude::make($relation, $alias);
    }

    /**
     * Create a callback include for custom loading logic.
     *
     * @param string $name The include name
     * @param callable(mixed $query, string $relation): void $callback
     * @param string|null $alias Optional alias for URL parameter name
     */
    public static function callback(string $name, callable $callback, ?string $alias = null): CallbackInclude
    {
        return CallbackInclude::make($name, $callback, $alias);
    }
}

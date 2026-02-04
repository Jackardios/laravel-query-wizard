<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Definitions;

use Jackardios\QueryWizard\Drivers\Eloquent\Sorts\FieldSort;
use Jackardios\QueryWizard\Sorts\CallbackSort;

/**
 * Facade for creating Eloquent sort definitions.
 *
 * ## Available Sort Types
 *
 * | Method | Description | Example Request |
 * |--------|-------------|-----------------|
 * | field() | Sort by database column | ?sort=-created_at |
 * | callback() | Custom sort logic | ?sort=popularity |
 *
 * ## Common Methods (inherited from AbstractSort)
 *
 * All sorts support these chainable methods:
 *
 * - `->alias(string $alias)` — Use different name in URL
 *
 * ## Sort Direction
 *
 * - Ascending: `?sort=name` (no prefix)
 * - Descending: `?sort=-name` (hyphen prefix)
 * - Multiple: `?sort=-created_at,name` (comma-separated)
 *
 * @see \Jackardios\QueryWizard\Sorts\AbstractSort for base sort methods
 */
final class SortDefinition
{
    /**
     * Create a field sort for ordering by database column.
     *
     * Orders results by the specified column using Eloquent's `orderBy()`.
     * The column is automatically qualified with the table name.
     *
     * **Request examples:**
     * - `?sort=name` → ORDER BY name ASC
     * - `?sort=-name` → ORDER BY name DESC
     * - `?sort=-created_at,name` → ORDER BY created_at DESC, name ASC
     *
     * @example Basic usage
     * ```php
     * SortDefinition::field('created_at')
     * ```
     *
     * @example With alias
     * ```php
     * SortDefinition::field('created_at', 'date')
     * // Request: ?sort=-date
     * ```
     *
     * @param string $property The database column to sort by
     * @param string|null $alias Optional alias for the URL parameter name
     */
    public static function field(string $property, ?string $alias = null): FieldSort
    {
        return FieldSort::make($property, $alias);
    }

    /**
     * Create a callback sort with custom logic.
     *
     * Use when built-in sort types don't meet your needs.
     * The callback receives the query builder, sort direction ('asc' or 'desc'),
     * and the sort property name.
     *
     * @example Custom popularity sort
     * ```php
     * SortDefinition::callback('popularity', function ($query, $direction, $property) {
     *     $query->orderByRaw("(likes_count + comments_count * 2) {$direction}");
     * })
     * // Request: ?sort=-popularity
     * ```
     *
     * @example Sort by related model
     * ```php
     * SortDefinition::callback('authorName', function ($query, $direction, $property) {
     *     $query->join('authors', 'posts.author_id', '=', 'authors.id')
     *           ->orderBy('authors.name', $direction);
     * })
     * ```
     *
     * @example With alias
     * ```php
     * SortDefinition::callback('latestActivity', fn($q, $dir) => $q->latest('activity_at'), 'recent')
     * // Request: ?sort=-recent
     * ```
     *
     * @param string $name The sort property name
     * @param callable(mixed $query, string $direction, string $property): void $callback The sort logic
     * @param string|null $alias Optional alias for the URL parameter name
     */
    public static function callback(string $name, callable $callback, ?string $alias = null): CallbackSort
    {
        return CallbackSort::make($name, $callback, $alias);
    }
}

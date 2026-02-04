<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Definitions;

use Jackardios\QueryWizard\Drivers\Eloquent\Includes\CountInclude;
use Jackardios\QueryWizard\Drivers\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Includes\CallbackInclude;

/**
 * Facade for creating Eloquent include definitions.
 *
 * ## Available Include Types
 *
 * | Method | Description | Example Request |
 * |--------|-------------|-----------------|
 * | relationship() | Eager load relationship | ?include=posts |
 * | count() | Load relationship count | ?include=postsCount |
 * | callback() | Custom include logic | — |
 *
 * ## Common Methods (inherited from AbstractInclude)
 *
 * All includes support these chainable methods:
 *
 * - `->alias(string $alias)` — Use different name in URL
 *
 * ## Auto-Detection
 *
 * Includes ending with "Count" suffix (configurable) are auto-detected as count includes:
 * ```php
 * ->setAllowedIncludes(['posts', 'postsCount'])
 * // 'postsCount' automatically becomes IncludeDefinition::count('posts')
 * ```
 *
 * @see \Jackardios\QueryWizard\Includes\AbstractInclude for base include methods
 */
final class IncludeDefinition
{
    /**
     * Create a relationship include for eager loading.
     *
     * Eager loads the specified relationship using Eloquent's `with()`.
     * Supports nested relationships using dot notation.
     * Field selection is applied if sparse fieldsets are configured.
     *
     * **Request examples:**
     * - `?include=posts` → Load posts relationship
     * - `?include=posts,comments` → Load multiple relationships
     * - `?include=posts.author` → Load nested relationships
     *
     * @example Basic usage
     * ```php
     * IncludeDefinition::relationship('posts')
     * ```
     *
     * @example With alias
     * ```php
     * IncludeDefinition::relationship('posts', 'articles')
     * // Request: ?include=articles
     * ```
     *
     * @example Nested relationships
     * ```php
     * IncludeDefinition::relationship('posts.author')
     * IncludeDefinition::relationship('posts.comments.user')
     * ```
     *
     * @param string $relation The relationship name (supports dot notation for nesting)
     * @param string|null $alias Optional alias for the URL parameter name
     */
    public static function relationship(string $relation, ?string $alias = null): RelationshipInclude
    {
        return RelationshipInclude::make($relation, $alias);
    }

    /**
     * Create a count include for loading relationship counts.
     *
     * Loads the count of related records using Eloquent's `withCount()`.
     * The count is added as `{relation}_count` attribute on the model.
     *
     * **Request examples:**
     * - `?include=postsCount` → Adds posts_count attribute
     *
     * @example Basic usage
     * ```php
     * IncludeDefinition::count('posts')
     * // Result: $user->posts_count
     * ```
     *
     * @example With custom alias
     * ```php
     * IncludeDefinition::count('posts', 'postCount')
     * // Request: ?include=postCount
     * ```
     *
     * @param string $relation The relationship name to count
     * @param string|null $alias Optional alias for the URL parameter name
     */
    public static function count(string $relation, ?string $alias = null): CountInclude
    {
        return CountInclude::make($relation, $alias);
    }

    /**
     * Create a callback include with custom logic.
     *
     * Use when built-in include types don't meet your needs.
     * The callback receives the query builder, include name, and selected fields.
     *
     * @example Load recent posts only
     * ```php
     * IncludeDefinition::callback('recentPosts', function ($query, $include, $fields) {
     *     $query->with(['posts' => function ($q) {
     *         $q->where('created_at', '>', now()->subMonth())
     *           ->orderBy('created_at', 'desc')
     *           ->limit(5);
     *     }]);
     * })
     * ```
     *
     * @example Conditional eager loading
     * ```php
     * IncludeDefinition::callback('fullProfile', function ($query, $include, $fields) {
     *     $query->with(['profile', 'settings', 'preferences']);
     * })
     * ```
     *
     * @param string $name The include name
     * @param callable(mixed $query, string $relation, array<string> $fields): void $callback The include logic
     * @param string|null $alias Optional alias for the URL parameter name
     */
    public static function callback(string $name, callable $callback, ?string $alias = null): CallbackInclude
    {
        return CallbackInclude::make($name, $callback, $alias);
    }
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Definitions;

use Jackardios\QueryWizard\Drivers\Eloquent\Filters\DateRangeFilter;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\ExactFilter;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\JsonContainsFilter;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\NullFilter;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\PartialFilter;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\RangeFilter;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\ScopeFilter;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\TrashedFilter;
use Jackardios\QueryWizard\Filters\CallbackFilter;
use Jackardios\QueryWizard\Filters\PassthroughFilter;

/**
 * Facade for creating Eloquent filter definitions.
 *
 * ## Available Filter Types
 *
 * | Method | Description | Example Request |
 * |--------|-------------|-----------------|
 * | exact() | Exact match (= or IN) | ?filter[status]=active |
 * | partial() | LIKE %value% (case-insensitive) | ?filter[name]=john |
 * | scope() | Model scope | ?filter[popular]=1000 |
 * | callback() | Custom logic | — |
 * | range() | Min/max range | ?filter[price][min]=10&filter[price][max]=100 |
 * | dateRange() | Date range | ?filter[created_at][from]=2024-01-01 |
 * | null() | IS NULL / IS NOT NULL | ?filter[deleted_at]=true |
 * | jsonContains() | JSON column contains | ?filter[tags]=php,laravel |
 * | trashed() | Soft deletes | ?filter[trashed]=with |
 * | passthrough() | Capture without filtering | — |
 *
 * ## Common Methods (inherited from AbstractFilter)
 *
 * All filters support these chainable methods:
 *
 * - `->alias(string $alias)` — Use different name in URL
 * - `->default(mixed $value)` — Default value when not in request
 * - `->prepareValueWith(Closure $callback)` — Transform value before applying
 *
 * @see \Jackardios\QueryWizard\Filters\AbstractFilter for base filter methods
 */
final class FilterDefinition
{
    /**
     * Create an exact match filter.
     *
     * Matches exact value(s). Arrays become WHERE IN queries.
     *
     * **Request examples:**
     * - `?filter[status]=active` → `WHERE status = 'active'`
     * - `?filter[status]=active,pending` → `WHERE status IN ('active', 'pending')`
     *
     * **Filter-specific methods:**
     * - `->withRelationConstraint(bool)` — Enable/disable auto whereHas for `relation.column` (default: true)
     *
     * @example Basic usage
     * ```php
     * FilterDefinition::exact('status')
     * ```
     *
     * @example With alias (URL: ?filter[user]=5)
     * ```php
     * FilterDefinition::exact('user_id', 'user')
     * ```
     *
     * @example With default value
     * ```php
     * FilterDefinition::exact('status')->default('active')
     * ```
     *
     * @example With value transformation
     * ```php
     * FilterDefinition::exact('email')->prepareValueWith(fn($v) => strtolower($v))
     * ```
     *
     * @example Relation filtering (auto whereHas)
     * ```php
     * FilterDefinition::exact('posts.status')
     * ```
     *
     * @example Disable relation constraint
     * ```php
     * FilterDefinition::exact('posts.status')->withRelationConstraint(false)
     * ```
     *
     * @param string $property The database column to filter on (supports dot notation for relations)
     * @param string|null $alias Optional alias for the URL parameter name
     */
    public static function exact(string $property, ?string $alias = null): ExactFilter
    {
        return ExactFilter::make($property, $alias);
    }

    /**
     * Create a partial match filter (LIKE %value%).
     *
     * Case-insensitive search using SQL LIKE with wildcards.
     * Empty string values in arrays are silently ignored.
     *
     * **Request examples:**
     * - `?filter[name]=john` → Matches "John", "Johnny", "john doe"
     * - `?filter[name]=john,jane` → Matches names containing "john" OR "jane"
     *
     * **Filter-specific methods:**
     * - `->withRelationConstraint(bool)` — Enable/disable auto whereHas for `relation.column` (default: true)
     *
     * @example Basic usage
     * ```php
     * FilterDefinition::partial('name')
     * ```
     *
     * @example Search in related model
     * ```php
     * FilterDefinition::partial('posts.title')
     * ```
     *
     * @param string $property The database column to filter on (supports dot notation for relations)
     * @param string|null $alias Optional alias for the URL parameter name
     */
    public static function partial(string $property, ?string $alias = null): PartialFilter
    {
        return PartialFilter::make($property, $alias);
    }

    /**
     * Create a scope filter that uses model scopes.
     *
     * Calls a scope method on the model. The filter value is passed as scope arguments.
     * Supports dot notation for scopes on related models (uses whereHas).
     *
     * **Request examples:**
     * - `?filter[popular]=5000` → Calls `scopePopular($query, 5000)`
     * - `?filter[status]=active,1` → Calls `scopeStatus($query, 'active', '1')`
     *
     * **Filter-specific methods:**
     * - `->resolveModelBindings(bool)` — Auto-resolve model bindings in scope parameters (default: true)
     *
     * @example Basic usage with model scope
     * ```php
     * // Model: public function scopePopular($query, $minFollowers = 1000)
     * FilterDefinition::scope('popular')
     * ```
     *
     * @example Scope on related model
     * ```php
     * // Filter by posts.scopePublished
     * FilterDefinition::scope('posts.published')
     * ```
     *
     * @example Disable automatic model binding resolution
     * ```php
     * FilterDefinition::scope('byUser')->resolveModelBindings(false)
     * ```
     *
     * @param string $scope The scope name (without 'scope' prefix, supports dot notation)
     * @param string|null $alias Optional alias for the URL parameter name
     */
    public static function scope(string $scope, ?string $alias = null): ScopeFilter
    {
        return ScopeFilter::make($scope, $alias);
    }

    /**
     * Create a filter for soft-deleted models.
     *
     * Requires the model to use the SoftDeletes trait.
     *
     * **Request examples:**
     * - `?filter[trashed]=with` → Include soft-deleted records (withTrashed)
     * - `?filter[trashed]=only` → Only soft-deleted records (onlyTrashed)
     * - `?filter[trashed]=without` → Exclude soft-deleted records (default behavior)
     *
     * @example Basic usage
     * ```php
     * FilterDefinition::trashed()
     * ```
     *
     * @example With custom alias
     * ```php
     * FilterDefinition::trashed('deleted')  // ?filter[deleted]=with
     * ```
     *
     * @param string|null $alias Optional alias for the URL parameter name (default: 'trashed')
     */
    public static function trashed(?string $alias = null): TrashedFilter
    {
        return TrashedFilter::make($alias);
    }

    /**
     * Create a callback filter with custom logic.
     *
     * Use when built-in filter types don't meet your needs.
     * The callback receives the query builder, filter value, and property name.
     *
     * @example Basic callback
     * ```php
     * FilterDefinition::callback('age_range', function ($query, $value, $property) {
     *     [$min, $max] = explode('-', $value);
     *     $query->whereBetween('age', [(int) $min, (int) $max]);
     * })
     * ```
     *
     * @example With alias
     * ```php
     * FilterDefinition::callback('search_all', fn($q, $v) => $q->search($v), 'q')
     * // Request: ?filter[q]=search+term
     * ```
     *
     * @param string $property The filter property name
     * @param callable(mixed $query, mixed $value, string $property): void $callback The filter logic
     * @param string|null $alias Optional alias for the URL parameter name
     */
    public static function callback(string $property, callable $callback, ?string $alias = null): CallbackFilter
    {
        return CallbackFilter::make($property, $callback, $alias);
    }

    /**
     * Create a numeric range filter.
     *
     * Filters by minimum and/or maximum values using >= and <= operators.
     * Either min or max can be omitted for one-sided ranges.
     *
     * **Request examples:**
     * - `?filter[price][min]=100&filter[price][max]=500` → Between 100 and 500
     * - `?filter[price][min]=100` → Greater than or equal to 100
     * - `?filter[price][max]=500` → Less than or equal to 500
     *
     * **Filter-specific methods:**
     * - `->keys(string $minKey, string $maxKey)` — Custom key names (default: 'min', 'max')
     *
     * @example Basic usage
     * ```php
     * FilterDefinition::range('price')
     * ```
     *
     * @example With custom keys
     * ```php
     * FilterDefinition::range('price')->keys('from', 'to')
     * // Request: ?filter[price][from]=100&filter[price][to]=500
     * ```
     *
     * @param string $property The database column to filter on
     * @param string|null $alias Optional alias for the URL parameter name
     */
    public static function range(string $property, ?string $alias = null): RangeFilter
    {
        return RangeFilter::make($property, $alias);
    }

    /**
     * Create a date range filter.
     *
     * Filters by date/datetime range using >= and <= operators.
     * Either from or to can be omitted for one-sided ranges.
     *
     * **Request examples:**
     * - `?filter[created_at][from]=2024-01-01&filter[created_at][to]=2024-12-31`
     * - `?filter[created_at][from]=2024-01-01` → On or after date
     * - `?filter[created_at][to]=2024-12-31` → On or before date
     *
     * **Filter-specific methods:**
     * - `->keys(string $fromKey, string $toKey)` — Custom key names (default: 'from', 'to')
     * - `->dateFormat(string $format)` — Format for DateTime objects (e.g., 'Y-m-d')
     *
     * @example Basic usage
     * ```php
     * FilterDefinition::dateRange('created_at')
     * ```
     *
     * @example With custom keys
     * ```php
     * FilterDefinition::dateRange('created_at')->keys('start', 'end')
     * // Request: ?filter[created_at][start]=2024-01-01
     * ```
     *
     * @example With date format for DateTime output
     * ```php
     * FilterDefinition::dateRange('created_at')->dateFormat('Y-m-d H:i:s')
     * ```
     *
     * @param string $property The database column to filter on
     * @param string|null $alias Optional alias for the URL parameter name
     */
    public static function dateRange(string $property, ?string $alias = null): DateRangeFilter
    {
        return DateRangeFilter::make($property, $alias);
    }

    /**
     * Create a null/not null filter.
     *
     * Checks if a column is NULL or NOT NULL based on a boolean-like value.
     *
     * **Request examples:**
     * - `?filter[deleted_at]=true` → WHERE deleted_at IS NULL
     * - `?filter[deleted_at]=false` → WHERE deleted_at IS NOT NULL
     * - Truthy values: true, "true", "1", "yes", "on"
     * - Falsy values: false, "false", "0", "no", "off", ""
     *
     * **Filter-specific methods:**
     * - `->invertLogic(bool)` — Invert the logic (true becomes NOT NULL, false becomes NULL)
     *
     * @example Basic usage
     * ```php
     * FilterDefinition::null('deleted_at')
     * // ?filter[deleted_at]=true → records where deleted_at IS NULL
     * ```
     *
     * @example Inverted logic (check for existence)
     * ```php
     * FilterDefinition::null('verified_at')->invertLogic()
     * // ?filter[verified_at]=true → records where verified_at IS NOT NULL
     * ```
     *
     * @param string $property The database column to filter on
     * @param string|null $alias Optional alias for the URL parameter name
     */
    public static function null(string $property, ?string $alias = null): NullFilter
    {
        return NullFilter::make($property, $alias);
    }

    /**
     * Create a JSON contains filter.
     *
     * Filters JSON/array columns using whereJsonContains.
     * Supports dot notation for nested JSON paths (converted to arrow notation).
     *
     * **Request examples:**
     * - `?filter[tags]=php` → JSON column contains "php"
     * - `?filter[tags]=php,laravel` → Contains "php" AND "laravel" (matchAll: true)
     * - `?filter[tags]=php,laravel` → Contains "php" OR "laravel" (matchAll: false)
     *
     * **Filter-specific methods:**
     * - `->matchAll(bool)` — Match all values (AND) vs any value (OR) (default: true)
     *
     * @example Basic usage
     * ```php
     * FilterDefinition::jsonContains('tags')
     * ```
     *
     * @example Match any value (OR logic)
     * ```php
     * FilterDefinition::jsonContains('tags')->matchAll(false)
     * // ?filter[tags]=php,laravel → tags contains "php" OR "laravel"
     * ```
     *
     * @example Nested JSON path
     * ```php
     * FilterDefinition::jsonContains('meta.roles')
     * // Queries meta->roles JSON path
     * ```
     *
     * @param string $property The JSON column (supports dot notation for nested paths)
     * @param string|null $alias Optional alias for the URL parameter name
     */
    public static function jsonContains(string $property, ?string $alias = null): JsonContainsFilter
    {
        return JsonContainsFilter::make($property, $alias);
    }

    /**
     * Create a passthrough filter that captures value without applying to query.
     *
     * Use when you need Query Wizard to validate and capture a filter value,
     * but handle the filtering logic yourself (e.g., for external services,
     * caching keys, or deferred processing).
     *
     * Captured values can be retrieved via `$wizard->getPassthroughFilters()`.
     *
     * @example Capture for external API
     * ```php
     * $wizard = QueryWizard::for(User::class)
     *     ->setAllowedFilters([
     *         FilterDefinition::passthrough('external_search'),
     *     ]);
     *
     * $users = $wizard->get();
     * $passthroughFilters = $wizard->getPassthroughFilters();
     * // ['external_search' => 'query value']
     * ```
     *
     * @param string $name The filter name as it appears in the request
     */
    public static function passthrough(string $name): PassthroughFilter
    {
        return PassthroughFilter::make($name);
    }
}

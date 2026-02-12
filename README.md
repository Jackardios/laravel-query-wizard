# Laravel Query Wizard

Build Eloquent queries from API request parameters. Filter, sort, include relationships, select fields, and append computed attributes - all from query string parameters.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jackardios/laravel-query-wizard.svg)](https://packagist.org/packages/jackardios/laravel-query-wizard)
[![License](https://img.shields.io/packagist/l/jackardios/laravel-query-wizard.svg)](https://packagist.org/packages/jackardios/laravel-query-wizard)

## Why Use Query Wizard?

Building APIs often requires handling complex query parameters for filtering, sorting, and including relationships. Without a proper solution, you end up with:

- Repetitive boilerplate code in every controller
- Inconsistent parameter handling across endpoints
- Security vulnerabilities from unvalidated user input
- Tight coupling between request handling and business logic

**Query Wizard solves these problems** by providing a clean, declarative API that:

- Automatically parses request parameters
- Validates and whitelists allowed operations
- Applies filters, sorts, includes, fields, and appends to your queries
- Protects against resource exhaustion attacks with built-in limits
- Supports custom filter/sort/include implementations

## Installation

```bash
composer require jackardios/laravel-query-wizard
```

The package uses Laravel's auto-discovery, so no additional setup is required.

### Publish Configuration (Optional)

```bash
php artisan vendor:publish --provider="Jackardios\QueryWizard\QueryWizardServiceProvider" --tag="config"
```

## Quick Start

```php
use App\Models\User;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;

public function index()
{
    $users = EloquentQueryWizard::for(User::class)
        ->allowedFilters('name', 'email', 'status')
        ->allowedSorts('name', 'created_at')
        ->allowedIncludes('posts', 'profile')
        ->get();

    return response()->json($users);
}
```

Now your API supports requests like:

```
GET /users?filter[name]=John&filter[status]=active&sort=-created_at&include=posts
```

## Table of Contents

- [Basic Usage](#basic-usage)
- [Filtering](#filtering)
- [Sorting](#sorting)
- [Including Relationships](#including-relationships)
- [Selecting Fields](#selecting-fields)
  - [Relation Field Modes](#relation-field-modes)
- [Appending Attributes](#appending-attributes)
- [Resource Schemas](#resource-schemas)
- [ModelQueryWizard](#modelquerywizard)
- [Laravel Octane Compatibility](#laravel-octane-compatibility)
- [Security](#security)
- [Configuration](#configuration)
- [Error Handling](#error-handling)
- [Batch Processing Limitations](#batch-processing-limitations)
- [API Reference](#api-reference)
- [Comparison with spatie/laravel-query-builder](#comparison-with-spatielaravel-query-builder)

## Basic Usage

### Creating a Query Wizard

```php
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;

// From a model class
$wizard = EloquentQueryWizard::for(User::class);

// From an existing query builder
$wizard = EloquentQueryWizard::for(User::where('active', true));

// From a relation
$wizard = EloquentQueryWizard::for($user->posts());
```

### Executing Queries

```php
// Get all results
$users = $wizard->get();

// Get first result
$user = $wizard->first();
$user = $wizard->firstOrFail();  // Throws ModelNotFoundException if not found

// Paginate results
$users = $wizard->paginate(15);
$users = $wizard->simplePaginate(15);
$users = $wizard->cursorPaginate(15);

// Get the underlying query builder (without executing)
$query = $wizard->toQuery();
```

### Modifying the Query

You can call query builder methods directly — they're proxied to the underlying builder. **Configuration methods must be called before query builder methods:**

```php
EloquentQueryWizard::for(User::class)
    ->allowedFilters('name')                // 1. configuration
    ->where('active', true)                 // 2. builder methods
    ->whereNotNull('email_verified_at')
    ->get();                                // 3. execution
```

For base query scopes, pass a pre-configured query to `for()`:

```php
EloquentQueryWizard::for(User::where('active', true))
    ->allowedFilters('name')
    ->allowedSorts('created_at')
    ->get();
```

## Filtering

Filters allow API consumers to narrow down results based on specific criteria.

### Basic Filters

```php
use Jackardios\QueryWizard\Eloquent\EloquentFilter;

EloquentQueryWizard::for(User::class)
    ->allowedFilters(
        'name',                              // Exact match (string shorthand)
        'email',                             // Exact match (string shorthand)
        EloquentFilter::exact('status'),     // Explicit exact filter
        EloquentFilter::partial('bio'),      // LIKE %value%
    )
    ->get();
```

**Request:** `GET /users?filter[name]=John&filter[bio]=developer`

### Available Filter Types

#### Exact Filter

Matches exact values. Supports arrays for `IN` queries.

```php
EloquentFilter::exact('status')
EloquentFilter::exact('category_id')

// With alias (use different name in URL)
EloquentFilter::exact('user_id', 'user')  // ?filter[user]=5
```

**Request:** `?filter[status]=active` or `?filter[status]=active,pending` (IN query)

#### Partial Filter

Case-insensitive LIKE search.

```php
EloquentFilter::partial('name')
EloquentFilter::partial('description')
```

**Request:** `?filter[name]=john` matches "John", "Johnny", "john doe"

#### Scope Filter

Uses model scopes for filtering.

```php
// Model
class User extends Model
{
    public function scopePopular($query, $minFollowers = 1000)
    {
        return $query->where('followers_count', '>=', $minFollowers);
    }
}

// Query Wizard
EloquentFilter::scope('popular')
```

**Request:** `?filter[popular]=5000`

#### Callback Filter

Custom filtering logic.

```php
EloquentFilter::callback('age_range', function ($query, $value, $property) {
    [$min, $max] = explode('-', $value);
    $query->whereBetween('age', [(int) $min, (int) $max]);
})
```

**Request:** `?filter[age_range]=18-35`

#### Trashed Filter

Filter soft-deleted models.

```php
EloquentFilter::trashed()
```

**Request:** `?filter[trashed]=with` (include), `?filter[trashed]=only` (only trashed), omit or any other value (exclude)

#### Range Filter

Filter by numeric ranges.

```php
EloquentFilter::range('price')

// Custom keys (default: 'min', 'max')
EloquentFilter::range('price')->minKey('from')->maxKey('to')
```

**Request:** `?filter[price][min]=100&filter[price][max]=500`

#### Date Range Filter

Filter by date ranges.

```php
EloquentFilter::dateRange('created_at')

// Custom keys (default: 'from', 'to')
EloquentFilter::dateRange('created_at')->fromKey('start')->toKey('end')

// Custom date format for DateTime objects
EloquentFilter::dateRange('created_at')->dateFormat('Y-m-d')
```

**Request:** `?filter[created_at][from]=2024-01-01&filter[created_at][to]=2024-12-31`

#### Null Filter

Check for NULL/NOT NULL values.

```php
EloquentFilter::null('deleted_at')

// Invert logic (true = NOT NULL)
EloquentFilter::null('verified_at')->withInvertedLogic()
```

**Request:** `?filter[deleted_at]=true` (IS NULL), `?filter[deleted_at]=false` (IS NOT NULL)

#### JSON Contains Filter

Filter JSON columns.

```php
EloquentFilter::jsonContains('meta.tags')

// Match any value (OR) instead of all (AND)
EloquentFilter::jsonContains('settings.roles')->matchAny()
```

**Request:** `?filter[meta.tags]=laravel,php`

#### Operator Filter

Filter with configurable comparison operators.

```php
use Jackardios\QueryWizard\Enums\FilterOperator;

// Static operator (fixed comparison)
EloquentFilter::operator('price', FilterOperator::GREATER_THAN)      // price > value
EloquentFilter::operator('status', FilterOperator::NOT_EQUAL)        // status != value
EloquentFilter::operator('name', FilterOperator::LIKE)               // name LIKE %value%

// Dynamic operator (parsed from value)
EloquentFilter::operator('price', FilterOperator::DYNAMIC)
```

Available operators: `EQUAL`, `NOT_EQUAL`, `GREATER_THAN`, `GREATER_THAN_OR_EQUAL`, `LESS_THAN`, `LESS_THAN_OR_EQUAL`, `LIKE`, `NOT_LIKE`, `DYNAMIC`

**Request (static):** `?filter[price]=100` with `GREATER_THAN` → `price > 100`

**Request (dynamic):** `?filter[price]=>=100` → `price >= 100`, `?filter[price]=!=5` → `price != 5`

Supported dynamic prefixes: `>=`, `<=`, `!=`, `<>`, `>`, `<`

#### Passthrough Filter

Capture filter values without applying them to the query. Useful for external API calls or custom processing.

```php
$wizard = EloquentQueryWizard::for(User::class)
    ->allowedFilters(
        EloquentFilter::passthrough('external_id'),
    );

$results = $wizard->get();

// Access passthrough values
$passthroughFilters = $wizard->getPassthroughFilters();
// Collection: ['external_id' => 'value']
```

### Filter Options

All filters support these fluent modifiers:

```php
EloquentFilter::exact('status')
    ->alias('state')                           // URL parameter name
    ->default('active')                        // Default value when not in request
    ->prepareValueWith(fn($value) => strtolower($value))  // Transform before applying
    ->when(fn($value) => $value !== 'all')     // Conditional: skip if returns false
```

By default, explicit empty/null request values (for example `?filter[status]=`) skip the filter and do not use `default()`.
If you want `default()` to be applied for explicit null/empty values too, enable `apply_filter_default_on_null` in config.

#### Conditional Filtering with `when()`

Skip filter application based on a condition:

```php
// Skip filter if value is 'all'
EloquentFilter::exact('status')
    ->when(fn($value) => $value !== 'all')

// Only apply filter for authenticated users
EloquentFilter::exact('user_id')
    ->when(fn($value) => auth()->check())

// Skip empty values
EloquentFilter::partial('search')
    ->when(fn($value) => !empty($value))
```

**Request:** `?filter[status]=all` → filter is skipped, all results returned

#### Relation Filtering

Filters with dot notation automatically use `whereHas`:

```php
EloquentFilter::exact('posts.status')  // Filters users by their posts' status
```

Disable this behavior:

```php
EloquentFilter::exact('posts.status')->withoutRelationConstraint()
```

## Sorting

Allow API consumers to sort results.

### Basic Sorts

```php
use Jackardios\QueryWizard\Eloquent\EloquentSort;

EloquentQueryWizard::for(User::class)
    ->allowedSorts(
        'name',                            // Field sort (string shorthand)
        'created_at',                      // Field sort (string shorthand)
        EloquentSort::field('email'),      // Explicit field sort
    )
    ->get();
```

**Request:** `?sort=name` (ascending), `?sort=-name` (descending), `?sort=-created_at,name` (multiple)

### Available Sort Types

#### Field Sort

Sort by a database column.

```php
EloquentSort::field('created_at')
EloquentSort::field('created_at', 'date')  // Alias: ?sort=-date
```

#### Count Sort

Sort by relationship count.

```php
EloquentSort::count('posts')                    // Sort by posts count
EloquentSort::count('comments', 'popularity')   // Alias: ?sort=-popularity
```

**Request:** `?sort=-posts` (most posts first)

#### Relation Sort

Sort by a related model's aggregate value.

```php
EloquentSort::relation('posts', 'created_at', 'max')   // Newest post date
EloquentSort::relation('orders', 'total', 'sum')        // Total order amount
EloquentSort::relation('ratings', 'score', 'avg')       // Average rating
```

Supported aggregates: `max`, `min`, `sum`, `avg`, `count`, `exists`

**Request:** `?sort=-orders` (highest order total first)

#### Callback Sort

Custom sorting logic.

```php
EloquentSort::callback('popularity', function ($query, $direction, $property) {
    $query->orderByRaw("(likes_count + comments_count * 2) {$direction}");
})
```

### Default Sorts

```php
EloquentQueryWizard::for(User::class)
    ->allowedSorts('name', 'created_at')
    ->defaultSorts('-created_at')  // Applied when no sort in request
    ->get();
```

## Including Relationships

Eager load relationships based on request parameters.

### Basic Includes

```php
use Jackardios\QueryWizard\Eloquent\EloquentInclude;

EloquentQueryWizard::for(User::class)
    ->allowedIncludes(
        'posts',                               // Relationship (string shorthand)
        'profile',                             // Relationship (string shorthand)
        'postsCount',                          // Count (auto-detected by suffix)
        EloquentInclude::relationship('comments'),
        EloquentInclude::count('followers'),
    )
    ->get();
```

**Request:** `?include=posts,profile,postsCount`

### Available Include Types

#### Relationship Include

Eager load a relationship with `with()`.

```php
EloquentInclude::relationship('posts')
EloquentInclude::relationship('posts.author')  // Nested relationships
```

#### Count Include

Load relationship counts with `withCount()`.

```php
EloquentInclude::count('posts')
EloquentInclude::count('posts', 'postCount')  // Custom alias
```

#### Exists Include

Check relationship existence with `withExists()`. Adds a boolean attribute `{relation}_exists`.

```php
EloquentInclude::exists('posts')
EloquentInclude::exists('comments', 'hasComments')  // Custom alias
```

**Request:** `?include=postsExists` → adds `posts_exists` boolean attribute

Includes ending with "Count" or "Exists" (configurable suffixes) are auto-detected:

```php
->allowedIncludes('posts', 'postsCount', 'postsExists')
// postsCount → count include, postsExists → exists include
```

#### Callback Include

Custom include logic.

```php
EloquentInclude::callback('recent_posts', function ($query, $relation) {
    $query->with(['posts' => function ($q) {
        $q->where('created_at', '>', now()->subMonth())
          ->orderBy('created_at', 'desc')
          ->limit(5);
    }]);
})
```

### Default Includes

```php
EloquentQueryWizard::for(User::class)
    ->allowedIncludes('posts', 'profile', 'settings')
    ->defaultIncludes('profile')  // Used only when ?include is not provided
    ->get();
```

## Selecting Fields

Allow sparse fieldsets (JSON:API compatible).

```php
EloquentQueryWizard::for(User::class)
    ->allowedFields('id', 'name', 'email', 'posts.id', 'posts.title')
    ->get();
```

**Request (resource-keyed):** `?fields[user]=id,name&fields[posts]=id,title`

**Request (root shorthand):** `?fields=id,name`

The resource key (`user` in the example) is derived from the model name in camelCase. You can customize it with schemas.

If both formats are provided, the resource-keyed format takes precedence for the root resource.

### Relation Fields

For included relationships, use the **relation name** (or include alias) as the key — not the database table name:

```php
// Model: Task with createdBy(): BelongsTo<User>  (table: users)

EloquentQueryWizard::for(Task::class)
    ->allowedIncludes('createdBy')
    ->allowedFields('id', 'title', 'createdBy.id', 'createdBy.name')
    ->get();
```

```
✅ ?include=createdBy&fields[task]=id,title&fields[createdBy]=id,name
✅ ?include=createdBy&fields=id,title,createdBy.id,createdBy.name  (dot notation)
❌ ?fields[users]=id,name  — table name won't work, use relation name
```

If the include has an alias, use the alias in fields too:

```php
->allowedIncludes(EloquentInclude::relationship('createdBy')->alias('creator'))
->allowedFields('id', 'creator.id', 'creator.name')

// URL: ?include=creator&fields[creator]=id,name
```

### Relation Field Modes

When using sparse fieldsets with relationships, the package offers two modes for handling relation field selection:

```php
// config/query-wizard.php
'optimizations' => [
    'relation_select_mode' => 'safe',  // 'safe' (recommended) or 'off'
],
```

#### Safe Mode (`'safe'`) — Recommended

Automatically handles foreign keys and protects accessors:

```php
// Request: ?include=posts&fields[user]=id,name&fields[posts]=title

// What happens:
// 1. Root query: SELECT id, name, user_id FROM users
//                                  ^^^^^^^^ auto-injected for eager loading
// 2. Relation query: SELECT title, user_id FROM posts
//                           ^^^^^^^^ auto-injected for matching
// 3. If relation has appends: SELECT * + makeHidden() (protects accessors)
```

**Benefits:**
- Automatic FK injection for eager loading to work correctly
- Accessor protection: relations with appends use `SELECT *` + `makeHidden()` to ensure accessors have access to required fields
- Works with: `BelongsTo`, `HasOne`, `HasMany`, `MorphOne`, `MorphMany`

#### Off Mode (`'off'`)

No automatic handling — you must include all required fields manually:

```php
// Request: ?include=posts&fields[user]=id,name&fields[posts]=title

// What happens:
// 1. Root query: SELECT id, name FROM users  (NO user_id!)
// 2. Eager loading fails silently — posts collection is empty!
```

**When to use:**
- Maximum performance when you know exactly which fields are needed
- Backwards compatibility with existing code
- When you don't use relation fields

> **Warning:** With `'off'` mode, if you request `?fields[user]=id,name` and `?include=posts`, the posts won't load because the foreign key (`user_id`) is missing from the SELECT. You must manually include all required FK columns.

#### Mode Comparison

| Feature | `'safe'` | `'off'` |
|---------|:--------:|:-------:|
| FK auto-injection (root) | Yes | No |
| FK auto-injection (relations) | Yes | No |
| Accessor protection | Yes | No |
| Performance overhead | Minimal (~0.004ms/model) | None |
| Manual FK management | Not needed | Required |

#### Model-level `$appends` Support

Safe mode automatically detects models with built-in `$appends` and uses `SELECT *` + `makeHidden()` to ensure accessors have access to all required attributes:

```php
class Post extends Model
{
    protected $appends = ['reading_time'];  // Always serialized

    public function getReadingTimeAttribute(): int
    {
        return ceil(str_word_count($this->content) / 200);
    }
}

// Request: ?include=posts&fields[posts]=id,title
// Safe mode detects $appends → uses SELECT * → reading_time works correctly!
```

This protection applies to:
- Explicitly requested appends (`?append=posts.reading_time`)
- Default appends (`defaultAppends()`)
- Model-level `$appends` property

## Appending Attributes

Append computed model attributes (accessors) to results.

```php
// Model
class User extends Model
{
    protected function fullName(): Attribute
    {
        return Attribute::get(fn() => "{$this->first_name} {$this->last_name}");
    }
}

// Query Wizard
EloquentQueryWizard::for(User::class)
    ->allowedAppends('full_name', 'posts.reading_time')
    ->get();
```

**Request:** `?append=full_name,posts.reading_time`

### Nested Appends

Append attributes on related models:

```php
->allowedAppends(
    'full_name',              // Root model
    'posts.reading_time',     // Related posts
    'posts.author.badge',     // Deeply nested
)
```

Like fields, use **relation names** — not table names:

```php
// Model: Task with createdBy(): BelongsTo<User>

->allowedAppends('createdBy.full_name')

// URL: ?append=createdBy.full_name
// ❌ ?append=users.full_name  — table name won't work
```

### Wildcard Appends

Allow any appends on a relation:

```php
->allowedAppends('posts.*')  // Any append on posts
```

## Resource Schemas

For larger applications, use Resource Schemas to define all query capabilities in one place.

### Creating a Schema

```php
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;

class UserSchema extends ResourceSchema
{
    public function model(): string
    {
        return \App\Models\User::class;
    }

    public function type(): string
    {
        return 'user';  // For ?fields[user]=id,name
    }

    public function filters(QueryWizardInterface $wizard): array
    {
        return [
            'name',
            EloquentFilter::partial('email'),
            EloquentFilter::exact('status'),
            EloquentFilter::scope('popular'),
            EloquentFilter::trashed(),
        ];
    }

    public function sorts(QueryWizardInterface $wizard): array
    {
        return [
            'name',
            'created_at',
            EloquentSort::callback('popularity', function ($query, $direction) {
                $query->orderBy('followers_count', $direction);
            }),
        ];
    }

    public function includes(QueryWizardInterface $wizard): array
    {
        return ['posts', 'profile', 'postsCount'];
    }

    public function fields(QueryWizardInterface $wizard): array
    {
        return ['id', 'name', 'email', 'status', 'created_at'];
    }

    public function appends(QueryWizardInterface $wizard): array
    {
        return ['full_name', 'avatar_url'];
    }

    public function defaultSorts(QueryWizardInterface $wizard): array
    {
        return ['-created_at'];
    }

    public function defaultIncludes(QueryWizardInterface $wizard): array
    {
        return ['profile'];
    }

    public function defaultFields(QueryWizardInterface $wizard): array
    {
        return ['id', 'name', 'email'];  // Applied when ?fields is absent
    }

    public function defaultFilters(QueryWizardInterface $wizard): array
    {
        return [
            'status' => 'active',  // Applied when ?filter[status] is absent
        ];
    }
}
```

### Using Schemas

```php
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;

$users = EloquentQueryWizard::forSchema(UserSchema::class)->get();
```

### Combining Schemas with Overrides

You can use a schema as a base and override specific settings:

```php
EloquentQueryWizard::forSchema(UserSchema::class)
    ->disallowedFilters('status')        // Remove filter from schema
    ->disallowedIncludes('posts')        // Remove include from schema
    ->allowedAppends('extra_append')     // Add additional append
    ->get();
```

### Wildcard Support in disallowed*() Methods

All `disallowed*()` methods support wildcards for convenient bulk exclusions:

| Pattern | Meaning | Example |
|---------|---------|---------|
| `'*'` | Block everything | `disallowedFields('*')` blocks all fields |
| `'relation.*'` | Block direct children only | `disallowedFields('posts.*')` blocks `posts.id`, `posts.title`, but NOT `posts.comments.id` |
| `'relation'` | Block relation and all descendants | `disallowedFields('posts')` blocks `posts`, `posts.id`, `posts.comments.id`, etc. |

```php
// Block all fields/appends
->disallowedFields('*')
->disallowedAppends('*')

// Block only direct children of a relation
->disallowedFields('posts.*')        // posts.id blocked, posts.comments.id allowed
->disallowedAppends('posts.*')

// Block relation and all nested paths (prefix match)
->disallowedFields('posts')          // posts.* AND posts.comments.* blocked
->disallowedIncludes('internal')     // internal AND internal.* blocked
```

> **Note:** Wildcards in `disallowed*()` work independently from `allowed*()`. If you use `allowedFields('*')` (allow everything) combined with `disallowedFields('secret')`, the `secret` field will still be blocked.

### Context-Aware Schemas

Schema methods receive the wizard instance, allowing conditional logic:

```php
use Jackardios\QueryWizard\ModelQueryWizard;

public function filters(QueryWizardInterface $wizard): array
{
    // No filters for ModelQueryWizard (already-loaded models)
    if ($wizard instanceof ModelQueryWizard) {
        return [];
    }

    return [
        EloquentFilter::exact('status'),
        EloquentFilter::partial('name'),
    ];
}
```

## ModelQueryWizard

For processing already-loaded model instances. Handles includes (load missing), fields (hide), and appends only - **not** filters or sorts.

```php
use Jackardios\QueryWizard\ModelQueryWizard;

$user = User::find(1);

$processedUser = ModelQueryWizard::for($user)
    ->allowedIncludes('posts', 'comments')
    ->allowedFields('id', 'name', 'email')
    ->allowedAppends('full_name')
    ->process();
```

**Request:** `?include=posts&fields[user]=id,name&append=full_name`

### With Schema

```php
$processedUser = ModelQueryWizard::for($user)
    ->schema(UserSchema::class)
    ->process();
```

### Behavior

- **Includes:** Loads missing relationships with `loadMissing()`, counts with `loadCount()`
- **Fields:** Hides non-requested fields with `makeHidden()`
- **Appends:** Adds computed attributes with `append()`
- **Filters/Sorts:** Ignored (model is already loaded)

## Laravel Octane Compatibility

Query Wizard is fully compatible with Laravel Octane. The package uses proper scoped bindings and avoids static state that could leak between requests.

### Automatic Handling

- **QueryParametersManager** uses `scoped()` binding, ensuring a fresh instance per request

## Security

### Request Limits

Query Wizard includes built-in protection against resource exhaustion attacks.

| Setting | Default | Description |
|---------|---------|-------------|
| `max_include_depth` | 3 | Max nesting depth (e.g., `posts.comments.author` = 3) |
| `max_includes_count` | 10 | Max includes per request |
| `max_filters_count` | 20 | Max filters per request |
| `max_appends_count` | 10 | Max appends per request |
| `max_sorts_count` | 5 | Max sorts per request |
| `max_append_depth` | 3 | Max append nesting depth |

Configure in `config/query-wizard.php`:

```php
'limits' => [
    'max_include_depth' => 3,      // Stricter limit
    'max_includes_count' => 5,
    'max_filters_count' => 10,
    'max_sorts_count' => 3,
    'max_append_depth' => 2,
],
```

Set any limit to `null` to disable it.

### ScopeFilter Model Binding

By default, `ScopeFilter` passes filter values as-is to your scope methods. If your scope has type-hinted model parameters, you can enable automatic model resolution:

```php
EloquentFilter::scope('byAuthor')->withModelBinding()
```

**Security Warning:** When enabled, model binding resolves instances by ID using `resolveRouteBinding()` **without authorization checks**.

```php
// Scope accepts a User model
public function scopeByAuthor($query, User $author)
{
    return $query->where('author_id', $author->id);
}

// With model binding enabled:
// Request: ?filter[by_author]=123
// User with ID 123 is loaded automatically - ensure authorization in scope!
```

If you enable model binding, add authorization checks in your scope:

```php
public function scopeByAuthor($query, User $author)
{
    abort_unless(auth()->user()->can('view', $author), 403);
    return $query->where('author_id', $author->id);
}
```

## Configuration

Full configuration file (`config/query-wizard.php`):

```php
return [
    /*
     * Query parameter names used in URLs.
     */
    'parameters' => [
        'includes' => 'include',   // ?include=posts,comments
        'filters' => 'filter',     // ?filter[name]=John
        'sorts' => 'sort',         // ?sort=-created_at
        'fields' => 'fields',      // ?fields[users]=id,name
        'appends' => 'append',     // ?append=full_name
    ],

    /*
     * Suffix for count includes.
     * Example: postsCount will load the count of posts relation.
     */
    'count_suffix' => 'Count',

    /*
     * Suffix for exists includes.
     * Example: postsExists will check existence of posts relation.
     */
    'exists_suffix' => 'Exists',

    /*
     * When true, invalid filters/sorts/etc. are silently ignored.
     * When false (default), appropriate exception is thrown.
     */
    'disable_invalid_filter_query_exception' => false,
    'disable_invalid_sort_query_exception' => false,
    'disable_invalid_include_query_exception' => false,
    'disable_invalid_field_query_exception' => false,
    'disable_invalid_append_query_exception' => false,

    /*
     * Where to read query parameters from.
     * Options: 'query_string', 'body'
     */
    'request_data_source' => 'query_string',

    /*
     * Opt-in: apply filter default() when request value is null/empty.
     * Example: ?filter[status]= will use ->default(...) when true.
     */
    'apply_filter_default_on_null' => false,

    /*
     * Separator for array values in query string.
     * Example: ?filter[status]=active,pending
     */
    'array_value_separator' => ',',

    /*
     * Per-parameter-type separators.
     * Allows using different separators for different parameter types.
     * If not set, falls back to 'array_value_separator'.
     */
    'separators' => [
        // 'includes' => ',',
        // 'sorts' => ',',
        // 'fields' => ',',
        // 'appends' => ',',
        // 'filters' => ';',  // Use semicolon to allow commas in filter values
    ],

    /*
     * Naming conversion options.
     */
    'naming' => [
        /*
         * When true, camelCase parameter names are converted to snake_case.
         * Example: ?filter[firstName]=John → filter[first_name]=John
         */
        'convert_parameters_to_snake_case' => false,
    ],

    /*
     * Runtime optimizations for relation field handling.
     * See "Relation Field Modes" section in README for details.
     */
    'optimizations' => [
        'relation_select_mode' => 'safe',  // Recommended: 'safe' or 'off'
    ],

    /*
     * Security limits (set to null to disable).
     */
    'limits' => [
        'max_include_depth' => 3,
        'max_includes_count' => 10,
        'max_filters_count' => 20,
        'max_appends_count' => 10,
        'max_sorts_count' => 5,
        'max_append_depth' => 3,
    ],
];
```

## Error Handling

### Exception Types

| Exception | HTTP | Description |
|-----------|------|-------------|
| `InvalidFilterQuery` | 400 | Unknown filter in request |
| `InvalidSortQuery` | 400 | Unknown sort in request |
| `InvalidIncludeQuery` | 400 | Unknown include in request |
| `InvalidFieldQuery` | 400 | Unknown field in request |
| `InvalidAppendQuery` | 400 | Unknown append in request |
| `MaxFiltersCountExceeded` | 400 | Filter count exceeds limit |
| `MaxSortsCountExceeded` | 400 | Sort count exceeds limit |
| `MaxIncludesCountExceeded` | 400 | Include count exceeds limit |
| `MaxAppendsCountExceeded` | 400 | Append count exceeds limit |
| `MaxIncludeDepthExceeded` | 400 | Include nesting exceeds limit |
| `MaxAppendDepthExceeded` | 400 | Append nesting exceeds limit |

All exceptions extend `InvalidQuery` which extends Symfony's `HttpException`.

### Example Handling

```php
use Jackardios\QueryWizard\Exceptions\InvalidQuery;
use Jackardios\QueryWizard\Exceptions\QueryLimitExceeded;

try {
    $users = EloquentQueryWizard::for(User::class)
        ->allowedFilters('name')
        ->get();
} catch (QueryLimitExceeded $e) {
    return response()->json([
        'error' => 'Query limit exceeded',
        'message' => $e->getMessage(),
    ], 422);
} catch (InvalidQuery $e) {
    return response()->json([
        'error' => 'Invalid query',
        'message' => $e->getMessage(),
    ], $e->getStatusCode());
}
```

### Global Exception Handler

In Laravel 11+ `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (InvalidQuery $e) {
        return response()->json([
            'error' => class_basename($e),
            'message' => $e->getMessage(),
        ], $e->getStatusCode());
    });
})
```

## Batch Processing and Post-Processing

### What is Post-Processing?

After query execution, the wizard applies **post-processing** to results:
- **Root field masking**: Hide fields not in `allowedFields()` (safe select mode)
- **Relation field masking**: Hide fields on related models per `fields[relation]=...`
- **Appends**: Add computed attributes via `append[]=...` or `append[relation]=...`

### Supported Methods

All wizard execution methods apply post-processing automatically:

| Method | Post-Processing | Notes |
|--------|-----------------|-------|
| `get()` | ✅ Automatic | |
| `first()` | ✅ Automatic | |
| `firstOrFail()` | ✅ Automatic | |
| `paginate()` | ✅ Automatic | |
| `simplePaginate()` | ✅ Automatic | |
| `cursorPaginate()` | ✅ Automatic | |
| `chunk()` | ✅ Automatic | |
| `chunkById()` | ✅ Automatic | |
| `cursor()` | ✅ Automatic | Returns LazyCollection with post-processing |
| `lazy()` | ✅ Automatic | Returns LazyCollection with post-processing |
| `find()` | ❌ None | Use `where('id', $id)->first()` instead |

```php
// All these apply post-processing automatically
$wizard = EloquentQueryWizard::for(User::class)
    ->allowedFields('id', 'name', 'posts.id', 'posts.title')
    ->allowedAppends('full_name', 'posts.reading_time')
    ->allowedIncludes('posts');

$wizard->get();                              // ✅ Full post-processing
$wizard->chunk(100, fn($users) => ...);      // ✅ Full post-processing per chunk
$wizard->lazy()->each(fn($user) => ...);     // ✅ Full post-processing per model
$wizard->cursor()->each(fn($user) => ...);   // ✅ Full post-processing per model
```

### Manual Post-Processing

Some `Builder` methods like `find()`, `findMany()`, `sole()` are not wrapped by the wizard. Use `applyPostProcessingTo()` to apply field masking and appends:

```php
$wizard = EloquentQueryWizard::for(User::class)
    ->allowedFields('id', 'name')
    ->allowedAppends('full_name');

// find() is not wrapped — post-processing not applied automatically
$user = $wizard->toQuery()->find($id);
$wizard->applyPostProcessingTo($user);

// Also useful for models from external sources (cache, queue, etc.)
$users = Cache::get('users');
$wizard->applyPostProcessingTo($users);
```

## Configuration Order

`EloquentQueryWizard` proxies unknown method calls to the underlying query builder. Configuration methods (`allowedFilters`, `allowedSorts`, etc.) **must be called before** query builder methods (`where`, `orderBy`, etc.). Violating this order throws a `LogicException`:

```php
// ❌ Throws LogicException
EloquentQueryWizard::for(User::class)
    ->where('active', true)
    ->allowedFilters('name');  // LogicException!
```

The correct order is always: **configuration → builder methods → execution:**

```php
// ✅ Correct
EloquentQueryWizard::for(User::class)
    ->allowedFilters('name')        // configuration
    ->allowedSorts('created_at')    // configuration
    ->where('active', true)         // builder method
    ->get();                        // execution
```

For base query scopes (tenant filtering, soft deletes, etc.), pass a pre-configured query to `for()`:

```php
// ✅ Base scopes via for()
EloquentQueryWizard::for(
    User::where('tenant_id', $tenantId)->withoutGlobalScopes()
)
    ->allowedFilters('name')
    ->allowedSorts('created_at')
    ->get();
```

## API Reference

### EloquentQueryWizard Methods

#### Factory Methods

| Method | Description |
|--------|-------------|
| `for($subject)` | Create from model class, query builder, or relation |
| `forSchema($schema)` | Create from a ResourceSchema class |

#### Configuration Methods

| Method | Description |
|--------|-------------|
| `schema($schema)` | Set ResourceSchema for configuration |
| `allowedFilters(...$filters)` | Set allowed filters |
| `disallowedFilters(...$names)` | Remove filters (supports wildcards: `*`, `relation.*`, `relation`) |
| `allowedSorts(...$sorts)` | Set allowed sorts |
| `disallowedSorts(...$names)` | Remove sorts (supports wildcards: `*`, `relation.*`, `relation`) |
| `defaultSorts(...$sorts)` | Set default sorts |
| `allowedIncludes(...$includes)` | Set allowed includes |
| `disallowedIncludes(...$names)` | Remove includes (supports wildcards: `*`, `relation.*`, `relation`) |
| `defaultIncludes(...$names)` | Set default includes (applied only when include param is absent) |
| `allowedFields(...$fields)` | Set allowed fields (supports wildcards: `*`, `relation.*`) |
| `disallowedFields(...$names)` | Remove fields (supports wildcards: `*`, `relation.*`, `relation`) |
| `defaultFields(...$fields)` | Set default fields (applied when ?fields is absent) |
| `allowedAppends(...$appends)` | Set allowed appends (supports wildcards: `*`, `relation.*`) |
| `disallowedAppends(...$names)` | Remove appends (supports wildcards: `*`, `relation.*`, `relation`) |
| `defaultAppends(...$appends)` | Set default appends |
| `tap(callable $callback)` | Add query modification callback |

#### Execution Methods

| Method | Description |
|--------|-------------|
| `get()` | Execute and return Collection |
| `first()` | Execute and return first result |
| `firstOrFail()` | Execute and return first result or throw exception |
| `paginate($perPage)` | Execute with pagination |
| `simplePaginate($perPage)` | Execute with simple pagination |
| `cursorPaginate($perPage)` | Execute with cursor pagination |
| `toQuery()` | Build and return query builder |
| `getSubject()` | Get underlying query builder |
| `applyPostProcessingTo($results)` | Apply full post-processing (fields + appends) to results |
| `getPassthroughFilters()` | Get passthrough filter values |

### Filter Factory Methods (EloquentFilter)

| Method | Description |
|--------|-------------|
| `exact($property, $alias)` | Exact match filter |
| `partial($property, $alias)` | LIKE search filter |
| `scope($scope, $alias)` | Model scope filter |
| `trashed($alias)` | Soft delete filter |
| `null($property, $alias)` | NULL check filter |
| `range($property, $alias)` | Numeric range filter |
| `dateRange($property, $alias)` | Date range filter |
| `jsonContains($property, $alias)` | JSON contains filter |
| `operator($property, $operator, $alias)` | Operator filter (=, !=, >, >=, <, <=, LIKE, NOT LIKE, DYNAMIC) |
| `callback($name, $callback, $alias)` | Custom callback filter |
| `passthrough($name, $alias)` | Passthrough filter |

### Sort Factory Methods (EloquentSort)

| Method | Description |
|--------|-------------|
| `field($property, $alias)` | Column sort |
| `count($relation, $alias)` | Relationship count sort |
| `relation($relation, $column, $aggregate, $alias)` | Relationship aggregate sort |
| `callback($name, $callback, $alias)` | Custom callback sort |

### Include Factory Methods (EloquentInclude)

| Method | Description |
|--------|-------------|
| `relationship($relation, $alias)` | Eager load relationship |
| `count($relation, $alias)` | Load relationship count |
| `exists($relation, $alias)` | Check relationship existence (adds boolean attribute) |
| `callback($name, $callback, $alias)` | Custom callback include |

## Comparison with spatie/laravel-query-builder

This package is inspired by [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder). Spatie's package is a well-established solution for building Eloquent queries from API requests. Query Wizard builds on the same idea and extends it with additional capabilities.

### Feature Comparison

| Feature | Query Wizard | spatie/laravel-query-builder |
|---------|:---:|:---:|
| **Filters** | | |
| Exact, Partial, Scope, Trashed, Callback | ✅ | ✅ |
| Range (min/max) | ✅ | ❌ |
| Date Range (from/to) | ✅ | ❌ |
| Null (IS NULL / IS NOT NULL) | ✅ | ❌ |
| JSON Contains | ✅ | ❌ |
| Passthrough (capture without applying) | ✅ | ❌ |
| Conditional filters (`when()`) | ✅ | ❌ |
| Value transformation (`prepareValueWith()`) | ✅ | ❌ |
| Operator filter (>, <, >=, <=, DYNAMIC) | ✅ | ✅ |
| BelongsTo filter | ❌ | ✅ |
| BeginsWith / EndsWith | ❌ | ✅ |
| Ignored filter values | ❌ | ✅ |
| Default filter values | ✅ | ✅ |
| Filter aliases | ✅ | ✅ |
| Relation filters (dot notation) | ✅ | ✅ |
| **Sorts** | | |
| Field, Custom/Callback | ✅ | ✅ |
| Relationship count sort | ✅ | ❌ |
| Relationship aggregate sort (sum, avg, max, min) | ✅ | ❌ |
| Default sorts | ✅ | ✅ |
| **Includes** | | |
| Relationship, Count, Custom/Callback | ✅ | ✅ |
| Exists includes | ✅ | ✅ |
| Default includes | ✅ | ❌ |
| Count auto-allowed with relationship | ❌ | ✅ |
| **Fields** | | |
| Field selection | ✅ | ✅ |
| **Appends** | | |
| Appends (computed attributes) | ✅ | ❌ |
| Nested appends (e.g. `posts.reading_time`) | ✅ | ❌ |
| Default appends | ✅ | ❌ |
| **Architecture** | | |
| Resource Schemas (reusable config) | ✅ | ❌ |
| `disallowed*()` methods (schema overrides) | ✅ | ❌ |
| ModelQueryWizard (for loaded models) | ✅ | ❌ |
| `tap()` query modification callbacks | ✅ | ❌ |
| Batch processing with full post-processing | ✅ | ❌ |
| **Security** | | |
| Max include depth | ✅ | ❌ |
| Max count limits (filters, sorts, includes, appends) | ✅ | ❌ |
| **Compatibility** | | |
| Laravel Octane | ✅ | ✅ |

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Salavat Salakhutdinov](https://github.com/jackardios)
- Inspired by [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder) by [Spatie](https://spatie.be)

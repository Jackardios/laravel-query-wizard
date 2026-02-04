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
- Supports custom filter/sort/include implementations
- Works with any Eloquent model or query builder

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
use Jackardios\QueryWizard\QueryWizard;

public function index()
{
    $users = QueryWizard::for(User::class)
        ->setAllowedFilters(['name', 'email', 'status'])
        ->setAllowedSorts(['name', 'created_at'])
        ->setAllowedIncludes(['posts', 'profile'])
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
- [Appending Attributes](#appending-attributes)
- [API Reference](#api-reference)
- [Resource Schemas](#resource-schemas)
- [Security](#security)
- [Custom Drivers](#custom-drivers)
- [Configuration](#configuration)
- [Error Handling](#error-handling)
- [Laravel Octane Compatibility](#laravel-octane-compatibility)

## Basic Usage

### Creating a Query Wizard

```php
use Jackardios\QueryWizard\QueryWizard;

// From a model class
$wizard = QueryWizard::for(User::class);

// From an existing query builder
$wizard = QueryWizard::for(User::where('active', true));

// From a relation
$wizard = QueryWizard::for($user->posts());

// Using a specific driver
$wizard = QueryWizard::using('eloquent', User::class);
```

### Executing Queries

```php
// Get all results
$users = $wizard->get();

// Get first result
$user = $wizard->first();

// Paginate results
$users = $wizard->paginate(15);
$users = $wizard->simplePaginate(15);
$users = $wizard->cursorPaginate(15);

// Access the underlying query builder
$query = $wizard->build();
```

### Modifying the Query

```php
QueryWizard::for(User::class)
    ->setAllowedFilters(['name'])
    ->modifyQuery(function ($query) {
        $query->where('active', true)
              ->whereNotNull('email_verified_at');
    })
    ->get();
```

## Filtering

Filters allow API consumers to narrow down results based on specific criteria.

### Basic Filters

```php
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\FilterDefinition;

QueryWizard::for(User::class)
    ->setAllowedFilters([
        'name',           // Exact match filter (shorthand)
        'email',          // Exact match filter (shorthand)
        FilterDefinition::exact('status'),
        FilterDefinition::partial('bio'),
    ])
    ->get();
```

**Request:** `GET /users?filter[name]=John&filter[bio]=developer`

### Available Filter Types

#### Exact Filter

Matches exact values. Supports arrays for `IN` queries.

```php
FilterDefinition::exact('status')
FilterDefinition::exact('category_id')

// With alias (use different name in URL)
FilterDefinition::exact('user_id', 'user')  // ?filter[user]=5
```

**Request:** `?filter[status]=active` or `?filter[status]=active,pending` (IN query)

#### Partial Filter

Case-insensitive LIKE search.

```php
FilterDefinition::partial('name')
FilterDefinition::partial('description')
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
FilterDefinition::scope('popular')
```

**Request:** `?filter[popular]=5000`

#### Callback Filter

Custom filtering logic.

```php
FilterDefinition::callback('age_range', function ($query, $value, $property) {
    [$min, $max] = explode('-', $value);
    $query->whereBetween('age', [(int) $min, (int) $max]);
})
```

**Request:** `?filter[age_range]=18-35`

#### Trashed Filter

Filter soft-deleted models.

```php
FilterDefinition::trashed()
```

**Request:** `?filter[trashed]=with` (include trashed), `?filter[trashed]=only` (only trashed)

#### Range Filter

Filter by numeric ranges.

```php
FilterDefinition::range('price')
FilterDefinition::range('price')->withOptions([
    'minKey' => 'from',
    'maxKey' => 'to',
])
```

**Request:** `?filter[price][min]=100&filter[price][max]=500`

#### Date Range Filter

Filter by date ranges.

```php
FilterDefinition::dateRange('created_at')
FilterDefinition::dateRange('created_at')->withOptions([
    'fromKey' => 'start',
    'toKey' => 'end',
    'dateFormat' => 'Y-m-d',
])
```

**Request:** `?filter[created_at][from]=2024-01-01&filter[created_at][to]=2024-12-31`

#### Null Filter

Check for null/not null values.

```php
FilterDefinition::null('deleted_at')
FilterDefinition::null('email')->withOptions(['invertLogic' => true])
```

**Request:** `?filter[deleted_at]=1` (is null), `?filter[deleted_at]=0` (is not null)

#### JSON Contains Filter

Filter JSON columns.

```php
FilterDefinition::jsonContains('meta.tags')
FilterDefinition::jsonContains('settings.roles')->withOptions([
    'matchAll' => false,  // Match any vs match all
])
```

**Request:** `?filter[meta.tags]=laravel,php`

#### Passthrough Filter

Capture filter values without applying them to the query. Useful when you need to handle filtering logic manually (e.g., for external services).

```php
QueryWizard::for(User::class)
    ->setAllowedFilters([
        FilterDefinition::passthrough('external_id'),
    ])
    ->get();

// Access passthrough values
$wizard = QueryWizard::for(User::class)
    ->setAllowedFilters([FilterDefinition::passthrough('search')]);

$passthroughFilters = $wizard->getPassthroughFilters();
// ['search' => 'query value']
```

### Filter Options

#### Default Values

```php
FilterDefinition::exact('status')->default('active')
```

#### Prepare Values

Transform filter values before applying:

```php
FilterDefinition::exact('email')->prepareValueWith(fn($value) => strtolower($value))
```

#### Relation Filtering

Filters with dot notation automatically use `whereHas`:

```php
FilterDefinition::exact('posts.status')  // Filters users by their posts' status
```

Disable this behavior:

```php
FilterDefinition::exact('posts.status')->withRelationConstraint(false)
```

## Sorting

Allow API consumers to sort results.

### Basic Sorts

```php
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\SortDefinition;

QueryWizard::for(User::class)
    ->setAllowedSorts([
        'name',            // Field sort (shorthand)
        'created_at',      // Field sort (shorthand)
        SortDefinition::field('email'),
    ])
    ->get();
```

**Request:** `?sort=name` (ascending), `?sort=-name` (descending), `?sort=-created_at,name` (multiple)

### Available Sort Types

#### Field Sort

Sort by a database column.

```php
SortDefinition::field('created_at')
SortDefinition::field('created_at', 'date')  // Alias: ?sort=-date
```

#### Callback Sort

Custom sorting logic.

```php
SortDefinition::callback('popularity', function ($query, $direction, $property) {
    $query->orderByRaw("(likes_count + comments_count * 2) {$direction}");
})
```

### Default Sorts

```php
QueryWizard::for(User::class)
    ->setAllowedSorts(['name', 'created_at'])
    ->setDefaultSorts('-created_at')  // Applied when no sort in request
    ->get();
```

## Including Relationships

Eager load relationships based on request parameters.

### Basic Includes

```php
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\IncludeDefinition;

QueryWizard::for(User::class)
    ->setAllowedIncludes([
        'posts',           // Relationship include (shorthand)
        'profile',         // Relationship include (shorthand)
        'postsCount',      // Count include (auto-detected by suffix)
        IncludeDefinition::relationship('comments'),
        IncludeDefinition::count('followers'),
    ])
    ->get();
```

**Request:** `?include=posts,profile,postsCount`

### Available Include Types

#### Relationship Include

Eager load a relationship.

```php
IncludeDefinition::relationship('posts')
IncludeDefinition::relationship('posts.author')  // Nested relationships
```

#### Count Include

Load relationship counts (uses `withCount`).

```php
IncludeDefinition::count('posts')
IncludeDefinition::count('posts', 'postCount')  // Custom alias
```

Includes ending with "Count" (configurable) are auto-detected:

```php
->setAllowedIncludes(['posts', 'postsCount'])  // postsCount becomes count include
```

#### Callback Include

Custom include logic.

```php
IncludeDefinition::callback('recent_posts', function ($query, $include, $fields) {
    $query->with(['posts' => function ($q) {
        $q->where('created_at', '>', now()->subMonth())
          ->orderBy('created_at', 'desc')
          ->limit(5);
    }]);
})
```

### Default Includes

```php
QueryWizard::for(User::class)
    ->setAllowedIncludes(['posts', 'profile', 'settings'])
    ->setDefaultIncludes('profile')  // Always loaded unless overridden
    ->get();
```

## Selecting Fields

Allow sparse fieldsets (JSON:API compatible).

```php
QueryWizard::for(User::class)
    ->setAllowedFields([
        'id', 'name', 'email',      // Root model fields
        'posts.id', 'posts.title',   // Related model fields
    ])
    ->get();
```

**Request:** `?fields[users]=id,name&fields[posts]=id,title`

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
QueryWizard::for(User::class)
    ->setAllowedAppends(['full_name', 'posts.author_name'])
    ->get();
```

**Request:** `?append=full_name,posts.author_name`

### Nested Appends

Append attributes on related models:

```php
->setAllowedAppends([
    'full_name',              // Root model
    'posts.reading_time',     // Related posts
    'posts.author.badge',     // Deeply nested
])
```

### Wildcard Appends

Allow any appends on a relation:

```php
->setAllowedAppends(['posts.*'])  // Any append on posts
```

## API Reference

This section provides a quick reference for all available methods on filters, includes, and sorts.

### Filter Methods

All filters inherit these methods from `AbstractFilter`:

| Method | Description | Example |
|--------|-------------|---------|
| `->alias(string)` | Use different name in URL | `exact('user_id', 'user')` or `->alias('user')` |
| `->default(mixed)` | Default value when not in request | `->default('active')` |
| `->prepareValueWith(Closure)` | Transform value before applying | `->prepareValueWith(fn($v) => strtolower($v))` |

### Filter-Specific Methods

| Filter | Method | Description | Example |
|--------|--------|-------------|---------|
| `exact`, `partial` | `->withRelationConstraint(bool)` | Enable/disable auto `whereHas` for `relation.column` | `->withRelationConstraint(false)` |
| `scope` | `->resolveModelBindings(bool)` | Auto-resolve model bindings in scope parameters | `->resolveModelBindings(false)` |
| `range` | `->keys(string $min, string $max)` | Custom key names (default: 'min', 'max') | `->keys('from', 'to')` |
| `dateRange` | `->keys(string $from, string $to)` | Custom key names (default: 'from', 'to') | `->keys('start', 'end')` |
| `dateRange` | `->dateFormat(string)` | Date format for DateTime objects | `->dateFormat('Y-m-d')` |
| `null` | `->invertLogic(bool)` | Invert null check (true → NOT NULL) | `->invertLogic()` |
| `jsonContains` | `->matchAll(bool)` | Match all values (AND) vs any (OR) | `->matchAll(false)` |

### Filter Types Summary

| Type | Factory Method | Request Format | SQL Generated |
|------|---------------|----------------|---------------|
| Exact | `FilterDefinition::exact('col')` | `?filter[col]=value` | `WHERE col = 'value'` |
| Exact (array) | `FilterDefinition::exact('col')` | `?filter[col]=a,b` | `WHERE col IN ('a', 'b')` |
| Partial | `FilterDefinition::partial('col')` | `?filter[col]=val` | `WHERE LOWER(col) LIKE '%val%'` |
| Scope | `FilterDefinition::scope('name')` | `?filter[name]=arg` | Calls `scopeName($query, 'arg')` |
| Callback | `FilterDefinition::callback('n', fn)` | `?filter[n]=val` | Custom logic |
| Range | `FilterDefinition::range('col')` | `?filter[col][min]=1&[max]=10` | `WHERE col >= 1 AND col <= 10` |
| Date Range | `FilterDefinition::dateRange('col')` | `?filter[col][from]=...&[to]=...` | `WHERE col >= ... AND col <= ...` |
| Null | `FilterDefinition::null('col')` | `?filter[col]=true` | `WHERE col IS NULL` |
| JSON Contains | `FilterDefinition::jsonContains('col')` | `?filter[col]=a,b` | `whereJsonContains` for each |
| Trashed | `FilterDefinition::trashed()` | `?filter[trashed]=with` | `withTrashed()` / `onlyTrashed()` |
| Passthrough | `FilterDefinition::passthrough('n')` | `?filter[n]=val` | No SQL (value captured) |

### Include Methods

All includes inherit these methods from `AbstractInclude`:

| Method | Description | Example |
|--------|-------------|---------|
| `->alias(string)` | Use different name in URL | `relationship('posts', 'articles')` or `->alias('articles')` |

### Include Types Summary

| Type | Factory Method | Request Format | Eloquent Method |
|------|---------------|----------------|-----------------|
| Relationship | `IncludeDefinition::relationship('rel')` | `?include=rel` | `with('rel')` |
| Count | `IncludeDefinition::count('rel')` | `?include=relCount` | `withCount('rel')` |
| Callback | `IncludeDefinition::callback('n', fn)` | `?include=n` | Custom logic |

### Sort Methods

All sorts inherit these methods from `AbstractSort`:

| Method | Description | Example |
|--------|-------------|---------|
| `->alias(string)` | Use different name in URL | `field('created_at', 'date')` or `->alias('date')` |

### Sort Types Summary

| Type | Factory Method | Request Format | SQL Generated |
|------|---------------|----------------|---------------|
| Field | `SortDefinition::field('col')` | `?sort=col` / `?sort=-col` | `ORDER BY col ASC/DESC` |
| Callback | `SortDefinition::callback('n', fn)` | `?sort=n` / `?sort=-n` | Custom logic |

## Resource Schemas

For larger applications, use Resource Schemas to define all query capabilities in one place.

### Creating a Schema

```php
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\FilterDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\SortDefinition;

class UserSchema extends ResourceSchema
{
    public function model(): string
    {
        return \App\Models\User::class;
    }

    public function filters(): array
    {
        return [
            'name',
            FilterDefinition::partial('email'),
            FilterDefinition::exact('status'),
            FilterDefinition::scope('popular'),
            FilterDefinition::trashed(),
        ];
    }

    public function sorts(): array
    {
        return [
            'name',
            'created_at',
            SortDefinition::callback('popularity', function ($query, $direction) {
                $query->orderBy('followers_count', $direction);
            }),
        ];
    }

    public function includes(): array
    {
        return ['posts', 'profile', 'postsCount'];
    }

    public function fields(): array
    {
        return ['id', 'name', 'email', 'status', 'created_at'];
    }

    public function appends(): array
    {
        return ['full_name', 'avatar_url'];
    }

    public function defaultSorts(): array
    {
        return ['-created_at'];
    }

    public function defaultIncludes(): array
    {
        return ['profile'];
    }
}
```

### Using Schemas

```php
use Jackardios\QueryWizard\QueryWizard;

// List query
$users = QueryWizard::forList(UserSchema::class)->get();

// Item query (single resource)
$user = QueryWizard::forItem(UserSchema::class, $userId)->first();
$user = QueryWizard::forItem(UserSchema::class, $loadedUser)->first();
```

### Context-Based Customization

Override schema settings for different contexts (list vs item):

```php
use Jackardios\QueryWizard\Schema\SchemaContext;
use Jackardios\QueryWizard\Contracts\SchemaContextInterface;

class UserSchema extends ResourceSchema
{
    // ... other methods

    public function forList(): ?SchemaContextInterface
    {
        return SchemaContext::make()
            ->setDisallowedIncludes(['sensitiveRelation'])
            ->setDefaultSorts(['-created_at']);
    }

    public function forItem(): ?SchemaContextInterface
    {
        return SchemaContext::make()
            ->setAllowedIncludes(['profile', 'posts', 'settings'])
            ->setDisallowedFields(['password_hash']);
    }
}
```

### SchemaContext Methods

| Method | Description |
|--------|-------------|
| `setAllowedFilters(array)` | Override allowed filters |
| `setAllowedSorts(array)` | Override allowed sorts |
| `setAllowedIncludes(array)` | Override allowed includes |
| `setAllowedFields(array)` | Override allowed fields |
| `setAllowedAppends(array)` | Override allowed appends |
| `setDisallowedFilters(array)` | Remove specific filters from allowed list |
| `setDisallowedSorts(array)` | Remove specific sorts from allowed list |
| `setDisallowedIncludes(array)` | Remove specific includes from allowed list |
| `setDisallowedFields(array)` | Remove specific fields from allowed list |
| `setDisallowedAppends(array)` | Remove specific appends from allowed list |
| `setDefaultFields(array)` | Set default fields |
| `setDefaultSorts(array)` | Set default sorts |
| `setDefaultIncludes(array)` | Set default includes |
| `setDefaultAppends(array)` | Set default appends |

## Security

### Request Limits

Query Wizard includes built-in protection against resource exhaustion attacks. Malicious users could attempt to overload your server by requesting deeply nested includes or excessive numbers of filters/sorts.

#### Default Limits

| Setting | Default | Description |
|---------|---------|-------------|
| `max_include_depth` | 5 | Maximum nesting depth for includes (e.g., `posts.comments.author` = depth 3) |
| `max_includes_count` | 10 | Maximum number of includes per request |
| `max_filters_count` | 15 | Maximum number of filters per request |
| `max_filter_depth` | 5 | Maximum nesting depth for filters |
| `max_sorts_count` | 5 | Maximum number of sorts per request |

#### Configuring Limits

In your `config/query-wizard.php`:

```php
'limits' => [
    'max_include_depth' => 3,      // Stricter limit
    'max_includes_count' => 5,     // Fewer includes allowed
    'max_filters_count' => 10,
    'max_filter_depth' => 3,
    'max_sorts_count' => 3,
],
```

Set any limit to `null` to disable it:

```php
'limits' => [
    'max_include_depth' => null,   // No depth limit
],
```

### Unsupported Capability Behavior

Configure how the package behaves when a driver doesn't support a requested capability (e.g., filters on a driver that only supports sorting):

```php
// config/query-wizard.php
'unsupported_capability_behavior' => 'exception', // default
```

| Value | Behavior |
|-------|----------|
| `'exception'` | Throws `UnsupportedCapability` exception |
| `'log'` | Logs a warning and continues |
| `'silent'` | Silently ignores the unsupported capability |

## Custom Drivers

The driver system allows complete customization of how queries are built. You can create drivers for different data sources (Scout, Meilisearch, etc.) or customize the Eloquent behavior.

### Driver Methods Reference

When extending `AbstractDriver`, you must implement these methods:

| Method | Purpose |
|--------|---------|
| `name()` | Unique driver identifier (e.g., `'scout'`, `'meilisearch'`) |
| `supports($subject)` | Return `true` if driver can handle this subject type |
| `capabilities()` | Return array of supported `Capability` enum values |
| `normalizeFilter($filter)` | Convert string to `FilterInterface` (e.g., `'name'` → `ExactFilter`) |
| `normalizeInclude($include)` | Convert string to `IncludeInterface` |
| `normalizeSort($sort)` | Convert string to `SortInterface` |
| `applyFilter($subject, $filter, $value)` | Apply filter to query subject, return modified subject |
| `applyInclude($subject, $include, $fields)` | Apply include to query subject, return modified subject |
| `applySort($subject, $sort, $direction)` | Apply sort to query subject, return modified subject |
| `applyFields($subject, $fields)` | Apply field selection to subject, return modified subject |
| `applyAppends($result, $appends)` | Apply appends to **query result** (not subject!), return modified result |
| `getResourceKey($subject)` | Return key for sparse fieldsets (e.g., `'users'` for `?fields[users]=id,name`) |
| `prepareSubject($subject)` | Transform subject before query execution (e.g., class-string → Builder) |

`AbstractDriver` automatically provides `supportsFilterType()`, `supportsSortType()`, `supportsIncludeType()` based on the `$supportedFilterTypes`, `$supportedSortTypes`, `$supportedIncludeTypes` arrays.

### Creating a Custom Driver

Extend `AbstractDriver` for the easiest implementation:

```php
use Jackardios\QueryWizard\Drivers\AbstractDriver;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Enums\Capability;

class ScoutDriver extends AbstractDriver
{
    // Declare supported types - AbstractDriver handles supportsFilterType(), etc.
    protected array $supportedFilterTypes = ['exact', 'callback'];
    protected array $supportedSortTypes = ['field', 'callback'];
    protected array $supportedIncludeTypes = ['relationship', 'count', 'callback'];

    public function name(): string { return 'scout'; }

    public function supports(mixed $subject): bool
    {
        return $subject instanceof \Laravel\Scout\Builder;
    }

    public function capabilities(): array
    {
        // Scout supports includes/fields/appends via query() callback
        return Capability::values(); // All capabilities
    }

    public function normalizeFilter(FilterInterface|string $filter): FilterInterface { ... }
    public function normalizeSort(SortInterface|string $sort): SortInterface { ... }
    public function normalizeInclude(IncludeInterface|string $include): IncludeInterface { ... }

    public function applyFilter(mixed $subject, FilterInterface $filter, mixed $value): mixed
    {
        // Scout filters apply directly to Scout\Builder
        return $filter->apply($subject, $value);
    }

    public function applySort(mixed $subject, SortInterface $sort, string $direction): mixed
    {
        return $sort->apply($subject, $direction);
    }

    public function applyInclude(mixed $subject, IncludeInterface $include, array $fields = []): mixed
    {
        // Use Scout's query() to access underlying Eloquent builder
        $subject->query(fn ($query) => $include->apply($query, $fields));
        return $subject;
    }

    public function applyFields(mixed $subject, array $fields): mixed
    {
        $subject->query(fn ($query) => $query->select($fields));
        return $subject;
    }

    public function applyAppends(mixed $result, array $appends): mixed { ... }
    public function getResourceKey(mixed $subject): string { ... }
    public function prepareSubject(mixed $subject): mixed { return $subject; }
}
```

### Creating Custom Filters

Implement `FilterInterface` for custom filter types:

```php
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Filters\AbstractFilter;

class ScoutExactFilter extends AbstractFilter
{
    public function getType(): string
    {
        return 'exact';
    }

    public function apply(mixed $subject, mixed $value): mixed
    {
        // $subject is Scout\Builder
        return $subject->where($this->getProperty(), $value);
    }
}
```

### Registering a Custom Driver

In `config/query-wizard.php`:

```php
return [
    'drivers' => [
        'scout' => \App\QueryWizard\Drivers\ScoutDriver::class,
    ],
];
```

### Using a Custom Driver

```php
// Explicit driver usage
$results = QueryWizard::using('scout', User::search('query'))
    ->setAllowedFilters(['category', 'status'])
    ->setAllowedSorts(['relevance', 'created_at'])
    ->get();

// In a schema
class UserSchema extends ResourceSchema
{
    public function driver(): string
    {
        return 'scout';
    }
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
     * When true, invalid filters are silently ignored.
     * When false (default), InvalidFilterQuery exception is thrown.
     */
    'disable_invalid_filter_query_exception' => false,

    /*
     * Where to read query parameters from.
     * Options: 'query_string', 'body'
     */
    'request_data_source' => 'query_string',

    /*
     * Separator for array values in query string.
     * Example: ?filter[status]=active,pending
     */
    'array_value_separator' => ',',

    /*
     * Custom drivers to register.
     */
    'drivers' => [
        // 'scout' => \App\QueryWizard\Drivers\ScoutDriver::class,
    ],

    /*
     * Security limits to protect against resource exhaustion attacks.
     * Set to null to disable a specific limit.
     */
    'limits' => [
        'max_include_depth' => 5,
        'max_includes_count' => 10,
        'max_filters_count' => 15,
        'max_filter_depth' => 5,
        'max_sorts_count' => 5,
    ],

    /*
     * Behavior when requesting a capability that the driver doesn't support.
     * Options: 'exception' (throws), 'log' (warning), 'silent' (ignore)
     */
    'unsupported_capability_behavior' => 'exception',
];
```

## Error Handling

Query Wizard throws descriptive exceptions for invalid queries.

### Validation Exceptions

| Exception | Description |
|-----------|-------------|
| `InvalidFilterQuery` | Unknown filter in request |
| `InvalidSortQuery` | Unknown sort in request |
| `InvalidIncludeQuery` | Unknown include in request |
| `InvalidFieldQuery` | Unknown field in request |
| `InvalidAppendQuery` | Unknown append in request |

### Security Limit Exceptions

| Exception | Description |
|-----------|-------------|
| `MaxIncludeDepthExceeded` | Include nesting exceeds `max_include_depth` |
| `MaxIncludesCountExceeded` | Include count exceeds `max_includes_count` |
| `MaxFiltersCountExceeded` | Filter count exceeds `max_filters_count` |
| `MaxSortsCountExceeded` | Sort count exceeds `max_sorts_count` |

### Capability Exceptions

| Exception | Description |
|-----------|-------------|
| `UnsupportedCapability` | Driver doesn't support requested capability |

All exceptions extend `InvalidQuery` (which extends Symfony's `HttpException` with 400 status), except `UnsupportedCapability` which extends `LogicException`.

### Example Handling

```php
use Jackardios\QueryWizard\Exceptions\InvalidQuery;
use Jackardios\QueryWizard\Exceptions\QueryLimitExceeded;
use Jackardios\QueryWizard\Exceptions\UnsupportedCapability;

try {
    $users = QueryWizard::for(User::class)
        ->setAllowedFilters(['name'])
        ->setAllowedSorts(['created_at'])
        ->get();
} catch (QueryLimitExceeded $e) {
    return response()->json([
        'error' => 'Query limit exceeded',
        'message' => $e->getMessage(),
    ], 400);
} catch (UnsupportedCapability $e) {
    return response()->json([
        'error' => 'Unsupported capability',
        'capability' => $e->capability,
        'driver' => $e->driverName,
    ], 400);
} catch (InvalidQuery $e) {
    return response()->json([
        'error' => 'Invalid query',
        'message' => $e->getMessage(),
    ], 400);
}
```

### Global Exception Handler

In `app/Exceptions/Handler.php`:

```php
use Jackardios\QueryWizard\Exceptions\InvalidQuery;

public function register(): void
{
    $this->renderable(function (InvalidQuery $e) {
        return response()->json([
            'error' => class_basename($e),
            'message' => $e->getMessage(),
        ], $e->getStatusCode());
    });
}
```

## Laravel Octane Compatibility

This package is fully compatible with Laravel Octane. The architecture avoids state leakage between requests:

- **DriverRegistry** is registered as a singleton, properly isolated per Octane worker
- **QueryParametersManager** is created fresh per request
- **Reflection caches** use `WeakMap` for automatic cleanup

No additional configuration is required for Octane compatibility.

### Best Practices for Callbacks

When using callback filters, sorts, or includes, avoid capturing request-specific values in closures:

```php
// AVOID - captures user from first request
$user = auth()->user();
FilterDefinition::callback('owned', fn($q, $v) => $q->where('user_id', $user->id));

// CORRECT - fetches user on each request
FilterDefinition::callback('owned', fn($q, $v) => $q->where('user_id', auth()->id()));
```

## Testing

```bash
composer test
```

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Salavat Salakhutdinov](https://github.com/jackardios)

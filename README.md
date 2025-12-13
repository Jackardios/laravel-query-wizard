# Laravel Query Wizard

Build Eloquent queries from API request parameters with ease. Filter, sort, include relationships, select fields, and append computed attributes - all from query string parameters.

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
- Supports custom strategies for complete flexibility
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

// In your controller
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
- [Resource Schemas](#resource-schemas)
- [Custom Drivers](#custom-drivers)
- [Configuration](#configuration)
- [Laravel Octane Compatibility](#laravel-octane-compatibility)

## Basic Usage

### Creating a Query Wizard

There are several ways to create a Query Wizard:

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

### Default Fields

```php
QueryWizard::for(User::class)
    ->setAllowedFields(['id', 'name', 'email', 'password'])
    ->setDefaultFields(['id', 'name', 'email'])  // Exclude password by default
    ->get();
```

Use `['*']` to select all fields by default.

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
            ->disallowIncludes(['sensitiveRelation'])
            ->defaultSorts(['-created_at']);
    }

    public function forItem(): ?SchemaContextInterface
    {
        return SchemaContext::make()
            ->allowIncludes(['profile', 'posts', 'settings'])
            ->disallowFields(['password_hash']);
    }
}
```

## Custom Drivers

The driver system allows complete customization of how queries are built. You can create drivers for different data sources (Scout, Meilisearch, etc.) or customize the Eloquent behavior.

### Creating a Custom Driver

```php
use Jackardios\QueryWizard\Contracts\DriverInterface;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;

class ScoutDriver implements DriverInterface
{
    public function name(): string
    {
        return 'scout';
    }

    public function supports(mixed $subject): bool
    {
        // Define what subjects this driver can handle
        return $subject instanceof \Laravel\Scout\Builder;
    }

    public function capabilities(): array
    {
        // Only filters and sorts make sense for Scout
        return ['filters', 'sorts'];
    }

    public function normalizeFilter(FilterDefinitionInterface|string $filter): FilterDefinitionInterface
    {
        // Convert string filters to FilterDefinition objects
        if ($filter instanceof FilterDefinitionInterface) {
            return $filter;
        }
        return FilterDefinition::exact($filter);
    }

    public function normalizeInclude(IncludeDefinitionInterface|string $include): IncludeDefinitionInterface
    {
        // Implement based on your needs
    }

    public function normalizeSort(SortDefinitionInterface|string $sort): SortDefinitionInterface
    {
        // Implement based on your needs
    }

    public function applyFilter(mixed $subject, FilterDefinitionInterface $filter, mixed $value): mixed
    {
        // Apply filter to Scout builder
        return $subject->where($filter->getProperty(), $value);
    }

    public function applyInclude(mixed $subject, IncludeDefinitionInterface $include, array $fields = []): mixed
    {
        // Not supported for Scout
        return $subject;
    }

    public function applySort(mixed $subject, SortDefinitionInterface $sort, string $direction): mixed
    {
        // Apply sort to Scout builder
        return $subject->orderBy($sort->getProperty(), $direction);
    }

    public function applyFields(mixed $subject, array $fields): mixed
    {
        // Not typically supported for Scout
        return $subject;
    }

    public function applyAppends(mixed $result, array $appends): mixed
    {
        // Apply appends to results after fetching
        foreach ($result as $model) {
            $model->append($appends);
        }
        return $result;
    }

    public function getResourceKey(mixed $subject): string
    {
        return $subject->model->getTable();
    }

    public function prepareSubject(mixed $subject): mixed
    {
        return $subject;
    }

    public function supportsFilterType(string $type): bool
    {
        return in_array($type, ['exact', 'partial']);
    }

    public function supportsIncludeType(string $type): bool
    {
        return false;
    }

    public function supportsSortType(string $type): bool
    {
        return $type === 'field';
    }

    public function getSupportedFilterTypes(): array
    {
        return ['exact', 'partial'];
    }

    public function getSupportedIncludeTypes(): array
    {
        return [];
    }

    public function getSupportedSortTypes(): array
    {
        return ['field'];
    }
}
```

### Registering a Custom Driver

In your config file (`config/query-wizard.php`):

```php
return [
    // ...
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

    // ...
}
```

### Custom Filter Strategies

Create custom filter strategies for the Eloquent driver:

```php
use Jackardios\QueryWizard\Contracts\FilterStrategyInterface;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Illuminate\Database\Eloquent\Builder;

class FullTextSearchFilterStrategy implements FilterStrategyInterface
{
    public function apply(mixed $subject, FilterDefinitionInterface $filter, mixed $value): mixed
    {
        /** @var Builder $subject */
        $columns = $filter->getOption('columns', [$filter->getProperty()]);

        return $subject->whereFullText($columns, $value);
    }
}
```

Use it with `FilterDefinition::custom()`:

```php
FilterDefinition::custom('search', FullTextSearchFilterStrategy::class)
    ->withOptions(['columns' => ['title', 'body', 'tags']])
```

Or register it globally on the driver:

```php
use Jackardios\QueryWizard\Drivers\DriverRegistry;

$driver = app(DriverRegistry::class)->get('eloquent');
$driver->registerFilterStrategy('fulltext', FullTextSearchFilterStrategy::class);
```

### Custom Sort Strategies

```php
use Jackardios\QueryWizard\Contracts\SortStrategyInterface;
use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;

class RandomSortStrategy implements SortStrategyInterface
{
    public function apply(mixed $subject, SortDefinitionInterface $sort, string $direction): mixed
    {
        return $subject->inRandomOrder();
    }
}
```

Use with:
```php
SortDefinition::custom('random', RandomSortStrategy::class)
```

### Custom Include Strategies

```php
use Jackardios\QueryWizard\Contracts\IncludeStrategyInterface;
use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;

class CachedRelationIncludeStrategy implements IncludeStrategyInterface
{
    public function apply(mixed $subject, IncludeDefinitionInterface $include, array $fields = []): mixed
    {
        $relation = $include->getRelation();

        return $subject->with([
            $relation => function ($query) use ($fields) {
                if (!empty($fields)) {
                    $query->select($fields);
                }
                // Add your caching logic here
            }
        ]);
    }
}
```

Use with:
```php
IncludeDefinition::custom('posts', CachedRelationIncludeStrategy::class)
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
];
```

## Error Handling

Query Wizard throws descriptive exceptions for invalid queries:

| Exception | Description |
|-----------|-------------|
| `InvalidFilterQuery` | Unknown filter in request |
| `InvalidSortQuery` | Unknown sort in request |
| `InvalidIncludeQuery` | Unknown include in request |
| `InvalidFieldQuery` | Unknown field in request |
| `InvalidAppendQuery` | Unknown append in request |

Example handling:

```php
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;

try {
    $users = QueryWizard::for(User::class)
        ->setAllowedFilters(['name'])
        ->setAllowedSorts(['created_at'])
        ->get();
} catch (InvalidFilterQuery $e) {
    return response()->json([
        'error' => 'Invalid filter',
        'message' => $e->getMessage(),
    ], 400);
} catch (InvalidSortQuery $e) {
    return response()->json([
        'error' => 'Invalid sort',
        'message' => $e->getMessage(),
    ], 400);
}
```

## Laravel Octane Compatibility

This package is fully compatible with Laravel Octane. The architecture is designed to avoid state leakage between requests:

- **DriverRegistry** is registered as a singleton in the Laravel container, properly isolated per Octane worker
- **QueryParametersManager** is created fresh per request via `bind()` registration
- **Reflection caches** use `WeakMap` for automatic cleanup when model instances are garbage collected

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

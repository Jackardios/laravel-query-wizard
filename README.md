# Laravel Query Wizard

Build Eloquent queries from API request parameters. Filter, sort, include relationships, select fields, and append computed attributes — all from query string parameters.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jackardios/laravel-query-wizard.svg)](https://packagist.org/packages/jackardios/laravel-query-wizard)
[![License](https://img.shields.io/packagist/l/jackardios/laravel-query-wizard.svg)](https://packagist.org/packages/jackardios/laravel-query-wizard)
[![CI](https://github.com/jackardios/laravel-query-wizard/actions/workflows/ci.yml/badge.svg)](https://github.com/jackardios/laravel-query-wizard/actions)

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
- [Appending Attributes](#appending-attributes)
- [Resource Schemas](#resource-schemas)
- [ModelQueryWizard](#modelquerywizard)
- [Security](#security)
- [Configuration](#configuration)
- [Error Handling](#error-handling)
- [Advanced Usage](#advanced-usage)
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
$user = $wizard->firstOrFail();

// Paginate results
$users = $wizard->paginate(15);
$users = $wizard->simplePaginate(15);
$users = $wizard->cursorPaginate(15);

// Get the underlying query builder
$query = $wizard->toQuery();
```

### Configuration Order

Configuration methods (`allowedFilters`, `allowedSorts`, etc.) **must be called before** query builder methods (`where`, `orderBy`, etc.):

```php
// ✅ Correct: configuration → builder methods → execution
EloquentQueryWizard::for(User::class)
    ->allowedFilters('name')        // configuration
    ->allowedSorts('created_at')    // configuration
    ->where('active', true)         // builder method
    ->get();                        // execution

// ❌ Wrong: throws LogicException
EloquentQueryWizard::for(User::class)
    ->where('active', true)
    ->allowedFilters('name');       // LogicException!
```

For base query scopes, pass a pre-configured query to `for()`:

```php
EloquentQueryWizard::for(User::where('active', true))
    ->allowedFilters('name')
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

| Type | Factory | Request Example |
|------|---------|-----------------|
| Exact | `EloquentFilter::exact('status')` | `?filter[status]=active` |
| Partial | `EloquentFilter::partial('name')` | `?filter[name]=john` (LIKE %john%) |
| Scope | `EloquentFilter::scope('popular')` | `?filter[popular]=5000` |
| Trashed | `EloquentFilter::trashed()` | `?filter[trashed]=with\|only` |
| Null | `EloquentFilter::null('deleted_at')` | `?filter[deleted_at]=true` (IS NULL) |
| Range | `EloquentFilter::range('price')` | `?filter[price][min]=10&filter[price][max]=100` |
| Date Range | `EloquentFilter::dateRange('created_at')` | `?filter[created_at][from]=2024-01-01&filter[created_at][to]=2024-12-31` |
| JSON Contains | `EloquentFilter::jsonContains('tags')` | `?filter[tags]=laravel,php` |
| Operator | `EloquentFilter::operator('age', FilterOperator::GREATER_THAN)` | `?filter[age]=18` (age > 18) |
| Operator (dynamic) | `EloquentFilter::operator('price', FilterOperator::DYNAMIC)` | `?filter[price]=>=100` (price >= 100) |
| Callback | `EloquentFilter::callback('custom', fn($q, $v, $p) => ...)` | `?filter[custom]=value` |
| Passthrough | `EloquentFilter::passthrough('context')` | Captured but not applied |

### Filter Options

All filters support fluent modifiers:

```php
EloquentFilter::exact('status')
    ->alias('state')                           // URL parameter name: ?filter[state]=...
    ->default('active')                        // Default value when not in request
    ->prepareValueWith(fn($v) => strtolower($v))  // Transform before applying
    ->when(fn($v) => $v !== 'all')             // Skip filter if returns false
    ->allowStructuredInput()                   // Accept structured raw input, still validate prepared value
    ->asBoolean()                              // Convert 'true'/'1'/'yes' to bool
```

**Filter-specific modifiers:**

```php
// Range filter
EloquentFilter::range('price')->minKey('from')->maxKey('to')

// Date range filter
EloquentFilter::dateRange('created_at')
    ->fromKey('start')->toKey('end')
    ->dateFormat('Y-m-d')

// JSON contains filter
EloquentFilter::jsonContains('tags')->matchAny()  // Default: matchAll()

// Null filter
EloquentFilter::null('deleted_at')->withInvertedLogic()  // IS NOT NULL

// Scope filter
EloquentFilter::scope('byAuthor')->withModelBinding()  // Load model by ID
```

### Built-in Filter Payload Validation

Built-in filters validate the shape of their input before `prepareValueWith()` and `apply()` run.

- `exact`, `partial`, `operator`: scalar or flat list of scalars
- `scope`: single value or flat list without nested arrays
- `null`, `trashed`: scalar only
- `range`, `dateRange`: array with boundary keys (`min`/`max`, `from`/`to`) or a flat list with at least two values

Malformed payloads such as `?filter[name][foo][bar]=Alpha` now raise `InvalidFilterQuery::invalidFormat(...)` instead of reaching SQL generation or PHP warnings.

If you intentionally accept structured raw payloads and normalize them in `prepareValueWith()`, opt in with `allowStructuredInput()`. The built-in filter still validates the prepared value shape before applying it to the query.

`disable_invalid_filter_query_exception` only suppresses unknown-filter errors. It does not suppress malformed `filter` payload format errors.

### Relation Filtering

Filters with dot notation automatically use `whereHas`:

```php
EloquentFilter::exact('posts.status')  // Filters users by their posts' status

// Disable this behavior:
EloquentFilter::exact('posts.status')->withoutRelationConstraint()
```

## Sorting

Allow API consumers to sort results.

### Basic Sorts

```php
use Jackardios\QueryWizard\Eloquent\EloquentSort;

EloquentQueryWizard::for(User::class)
    ->allowedSorts('name', 'created_at', EloquentSort::field('email'))
    ->defaultSorts('-created_at')  // Applied only when ?sort is absent
    ->get();
```

**Request:** `?sort=name` (asc), `?sort=-name` (desc), `?sort=-created_at,name` (multiple)

`?sort=` is treated as an invalid request and throws `InvalidSortQuery`.

### Available Sort Types

| Type | Factory | Description |
|------|---------|-------------|
| Field | `EloquentSort::field('created_at')` | Sort by column |
| Count | `EloquentSort::count('posts')` | Sort by relationship count |
| Relation | `EloquentSort::relation('orders', 'total', 'sum')` | Sort by aggregate (min, max, sum, avg, count, exists) |
| Callback | `EloquentSort::callback('custom', fn($q, $dir, $p) => ...)` | Custom logic |

## Including Relationships

Eager load relationships based on request parameters.

### Basic Includes

```php
use Jackardios\QueryWizard\Eloquent\EloquentInclude;

EloquentQueryWizard::for(User::class)
    ->allowedIncludes(
        'posts',                               // Relationship (string shorthand)
        'postsCount',                          // Count (auto-detected by suffix)
        EloquentInclude::exists('subscription'),
    )
    ->defaultIncludes('profile')               // Used only when ?include is absent
    ->get();
```

**Request:** `?include=posts,postsCount,subscriptionExists`

`?include=` explicitly disables includes for that request and does not merge defaults.

### Available Include Types

| Type | Factory | Description |
|------|---------|-------------|
| Relationship | `EloquentInclude::relationship('posts')` | Eager load with `with()` |
| Count | `EloquentInclude::count('posts')` | Load count with `withCount()` |
| Exists | `EloquentInclude::exists('posts')` | Check existence with `withExists()` |
| Callback | `EloquentInclude::callback('custom', fn($q, $rel) => ...)` | Custom logic |

Includes ending with "Count" or "Exists" are auto-detected as count/exists includes.

When root sparse fieldsets are applied, explicit or default `count` / `exists` includes remain visible in the serialized output. Their request alias stays request-facing only; the runtime attribute key still follows Laravel's default naming (`posts_count`, `posts_exists`).

## Selecting Fields

Allow sparse fieldsets (JSON:API compatible).

```php
EloquentQueryWizard::for(User::class)
    ->allowedFields('id', 'name', 'email', 'posts.id', 'posts.title')
    ->get();
```

**Request:** `?fields[user]=id,name&fields[posts]=id,title` or `?fields=id,name`

`?fields=` means an explicit empty root fieldset. `?fields[posts]=` means an explicit empty fieldset for `posts`.

If a `count` / `exists` include is active, `?fields=` still hides normal root columns but keeps the included runtime attribute visible.

### Relation Fields

Use **relation name** as the key, not table name:

```php
// Model: Task with createdBy(): BelongsTo<User>
EloquentQueryWizard::for(Task::class)
    ->allowedIncludes('createdBy')
    ->allowedFields('id', 'title', 'createdBy.id', 'createdBy.name')
    ->get();

// ✅ ?fields[createdBy]=id,name
// ❌ ?fields[users]=id,name — won't work
```

### Relation Field Modes

```php
// config/query-wizard.php
'optimizations' => [
    'relation_select_mode' => 'safe',  // 'safe' (recommended) or 'off'
],
```

**Safe mode** (default): Automatically injects foreign keys for eager loading and protects relation and root accessors/appends by falling back to a full select when needed.

**Off mode**: No automatic handling — you must include all required FK columns manually.

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
    ->defaultAppends('full_name')
    ->get();
```

**Request:** `?append=full_name,posts.reading_time`

`?append=` explicitly disables appends for that request and does not merge defaults.

## Parameter Semantics

Defaults are applied only when the corresponding parameter is completely absent.

- `?include=` means "include nothing"
- `?append=` means "append nothing"
- `?fields=` means "show no root fields", except active `count` / `exists` include attributes remain visible
- `?fields[relation]=` means "show no fields for that relation"
- `?sort=` is invalid and throws `InvalidSortQuery`

## Resource Schemas

For larger applications, use Resource Schemas to define all query capabilities in one place.

### Creating a Schema

```php
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;

class UserSchema extends ResourceSchema
{
    public function model(): string
    {
        return User::class;
    }

    public function filters(QueryWizardInterface $wizard): array
    {
        return ['name', EloquentFilter::exact('status')];
    }

    public function sorts(QueryWizardInterface $wizard): array
    {
        return ['name', 'created_at'];
    }

    public function includes(QueryWizardInterface $wizard): array
    {
        return ['posts', 'profile', 'postsCount'];
    }

    public function fields(QueryWizardInterface $wizard): array
    {
        return ['id', 'name', 'email', 'status'];
    }

    public function appends(QueryWizardInterface $wizard): array
    {
        return ['full_name'];
    }

    public function defaultSorts(QueryWizardInterface $wizard): array
    {
        return ['-created_at'];
    }

    public function defaultFilters(QueryWizardInterface $wizard): array
    {
        return ['status' => 'active'];  // Applied when filter is absent
    }
}
```

### Using Schemas

```php
// With EloquentQueryWizard
$users = EloquentQueryWizard::forSchema(UserSchema::class)->get();

// With ModelQueryWizard (same schema!)
$user = User::find(1);
$processed = ModelQueryWizard::for($user)->schema(UserSchema::class)->process();
```

### Schema Overrides

```php
EloquentQueryWizard::forSchema(UserSchema::class)
    ->disallowedFilters('status')        // Remove from schema
    ->disallowedIncludes('posts')
    ->allowedAppends('extra')            // Add to schema
    ->get();
```

### Wildcard Support in disallowed*()

| Pattern | Meaning |
|---------|---------|
| `'*'` | Block everything |
| `'posts.*'` | Block direct children only |
| `'posts'` | Block relation and all descendants |

### Context-Aware Schemas

Schema methods receive the wizard instance for conditional logic:

```php
public function includes(QueryWizardInterface $wizard): array
{
    $includes = ['posts', 'profile'];

    // Count/exists only work with EloquentQueryWizard
    if ($wizard instanceof EloquentQueryWizard) {
        $includes[] = EloquentInclude::count('posts');
    }

    return $includes;
}
```

## ModelQueryWizard

For processing already-loaded model instances. Handles includes, fields, and appends — **not** filters or sorts.

Call all configuration methods before `process()`. After the first successful `process()`, treat the wizard as single-use for that request/configuration and create a new instance for any different parameters or rules.

```php
use Jackardios\QueryWizard\ModelQueryWizard;

$user = User::find(1);

$processed = ModelQueryWizard::for($user)
    ->allowedIncludes('posts', 'comments')
    ->allowedFields('id', 'name', 'email')
    ->allowedAppends('full_name')
    ->process();
```

| Feature | Behavior |
|---------|----------|
| Includes | Loads missing with `loadMissing()` |
| Fields | Hides non-requested with `makeHidden()` |
| Appends | Adds with `append()` |
| Filters/Sorts | Ignored |

## Security

### Request Limits

Built-in protection against resource exhaustion attacks:

| Setting | Default | Description |
|---------|---------|-------------|
| `max_include_depth` | 3 | Max nesting (e.g., `posts.comments.author` = 3) |
| `max_includes_count` | 10 | Max includes per request |
| `max_filters_count` | 20 | Max filters per request |
| `max_appends_count` | 20 | Max appends per request |
| `max_sorts_count` | 5 | Max sorts per request |

Configure in `config/query-wizard.php`. Set to `null` to disable.

### ScopeFilter Model Binding

By default, `ScopeFilter` passes values as-is. Enable model binding with caution:

```php
EloquentFilter::scope('byAuthor')->withModelBinding()
```

**Warning:** Model binding resolves by ID **without authorization checks**. Add checks in your scope if needed.

## Configuration

Key configuration options (`config/query-wizard.php`):

```php
return [
    'parameters' => [
        'includes' => 'include',   // ?include=posts
        'filters' => 'filter',     // ?filter[name]=John
        'sorts' => 'sort',         // ?sort=-created_at
        'fields' => 'fields',      // ?fields[user]=id,name
        'appends' => 'append',     // ?append=full_name
    ],

    'count_suffix' => 'Count',     // postsCount → count include
    'exists_suffix' => 'Exists',   // postsExists → exists include

    'disable_invalid_filter_query_exception' => false,  // Throw on invalid filter
    // ... similar for sort, include, field, append

    'request_data_source' => 'query_string',  // 'query_string' or 'body' (body only, query string ignored)
    'apply_filter_default_on_null' => false,  // Apply default() when filter value is null/empty

    'naming' => [
        'convert_parameters_to_snake_case' => false,  // ?filter[firstName] → first_name
    ],

    'optimizations' => [
        'relation_select_mode' => 'safe',  // 'safe' or 'off'
    ],

    'fields' => [
        'use_allowed_as_default' => false,
    ],

    'limits' => [
        'max_include_depth' => 3,
        'max_includes_count' => 10,
        'max_filters_count' => 20,
        'max_appends_count' => 20,
        'max_sorts_count' => 5,
        'max_append_depth' => 3,
    ],
];
```

When `fields.use_allowed_as_default` is enabled and `?fields` is absent, default fields resolve in this order: explicit `defaultFields()` on the wizard, schema `defaultFields()`, then the effective allowed root fields. Relation field allow-lists are not promoted into the root `SELECT`. This only affects default field selection and does not allow arbitrary `?fields[...]` requests when allowed fields are not configured. If no allowed fields are configured, the package keeps its normal behavior: root queries still default to all columns, while explicit `?fields[...]` requests are validated against the configured allow-list.

`getPassthroughFilters()` uses the same filter validation, defaults, `prepareValueWith()`, `when()`, and `max_filters_count` enforcement as normal query execution. Unknown filters still honor `disable_invalid_filter_query_exception`; malformed built-in filter payloads do not.

## Error Handling

All exceptions extend `InvalidQuery` (extends Symfony's `HttpException`):

| Exception | Description |
|-----------|-------------|
| `InvalidFilterQuery` | Unknown filter |
| `InvalidSortQuery` | Unknown sort |
| `InvalidIncludeQuery` | Unknown include |
| `InvalidFieldQuery` | Unknown field |
| `InvalidAppendQuery` | Unknown append |
| `MaxFiltersCountExceeded` | Too many filters |
| `MaxIncludeDepthExceeded` | Include nesting too deep |
| ... | (similar for other limits) |

### Global Handler (Laravel 11+)

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (InvalidQuery $e) {
        return response()->json([
            'error' => class_basename($e),
            'message' => $e->getMessage(),
        ], $e->getStatusCode());
    });
})
```

## Advanced Usage

### Batch Processing

All execution methods apply post-processing (field masking, appends) automatically:

```php
$wizard->get();
$wizard->paginate(15);
$wizard->chunk(100, fn($users) => ...);
$wizard->lazy()->each(fn($user) => ...);
```

### Manual Post-Processing

For methods not wrapped by wizard (`find()`, `findMany()`):

```php
$user = $wizard->toQuery()->find($id);
$wizard->applyPostProcessingTo($user);
```

### Laravel Octane

Fully compatible. `QueryParametersManager` uses `scoped()` binding for per-request instances.

## API Reference

See [docs/api-reference.md](docs/api-reference.md) for complete method reference.

## Comparison with spatie/laravel-query-builder

| Feature | Query Wizard | Spatie |
|---------|:---:|:---:|
| **Filters** | | |
| Exact, Partial, Scope, Trashed, Callback | Yes | Yes |
| Range, Date Range, Null, JSON Contains | Yes | No |
| Passthrough, Conditional (`when()`) | Yes | No |
| Value transformation (`prepareValueWith()`) | Yes | No |
| **Sorts** | | |
| Field, Callback | Yes | Yes |
| Relationship count/aggregate | Yes | No |
| **Includes** | | |
| Relationship, Count, Exists, Callback | Yes | Yes |
| Default includes | Yes | No |
| **Appends** | | |
| Appends with nesting | Yes | No |
| **Architecture** | | |
| Resource Schemas | Yes | No |
| `disallowed*()` methods | Yes | No |
| ModelQueryWizard | Yes | No |
| **Security** | | |
| Request limits | Yes | No |

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Testing

```bash
composer test
```

## Upgrading

See [UPGRADE.md](UPGRADE.md) for migration guides between versions.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Salavat Salakhutdinov](https://github.com/jackardios)
- Inspired by [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder) by [Spatie](https://spatie.be)

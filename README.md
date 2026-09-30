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

`toQuery()`, `getSubject()` and `build()` expose the live underlying builder, as do `getQuery()` and `toBase()` called through the wizard when they return the live query. Treat them as the point where wizard configuration is finalized: calling `allowed*()`, `default*()`, or `schema()` afterwards throws `LogicException`. So does reconfiguring a clone of such a wizard, or of one that received builder calls; create a new wizard instead. Such a wizard also throws `LogicException` when its request parameters change, and after a build that failed, since the handed-out builder holds part of the failed build.

Builder methods called on the wizard (`where()`, `orderBy()`, ...) run after the request's filters and sorts are applied, so an `orderBy()` through the wizard sorts after the requested sorts. Executing methods change the wizard's builder the way they change an Eloquent builder: `first()` adds `limit 1`, `find()` adds a key condition, `cursorPaginate()` adds its order columns.

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
| Partial | `EloquentFilter::partial('name')` | `?filter[name]=john` (LIKE %john%; the value is one phrase, commas included) |
| Scope | `EloquentFilter::scope('popular')` | `?filter[popular]=5000` |
| Trashed | `EloquentFilter::trashed()` | `?filter[trashed]=with\|only\|without` |
| Null | `EloquentFilter::null('deleted_at')` | `?filter[deleted_at]=true` (IS NULL) |
| Range | `EloquentFilter::range('price')` | `?filter[price][min]=10&filter[price][max]=100` |
| Date Range | `EloquentFilter::dateRange('created_at')` | `?filter[created_at][from]=2024-01-01&filter[created_at][to]=2024-12-31` (ISO 8601; `to` includes the whole day) |
| JSON Contains | `EloquentFilter::jsonContains('tags')` | `?filter[tags]=laravel,php` |
| Operator | `EloquentFilter::operator('age', FilterOperator::GreaterThan)` | `?filter[age]=18` (age > 18; number or ISO date) |
| Operator (dynamic) | `EloquentFilter::operator('price', FilterOperator::Dynamic)` | `?filter[price]=>=100` (price >= 100), `?filter[created_at]=<=2024-01-31` |
| Callback | `EloquentFilter::callback('custom', fn($q, $v, $p) => ...)` | `?filter[custom]=value` |
| Passthrough | `EloquentFilter::passthrough('context')` | Captured but not applied |

### Filter Options

All filters support fluent modifiers:

```php
EloquentFilter::exact('status')
    ->alias('state')                           // URL parameter name: ?filter[state]=...
    ->default('active')                        // Default value when not in request
    ->prepareValueWith(fn($v) => strtolower($v))  // Transform before applying (repeated calls chain in order)
    ->when(fn($v) => $v !== 'all')             // Skip filter if returns false
    ->allowStructuredInput()                   // Accept structured raw input, still validate prepared value
    ->withoutValueSplitting()                  // Keep 'a,b' as one string instead of ['a', 'b']
    ->asBoolean()                              // Read true/false/1/0/yes/no/on/off as bool; anything else is a 400
```

`prepareValueWith()` and `asBoolean()` add steps to one chain that runs in the order the methods were called, each
step receiving the previous result; a `null` result skips the filter. `asBoolean()` reads a list item by item, so
`?filter[is_active]=1,0` on an exact filter matches either value; a callback filter takes a single boolean and rejects a
list. Filters that cannot compare with a boolean (partial, range, date range, JSON contains, trashed, and operator
filters other than `Equal`/`NotEqual`) throw `LogicException` from `asBoolean()`.

String values are split by the filters separator (`?filter[status]=active,pending` → `['active', 'pending']`) for every
filter except `partial` and the `Like`/`NotLike` operators, whose value is a search phrase. Use `withoutValueSplitting()` / `withValueSplitting()` to change
that per filter; a list sent as `?filter[name][]=a&filter[name][]=b` always arrives as an array.

**Filter-specific modifiers:**

```php
// Range filter
EloquentFilter::range('price')->minKey('from')->maxKey('to')

// Date range filter
EloquentFilter::dateRange('created_at')
    ->fromKey('start')->toKey('end')
    ->dateFormat('Y-m-d')      // Format every bound for a column not stored in the database date format
    ->lenient()                // Also accept any date PHP can read ("yesterday", "-1 week")
EloquentFilter::dateRange('created_ts')->asUnixTimestamp()  // Integer column of Unix timestamps; accepts timestamps too

// JSON contains filter
EloquentFilter::jsonContains('tags')->matchAny()  // Default: matchAll()

// Null filter
EloquentFilter::null('deleted_at')->withInvertedLogic()  // IS NOT NULL

// Scope filter
EloquentFilter::scope('byAuthor')->withModelBinding()  // Load model by ID
```

### Filter Values

Built-in filters validate the shape of their input before `prepareValueWith()` and `apply()` run.

- `exact`, `partial`, `operator`: scalar or flat list of scalars
- `scope`: single value or flat list without nested arrays
- `jsonContains`: scalar or flat list of scalars
- `null`, `trashed`: scalar only
- `range`, `dateRange`: array with only the boundary keys (`min`/`max`, `from`/`to`) or a flat list of exactly two
  values; another key (a typo such as `form`) is rejected

Malformed payloads such as `?filter[name][foo][bar]=Alpha` raise `InvalidFilterQuery::invalidFormat(...)` instead of reaching SQL generation or PHP warnings.

If you intentionally accept structured raw payloads and normalize them in `prepareValueWith()`, opt in with `allowStructuredInput()`. The built-in filter still validates the prepared value shape before applying it to the query.

A blank value is absent: `?filter=` applies no filters, and `?filter[name]=`, a value of spaces, `?filter[name]=,` and a
list of empty items apply no condition (with `filters.apply_default_on_null` enabled, the filter's `default()` applies instead). A value that a
filter has to read and cannot is rejected with `InvalidFilterValue` (400), whose message says what was expected:

| Filter | Accepts |
|--------|---------|
| `asBoolean()` | `true`, `false`, `1`, `0`, `yes`, `no`, `on`, `off` (any letter case) |
| `null` | the same booleans |
| `trashed` | `with`, `only`, `without` (`true`/`false` for with/without) |
| `range` | decimal numbers (`10`, `-2.5`); no exponents or hex |
| `dateRange` | a date (`2024-01-31`) or an ISO 8601 date-time (`2024-01-31T10:00:00+03:00`, `Z`, fractions); see below |
| `operator` with `>`, `>=`, `<`, `<=` (static or after them with `Dynamic`) | a decimal number or an ISO 8601 date |
| `partial` | text or numbers (a boolean is rejected) |
| `scope` | as many values as the scope takes, each one its parameter's type accepts: `int`/`float` take numbers, `bool` takes the booleans above, a union takes any of its types |

**Dates** (`dateRange`, and `>`, `>=`, `<`, `<=` operators) are read in the application timezone; a date-time with an offset is
converted to it, and so is a `DateTimeInterface` default. A date names the whole day: `to=2024-01-31` and
`<=2024-01-31` match all of January 31 (`< 2024-02-01`), and `>2024-01-31` starts on February 1. Send `+` in an offset
as `%2B`, since an unencoded `+` in a query string is a space. `dateFormat()` formats every bound for the column;
`dateFormat('U')` / `asUnixTimestamp()` compares whole seconds and is meant for integer columns.

**Dynamic operators**: `>=`, `<=`, `>`, `<`, `!=` and `<>` at the start of the value. An operator without a value is
absent, and an operator inside a list (`?filter[price]=>=1,5`) is rejected. `!=`/`<>` and plain values are compared as
sent.

**LIKE**: `partial` filters and the `Like`/`NotLike` operators match the value literally; `%` and `_` in the value are
not wildcards. A list matches any of its phrases (`NotLike`: none of them). On PostgreSQL the column is compared as
text, so non-text columns work too, and `Like`/`NotLike` are case-sensitive even on a `citext` column. `partial` lowercases both sides; SQLite's `LOWER()` only folds ASCII letters.

To keep the old "skip what you can't read" behavior for a boolean filter, use your own preparer instead of
`asBoolean()`:

```php
EloquentFilter::exact('is_active')
    ->prepareValueWith(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE))
```

`ignore_unknown.filters` only drops unknown filter names. It never suppresses malformed payloads
or values a filter cannot read.

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

`?sort=` (and variants such as `?sort=-` or `?sort=,`) is treated as an invalid request and throws `InvalidSortQuery` (`invalid_sort_format`), also with `ignore_unknown.sorts` enabled.

### Available Sort Types

| Type | Factory | Description |
|------|---------|-------------|
| Field | `EloquentSort::field('created_at')` | Sort by column |
| Count | `EloquentSort::count('posts')` | Sort by relationship count |
| Aggregate | `EloquentSort::sum('orders', 'total')` | Sort by `max`, `min`, `sum` or `avg` of a related column |
| Callback | `EloquentSort::callback('custom', fn($q, $dir, $p) => ...)` | Custom logic |

A field sort orders by the qualified column (`users.total`), so it cannot sort by an alias from `select()`/`selectRaw()`.
Use a callback sort for that: `EloquentSort::callback('total', fn ($q, $dir) => $q->orderBy('total', $dir))`.

Count and aggregate sorts answer to the relation name (`?sort=-orders`); give two sorts on one relation aliases:
`EloquentSort::sum('orders', 'total')->alias('ordersTotal')`.

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

Includes ending with "Count" or "Exists" are auto-detected as count/exists includes. Count and exists includes take a
single relation (`postsCount`); a nested relation such as `posts.commentsCount` throws `InvalidArgumentException` when
the include is defined. Use a callback include for nested counts.

An include keeps constraints already registered for the same relation (a developer's `with(['posts' => fn ...])`, a
parent include's select). A callback include that registers its own closure for a relation replaces an existing
closure, as `with()` does in Laravel; to keep both, read `$query->getEagerLoads()` and call the previous closure from
yours. Declare attributes a callback include adds with `->withRuntimeAttributes('posts_total')` so sparse fieldsets keep
them visible.

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

Under a wildcard (`allowedFields('*')`), a requested name that is not a column of the table reaches the query and fails
there (`QueryException`), so list the columns explicitly when clients may send arbitrary names. A root `*` also allows
every relation fieldset. `disallowedFields()` rejects names a client requests; it does not hide them from a `*` request,
which still returns all columns. A name that matches a disallowed field or one of the model's `$hidden` attributes in
another letter case (`NAME` for `name`) is rejected too, since MySQL would return that column under the name as written.
Other names are returned as written. At most `limits.max_fields_count` fields (100 by default) may be requested across every fieldset.

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

### Relation Key Columns

Sparse fieldsets keep the key columns eager loading needs: the wizard adds them to the root and relation selects, so
`?fields[posts]=title` still matches posts to their users. BelongsTo, HasOne, HasMany, MorphOne, MorphMany and
BelongsToThrough relations are narrowed to their fieldset; other relations, relations whose model has `$appends` and
relations with requested appends select all columns and hide the fields outside the fieldset, so accessors keep working.

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

Defaults come from the developer, so they apply without being allowed: `defaultSorts('-created_at')` works without
`allowedSorts()`, and a default that names an allowed definition (an alias, a count sort) uses it. The client still
needs `allowed*()` to request the same name. A default that `disallowed*()` removes throws `InvalidArgumentException`,
and a typo in a default fails in the database or in Eloquent instead of being skipped.

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
        return ['status' => 'active'];  // Applied when filter is absent; keyed by the public (alias) name
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
    ->addAllowedAppends('extra')         // Add to schema
    ->get();
```

`allowed*()` replaces the schema's list and any earlier call; `addAllowed*()` (`addAllowedFilters()`,
`addAllowedSorts()`, `addAllowedIncludes()`, `addAllowedFields()`, `addAllowedAppends()`) adds to the list set with
`allowed*()` or, when none was set, to the schema's. `disallowed*()` calls add up, so a later call never re-allows what
an earlier one removed.

> **Warning:** `disallowedFilters()` also drops the schema's `defaultFilters()` entry for that filter. A default such as
> `['status' => 'published']` stops restricting the results once `status` is disallowed. Put a condition that must
> always hold on the query itself, not in a filter default:
>
> ```php
> EloquentQueryWizard::forSchema(PostSchema::class)
>     ->tap(fn ($query) => $query->where('status', 'published'))
>     ->disallowedFilters('status')
>     ->get();
> ```

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
| Includes | Loads missing with `loadMissing()`, counts with `loadCount()`, existence with `loadExists()` |
| Callback includes | The callback receives the loaded model, not a query: `fn ($model) => $model->loadCount('posts')` |
| Custom includes | Run through `AppliesToModel::applyToModel()`; without it, `LogicException` before the model is changed |
| Fields | Hides non-requested with `makeHidden()` |
| Appends | Adds with `append()` |
| Relations not requested | Unset from the model (loaded relations not in `?include` are removed) |
| Filters/Sorts | Ignored |

## Security

### Request Limits

Built-in protection against resource exhaustion attacks:

| Setting | Default | Description |
|---------|---------|-------------|
| `max_include_depth` | 3 | Max nesting (e.g., `posts.comments.author` = 3) |
| `max_includes_count` | 10 | Max includes per request |
| `max_filters_count` | 20 | Max filters per request |
| `max_filter_values_count` | 1000 | Max values one filter receives (list items, counted through nested lists) |
| `max_fields_count` | 100 | Max fields per request, across every fieldset |
| `max_appends_count` | 20 | Max appends per request |
| `max_append_depth` | 3 | Max append nesting (e.g., `posts.author.full_name` = 3) |
| `max_sorts_count` | 5 | Max sorts per request |

Configure in `config/query-wizard.php`. Set a limit to `null` to disable it. Any other value that is not a positive
integer (`0`, `''` from an empty environment variable, `false`, `-1`) throws `InvalidArgumentException` instead of
silently disabling the limit, and a limit missing from a published `limits` array takes the package default.

Includes, sorts, fields and appends are counted while the request is read, before any name is validated: a list is
split only until it names one item more than its limit. A name repeated in the list, or differing only in naming style
under `convert_parameters_to_snake_case`, counts once, and blank items don't count; a sort counts once whatever its
direction. Appends are counted as requested, including names that `ignore_unknown.appends` later
ignores. The exception's `$count` is then one more than the limit, not the total the request names.

Limits apply to what the client sends. Developer defaults (`defaultSorts()`, `defaultIncludes()`, `defaultAppends()`,
schema defaults) over a limit throw `InvalidArgumentException`, since only the developer can fix them.

### ScopeFilter Model Binding

By default, `ScopeFilter` passes values as-is. Enable model binding with caution:

```php
EloquentFilter::scope('byAuthor')->withModelBinding()
```

**Warning:** Model binding resolves by ID **without authorization checks**. Add checks in your scope if needed. Because
a missing ID and an existing one may produce different results, binding also tells a client which IDs exist.

### Values Exposed by Sorting

A cursor encodes the values of the columns the query is ordered by, including columns hidden by a sparse fieldset.
Clients can decode it, so don't sort by columns whose values they must not see. Count and aggregate sorts add their
value to each model (`posts_count`, `orders_max_total`); without a root fieldset it is serialized with the model.

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

    'request_data_source' => 'query_string',  // 'query_string' or 'body' (body only, query string ignored)

    'naming' => [
        'convert_parameters_to_snake_case' => false,  // ?filter[firstName] → first_name
    ],

    'separators' => [
        'default' => ',',  // Splits list parameters; 'filters' => ';' etc. per type
    ],

    'ignore_unknown' => [
        'filters' => false,  // true: drop filters that are not allowed instead of a 400
        // ... likewise sorts, includes, fields, appends; malformed parameters are always a 400
    ],

    'includes' => [
        'count_suffix' => 'Count',     // postsCount → count include
        'exists_suffix' => 'Exists',   // postsExists → exists include
    ],

    'filters' => [
        'apply_default_on_null' => false,  // Apply default() when filter value is null/empty
    ],

    'fields' => [
        'use_allowed_as_default' => false,
    ],

    'limits' => [
        'max_include_depth' => 3,
        'max_includes_count' => 10,
        'max_filters_count' => 20,
        'max_filter_values_count' => 1000,
        'max_fields_count' => 100,
        'max_appends_count' => 20,
        'max_sorts_count' => 5,
        'max_append_depth' => 3,
    ],
];
```

When `fields.use_allowed_as_default` is enabled and `?fields` is absent, default fields resolve in this order: explicit `defaultFields()` on the wizard, schema `defaultFields()`, then the effective allowed root fields. Relation field allow-lists are not promoted into the root `SELECT`. This only affects default field selection and does not allow arbitrary `?fields[...]` requests when allowed fields are not configured. If no allowed fields are configured, the package keeps its normal behavior: root queries still default to all columns, while explicit `?fields[...]` requests are validated against the configured allow-list.

`getPassthroughFilters()` uses the same filter validation, defaults, `prepareValueWith()`, `when()`, and `max_filters_count` enforcement as normal query execution. Unknown filters still honor `ignore_unknown.filters`; malformed built-in filter payloads do not.

The whole configuration is validated when a build reads it, whatever the request uses: an invalid limit, separator (a
non-empty string of at most 10 characters), parameter name (a non-empty string, or `null` to turn the parameter off),
`request_data_source` or boolean option (`true`/`false`, or a string such as `'false'` or `'off'`) throws
`InvalidArgumentException` naming the key, and so does an unknown key inside `parameters`, `naming`, `separators`,
`fields` or `limits` (a typo such as `limits.max_filter_count`). A key missing from the published file takes the
package default; unknown top-level keys are ignored. Each build reads the configuration once, so a `config()->set()` at runtime applies from the next build;
parameter names and separators apply from the next request.

With `convert_parameters_to_snake_case` enabled, only the names of filters, sorts, includes, fields and appends are
converted. Keys inside a filter value (a range's `minKey()`, a structured callback payload) are passed as sent, and when
two filter keys convert to the same name (`createdAt` and `created_at`), the one already in snake_case wins.

With `request_data_source` set to `body`, a JSON request body must be a JSON object; malformed or non-object JSON
throws `InvalidRequestBody` (400). A body is read as JSON only when the request has a JSON content type.

## Error Handling

All exceptions extend `InvalidQuery` (extends Symfony's `HttpException`, status 400). Each carries a stable
`errorCode` and the request `parameter` it refers to (as configured under `parameters`, e.g. `filter`), or `null`:

| Exception | `errorCode` | When |
|-----------|-------------|------|
| `InvalidFilterQuery` | `filter_not_allowed` | Unknown filter |
| `InvalidFilterQuery` | `invalid_filter_format` | Malformed `filter` payload |
| `InvalidFilterValue` | `invalid_filter_value` | A value the filter cannot read (see `$reason`, `$filterName`, `$filterValue`) |
| `InvalidSortQuery` | `sort_not_allowed` | Unknown sort |
| `InvalidSortQuery` | `invalid_sort_format` | Empty `sort`, or nested or keyed lists in it |
| `InvalidIncludeQuery` | `include_not_allowed` | Unknown or disallowed include |
| `InvalidIncludeQuery` | `invalid_include_format` | Nested or keyed lists in `include` |
| `InvalidFieldQuery` | `field_not_allowed` | Unknown or disallowed field |
| `InvalidFieldQuery` | `invalid_field_format` | Nested or keyed lists, a dotted name inside a fieldset, or a name that is not a valid column identifier |
| `InvalidAppendQuery` | `append_not_allowed` | Unknown or disallowed append |
| `InvalidAppendQuery` | `invalid_append_format` | Nested or keyed lists in `append` |
| `InvalidRequestBody` | `invalid_request_body` | Malformed or non-object JSON body in `body` mode |
| `MaxFiltersCountExceeded` | `max_filters_count_exceeded` | Too many filters |
| `MaxFilterValuesCountExceeded` | `max_filter_values_count_exceeded` | Too many values for one filter |
| `MaxSortsCountExceeded` | `max_sorts_count_exceeded` | Too many sorts |
| `MaxIncludesCountExceeded` | `max_includes_count_exceeded` | Too many includes |
| `MaxFieldsCountExceeded` | `max_fields_count_exceeded` | Too many fields |
| `MaxIncludeDepthExceeded` | `max_include_depth_exceeded` | Include nesting too deep |
| `MaxAppendsCountExceeded` | `max_appends_count_exceeded` | Too many appends |
| `MaxAppendDepthExceeded` | `max_append_depth_exceeded` | Append nesting too deep |

Configuration mistakes (invalid config values, developer defaults over a limit, a nested relation in a count sort or
count/exists include) throw `InvalidArgumentException` instead, since they are not the client's fault.

### Global Handler

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (InvalidQuery $e) {
        return response()->json([
            'error' => $e->errorCode,
            'parameter' => $e->parameter,
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
$wizard->chunkById(100, fn($users) => ...);   // also lazyById(), lazyByIdDesc(), chunkByIdDesc(), eachById()
$wizard->each(fn($user) => ...);
$wizard->chunkMap(fn($user) => ...);
$wizard->lazy()->each(fn($user) => ...);
$wizard->cursor()->each(fn($user) => ...);
```

The `*ById` methods and `cursorPaginate()` select the key or order columns they need even when a sparse fieldset left
them out, and hide them again in the results. `cursor()` loads includes (and any other eager loads) for each batch of
1000 models, so up to 1000 models and their relations are in memory at once; without eager loads it streams one model at
a time as before.

Finder methods called on the wizard (`find()`, `findMany()`, `findOrFail()`, `findOr()`, `findSole()`, `sole()`,
`firstWhere()`, `firstOr()`) build the query and post-process the models they return. Like on an Eloquent builder, they
narrow the wizard's query (for example `find()` adds a key condition). Methods that create or return raw values
(`firstOrNew()`, `firstOrCreate()`, `updateOrCreate()`, `value()`, `pluck()`, ...) are not post-processed.

### Manual Post-Processing

For queries you run on the builder yourself:

```php
$user = $wizard->toQuery()->find($id);
$wizard->applyPostProcessingTo($user);

$users = $wizard->applyPostProcessingTo($wizard->toQuery()->lazy()); // use the returned lazy collection
```

A lazy collection is not read: `applyPostProcessingTo()` returns a new one that post-processes each model as it is read.
A generator is refused with `InvalidArgumentException`, since reading it would leave nothing to return.

### Extending

Custom filters, sorts and includes extend `AbstractFilter`, `AbstractSort` or `AbstractInclude`; filters on Eloquent
columns can add `HandlesRelationFiltering` (dot-notation properties go through `whereHas()`) or extend
`AbstractRangeFilter`. The built-in definitions are `final`: to change one, write a filter on these bases.

```php
use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Eloquent\Filters\Concerns\HandlesRelationFiltering;
use Jackardios\QueryWizard\Filters\AbstractFilter;

final class NullOrEqualFilter extends AbstractFilter
{
    /** @use HandlesRelationFiltering<mixed> */
    use HandlesRelationFiltering;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOrFlatListValueShape($value);
    }

    public function apply(mixed $subject, mixed $value): mixed
    {
        return $this->applyToSubject($subject, $value);
    }

    protected function applyOnQuery(Builder $builder, mixed $value, string $column): Builder
    {
        $column = $builder->qualifyColumn($column);

        return $builder->where(fn (Builder $query) => $query->whereNull($column)->orWhereIn($column, (array) $value));
    }
}

EloquentQueryWizard::for(User::class)->allowedFilters(NullOrEqualFilter::make('team_id'));
```

These hooks are part of the supported API:

| Hook | Purpose |
|------|---------|
| `Support\FilterValueParser` | Read request values the way built-in filters do: `isBlank()`, `boolean()`, `number()`, `isoDate()`, `trashedMode()`, `comparable()`, `defaultTimezone()`; unreadable values throw `InvalidFilterValue` |
| `InvalidFilterValue::make($value, $filter, $reason)` | The 400 for a value a custom filter cannot read; `$reason` says what was expected. Called on a subclass, it returns that subclass |
| `Support\ParsedDate` | Result of the date readers: `value` (`DateTimeImmutable`) and `dateOnly`; `upToBound()` and `afterBound()` give the comparison for "on or before" and "after", where a date names its whole day |
| `AbstractFilter::supportsBooleanValues()` | Return `false` when the filter can't take booleans, so `asBoolean()` throws `LogicException` |
| `AbstractFilter::supportsBooleanLists()` | Return `false` when `asBoolean()` must reject lists |
| `validateScalarOnlyValueShape()`, `validateScalarOrFlatListValueShape()` | Ready-made `validateValueShape()` bodies for filters taking a scalar, or a scalar or flat list |
| `resolveConstraint(mixed $value): mixed` | For filters using `HandlesRelationFiltering`: read the value once into what `applyOnQuery()` receives; `null` adds no condition, so no `whereHas` is added |
| `applyRelationFilter($builder, $property, $value)` | For filters using `HandlesRelationFiltering`: how a dot-notation property constrains the relation (default: `whereHas()` running `applyOnQuery()`) |
| `Contracts\ProvidesRuntimeAttributes` | Includes that add attributes (`runtimeAttributes(): list<string>`) keep them visible under sparse fieldsets |
| `Contracts\EagerLoadsRelation` | Includes whose `apply()` eager loads `getRelation()`: `fields[relation]` narrows the eager load after `apply()`, relation fields and appends are validated, and disallowing the relation path denies the include under any alias |
| `Contracts\AppliesToModel` | Includes `ModelQueryWizard` can run on a loaded model (`applyToModel(Model $model): void`); other custom includes throw `LogicException` there |
| `BaseQueryWizard::__construct($subject, $parameters, $config, $schema)` | Wizard subclasses call it from their own constructor; `null` parameters and config resolve from the container |
| `$subject`, `isBuilt(): bool` | The subject the build shapes, and whether it is built for the current configuration and request |
| `rollbackFailedBuild(): void` | Wizard subclasses reset their own state after a build throws (call the parent) |
| `resourceModel(): ?Model` | The resource's model; the default `resolveAppendAccessorModel()` checks it and its relations |
| `resolveAppendAccessorModel(string $relationPath): ?Model` | The model whose accessors a wildcard append must name (`null` = no check) |
| `resolveEloquentShape($includes, $rootFields, $requiredRootColumns)` | For a wizard that loads the models with an Eloquent query of its own: call it from `finalizeBuild()` with what `applyValidatedIncludes()` and `applyFields()` received. It validates relation fieldsets and appends and returns an `Eloquent\EloquentShape` |
| `EloquentShape::applyTo($query)`, `postProcess($results)` | Apply the includes, relation fieldsets and root select to the loading query (last, after its other eager loads and selects), then the fieldsets and appends to the loaded models. The shape holds no reference to the wizard and reads no configuration |
| `normalizePublicPath()`, `resolveDefaultResourceKey($model)` | A requested name in the form the wizard compares names in (snake case when configured); the default sparse-fieldset key for a model |
| `QueryWizardConfig::snapshot()` | Configuration fixed at the time of the call |

### Backward Compatibility

From 3.0.0, breaking changes wait for a major version in:

- public methods of classes, interfaces and enums not marked `@internal`;
- protected members marked `@api`. A class-level `@api` marks a class as meant to be extended; its protected members
  without `@api` are not covered;
- the `@api` interfaces custom definitions implement (`FilterInterface`, `SortInterface`, `IncludeInterface` and the
  include contracts), which gain no required methods in minor releases.

Anything marked `@internal` may change in any release, and so may protected members without `@api`. The `Concerns`
traits are `@internal`: the wizard methods they provide are covered, using a trait in another class is not.

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

- PHP 8.2+ (tested on 8.2–8.5)
- Laravel 12.61.1+ or 13.12.0+

## Testing

```bash
composer test
```

## Upgrading

See [UPGRADE.md](UPGRADE.md) for migration guides between versions and [CHANGELOG.md](CHANGELOG.md) for the list of changes.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Salavat Salakhutdinov](https://github.com/jackardios)
- Inspired by [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder) by [Spatie](https://spatie.be)

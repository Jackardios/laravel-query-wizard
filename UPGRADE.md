# Upgrade Guide

This document describes how to upgrade Laravel Query Wizard between versions.

Coming from v2.x, read [v2.x to v3.0](#upgrade-guide-v2x-to-v30) first, then the sections below. Coming from a
`dev-master` snapshot of v3, the sections below list everything that changed before the 3.0.0 release.

---

## dev-master → 3.0.0

Most changes turn silent misbehavior into an error: a value the package used to skip, truncate or bind as is now gets a
400 (`InvalidQuery`), and a configuration mistake gets an `InvalidArgumentException`. Response bodies change for some
requests (see [Includes](#includes)), so flush response caches after deploying.

### Requirements

- PHP 8.2+ (tested on 8.2–8.5).
- Laravel 12.61.1+ or 13.12.0+. Laravel 10 and 11 are no longer supported; the floors are the first releases without
  open security advisories.

### Filter Values

**Unreadable values are rejected.** A filter that must read a value and cannot now throws `InvalidFilterValue` (400,
`invalid_filter_value`) instead of skipping itself. `disable_invalid_filter_query_exception` does not suppress it.

| Filter | Before | Now |
|--------|--------|-----|
| `asBoolean()` | `filter_var()`; unreadable → filter skipped; a list → skipped | `true/false/1/0/yes/no/on/off` (any case), else 400; a list is read item by item (`whereIn` on an exact filter); a callback filter rejects lists |
| `null` | non-boolean → skipped (unless `strict()`) | non-boolean → 400; `strict()` is removed |
| `trashed` | unknown values ignored | `with`, `only`, `without`, `true`, `false` (any case), else 400 (`1`/`0` included) |
| `range` | bounds bound as strings; non-numeric bound dropped | decimal numbers (no exponents or hex), bound as int/float, else 400 |
| `dateRange` | any `strtotime()` string bound raw; unparseable bound dropped | a date or an ISO 8601 date-time, else 400; see below |
| `operator` `DYNAMIC` | non-numeric operand → filter skipped (`>=2024-01-31` never applied) | decimal number or ISO 8601 date after `>`, `>=`, `<`, `<=`, else 400 |
| `partial` | `true` → `%1%`, `false` → `%%` (matches all) | booleans and objects → 400 (numbers are still searched as text) |

To keep the old skip-on-garbage behavior of a boolean filter:

```php
// Before
EloquentFilter::exact('is_active')->asBoolean()

// After, lenient
EloquentFilter::exact('is_active')
    ->prepareValueWith(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE))
```

**Blank values are absent.** `null`, a whitespace-only string, `,`, `[]=`, a list of blanks and a range with blank bounds
add no condition (before: `where name in ('')` → empty result, or `where name = ' '`, or a 500 on a scope). Blank
items in a partial or LIKE list are dropped (before: `['foo', ' ']` added `LIKE '% %'`). With
`apply_filter_default_on_null`, the default applies to all of them. A blank `default()` (for example `[]`) is no default.
`default('a,b')` is passed whole, not split.

**Relation filters without a condition add no `whereHas`.** A dotted filter whose value adds nothing (blank, a range
without bounds, a DYNAMIC operator without a value) no longer adds an unconstrained `whereHas`, which dropped parents
without related rows. Custom filters using `HandlesRelationFiltering` can override `hasEffectiveConstraint()`.

**Date ranges** (`dateRange`):

- Bounds are read in the application timezone; a date-time with an offset, and a `DateTimeInterface` default, are
  converted to it. Clients must send `+` in an offset as `%2B`.
- A date-only `to` covers the whole day: `to=2024-01-31` becomes `< 2024-02-01` (before: `<= '2024-01-31'`, which
  dropped rows later that day on datetime columns).
- `dateFormat()` now formats every bound, not only `DateTimeInterface` values.
- New `lenient()` accepts anything PHP's date parser reads (`yesterday`, `-1 week`).
- New `asUnixTimestamp()` (same as `dateFormat('U')`) compares whole seconds, accepts integer timestamps and binds
  integers. Use it only on integer columns.
- `strict()` is removed; strict parsing is the default.

**Dynamic operators** (`FilterOperator::DYNAMIC`): a date operand names the whole day (`>2024-01-31` →
`>= 2024-02-01`, `<=2024-01-31` → `< 2024-02-01`). An operator inside a list (`>=1,3`) → 400 (before: `whereIn` with
the literal `>=1`). `=`, `!=`, `<>` and plain values are still compared as sent.

**LIKE is literal.** `partial` filters and the `LIKE`/`NOT_LIKE` operators escape `%`, `_` and `!` (`ESCAPE '!'`) on
every database. Clients that used `%` or `_` as wildcards now match them literally. `LIKE`/`NOT_LIKE` values are no
longer split by the separator (`->withValueSplitting()` restores it); a list matches any phrase (`NOT_LIKE`: none) instead
of throwing. On PostgreSQL the column is compared as text, so `partial` works on integer columns (before: 500).
As a result, the `LIKE`/`NOT_LIKE` operators are case-sensitive on a `citext` column (they were not); `partial`
lowercases both sides and is unaffected.

**Value preparers chain.** `prepareValueWith()` adds a step instead of replacing the previous one, and `asBoolean()` is
one of the steps; they run in call order and a `null` result skips the filter.

```php
// Before: only strtolower() ran (the second call replaced trim())
// After: trim(), then strtolower()
EloquentFilter::exact('name')
    ->prepareValueWith(fn ($v) => trim($v))
    ->prepareValueWith(fn ($v) => strtolower($v));
```

**Scope filters check arguments** against the scope's signature: too many or too few values → 400 (before: extra values
dropped, so `Moscow, Russia` reached a one-parameter scope as `Moscow`; use `->withoutValueSplitting()` for free text);
`int`/`float` parameters need numbers; `null` for a non-nullable parameter → 400. Scopes marked `#[Scope]` get model
binding, and `rel.scope` filters bind against the related model.

**Range lists hold exactly two values**: `?filter[id]=1,2,3` on a range filter → 400 (before: the rest was ignored).

**Nested filter names.** With `name` and `name.first` allowed, `filter[name][first]=x` goes to `name.first` only (before:
both). Keys no nested filter takes stay with `name`.

**Callback return values.** Callback filters, sorts and includes adopt the returned value as the new subject only when
it is an instance of the subject's class; any other return value is ignored (before: it replaced the subject and caused
a later TypeError).

### Sorts

- `?sort=-`, `?sort=,` and `?sort[]=-` behave like `?sort=`: 400, or with `disable_invalid_sort_query_exception` "no sort
  requested", so the default sorts apply (before: `?sort=` was a 400 even with the flag).
- `EloquentSort::count('posts.comments')` and `EloquentSort::relation()` with a dotted relation throw
  `InvalidArgumentException` when defined (before: 500 at request time). Use a callback sort.
- Duplicate sorts are removed by exact name (`1` and `01` are no longer merged).

### Includes

- A client include no longer replaces a constraint the developer registered for the same relation
  (`with(['posts' => fn ...])`), a callback include's constraint or a parent include's select. Responses may change.
- `disallowedIncludes()` also matches a relationship include's relation path, so an alias cannot load a disallowed
  relation.
- `EloquentInclude::count()`/`exists()` and string includes such as `posts.commentsCount` throw
  `InvalidArgumentException` for a nested relation when defined.
- `ModelQueryWizard` loads `exists` includes (before: accepted and ignored) and keeps count/exists attributes visible
  under root fieldsets.

### Fields and Appends

- Nested lists in `fields` or `append` → 400 `invalid_field_format` / `invalid_append_format` (before: 500 or silently
  dropped). An integer fieldset key (`fields[5]=x`) → 400 `field_not_allowed`.
- A dotted name inside a fieldset → 400 `invalid_field_format`. A name accepted only through a wildcard must be a column
  identifier, else 400 (before: `name as id` aliased columns, `count(*)` caused a 500). JSON selectors such as `meta->x`
  must be allowed explicitly.
- `disallowedFields()` and `disallowedAppends()` apply to names allowed by a wildcard: a client requesting one gets 400
  (before: 200). Under a wildcard, a field name that matches a disallowed field or a `$hidden` attribute of the model in
  another letter case is rejected as well (before: on MySQL, `fields=NAME` returned the hidden `name` column as `NAME`).
- Root default fields apply whenever the request has no root fieldset, even when relation fieldsets are present
  (before: `fields[posts]=id` disabled the root defaults and returned all columns). A dotted default field throws
  `InvalidArgumentException`.
- Requested relation appends are always validated against `allowedAppends()`, also when the relation is not included.
  An append allowed only through a wildcard must name an accessor of the model, else 400 (before: 500 on serialization).
- Appends are validated during the build: `toQuery()`, `count()` and other builder calls throw
  `InvalidAppendQuery` for an invalid `?append`, before any SQL runs.
- `defaultSorts()`, `defaultIncludes()`, `defaultFields()` and `defaultAppends()` called with no arguments mean "no
  defaults" (before: they fell back to the schema's defaults).

### Execution and Builder Access

- `build()` returns `Builder|Relation` and finalizes the configuration like `toQuery()`: configuring the wizard
  afterwards throws `LogicException`. Subclasses overriding `build()` must return `Builder|Relation`.
- Builder calls through the wizard return the wizard only when the builder returns itself; a different builder or
  relation (`getRelation()`, `clone()`, a scope returning a new builder) is returned as is (before: it silently replaced
  the wizard's subject and dropped the filters).
- Cloning a wizard that received builder calls or exposed its builder keeps that state: reconfiguring the clone throws
  `LogicException`. Create a new wizard instead.
- A build that throws is rolled back, so a retry does not apply taps, filters or sorts twice. A builder already handed
  out with `toQuery()`, `getSubject()` or `build()` is kept as it is.
- `find()`, `findMany()`, `findOrFail()`, `findOr()`, `findSole()`, `sole()`, `firstWhere()` and `firstOr()` through the
  wizard post-process their results. New wrappers: `lazyById()`, `lazyByIdDesc()`, `chunkByIdDesc()`, `eachById()`,
  `each()`, `chunkMap()`. The `*ById` methods and `cursorPaginate()` work with sparse fieldsets that leave out their
  columns (before: `RuntimeException`).
- `cursor()` eager loads includes per 1000 models. Up to 1000 models and their relations are in memory at once (before:
  relations were not loaded at all).

### Configuration

- Values are validated when read. A limit must be a positive integer or `null`; `0`, `''` (an empty environment
  variable), `false` and negative numbers now throw `InvalidArgumentException` (before: they disabled the limit). Invalid
  separators, parameter names (`''` used to disable a parameter; use `null`), `request_data_source` and
  `relation_select_mode` throw as well.
- A key missing from a published config takes the package default. A published `limits` array that lists only some
  limits used to disable the rest; they now apply with their defaults (`max_appends_count` is 20).
- Developer defaults over a limit (`defaultSorts()`, `defaultIncludes()`, `defaultAppends()`, schema defaults) throw
  `InvalidArgumentException` instead of sending the client a 400.
- `convert_parameters_to_snake_case` converts only names. Keys inside filter values are kept as sent: configure
  `minKey()`/`maxKey()` and read callback payload keys as the client sends them. When `createdAt` and `created_at` are
  both sent, the snake_case key wins (before: the last one). `QueryParametersManager::getFilters()` returns converted
  top-level names with nested keys as sent.
- With `request_data_source` = `body`, a JSON request whose body is malformed or not a JSON object → 400
  `InvalidRequestBody` (before: treated as no parameters, returning unfiltered rows).
- Each build reads the configuration once; a `config()->set()` at runtime applies from the next build.
- The scoped `QueryParametersManager` follows a rebound request, so feature tests with several requests per test no
  longer need `forgetScopedInstances()`.

### Exceptions

- `InvalidQuery` has readonly `errorCode` and `parameter` properties (see the table in the README). Custom subclasses
  built with `new self(400, 'message')` keep working and report `invalid_query`.
- Messages name the request parameters as configured under `parameters`.
- `InvalidFilterValue::make($value, $filterOrName, ?string $reason = null)`: the reason is exposed as `$reason` and
  appended to the message.
- New `InvalidRequestBody` (`invalid_request_body`).
- Configuration mistakes throw `InvalidArgumentException` (see above), not `InvalidQuery`.

### Subclasses and Custom Filters

- `AbstractFilter::$prepareValueCallback` is now `$valuePreparers` (a list).
- Removed without replacement: `NullFilter::strict()`, `DateRangeFilter::strict()`,
  `OperatorFilter::requiresNumericValue()`, `QueryParametersManager::convertFiltersArray()` (see `convertFilterKeys()`).
- `ParsesRangeValues::normalizeRangeValue()` takes the bound's key as a second argument.
- New extension points are listed under [Extending](README.md#extending) in the README; they and the classes marked
  `@api` are the supported surface. Classes marked `@internal` may change in any release.

### Checklist

- [ ] PHP 8.2+, Laravel 12.61.1+ or 13.12.0+
- [ ] Replace `->strict()` calls on null and date range filters (strict is the default now)
- [ ] Review boolean, null, trashed, range, date range and DYNAMIC filters for clients that send other values
- [ ] Review clients that use `%`/`_` as LIKE wildcards or send `+` unencoded in date offsets
- [ ] Check chained `prepareValueWith()` calls
- [ ] Check config limits: `0`/`''`/`false` now throw; missing limits now apply
- [ ] Replace nested count/aggregate sorts and includes with callbacks
- [ ] Handle `errorCode` in your exception renderer if you map errors
- [ ] Flush response caches

---

## v3.0.x Internal Refactoring

This section covers internal refactoring changes that may affect advanced usage.

### Configuration After Builder Methods Now Throws LogicException

**Breaking Change:** Calling configuration methods (`allowedFilters`, `allowedSorts`, etc.) **after** query builder methods (`where`, `orderBy`, etc.) now throws a `LogicException`. Previously, this silently lost the builder modifications.

**Before:**
```php
// Silently lost where() — bug
$wizard = EloquentQueryWizard::for(User::class);
$wizard->where('active', true);
$wizard->allowedFilters('name');
$wizard->get(); // where('active', true) was lost!
```

**After:**
```php
// Now throws LogicException with a descriptive message
$wizard = EloquentQueryWizard::for(User::class);
$wizard->where('active', true);
$wizard->allowedFilters('name'); // LogicException!
```

**Migration:** Ensure all configuration methods are called before query builder methods:
```php
// Option 1: Configuration first, builder methods last
EloquentQueryWizard::for(User::class)
    ->allowedFilters('name')
    ->where('active', true)
    ->get();

// Option 2: Base scopes via for()
EloquentQueryWizard::for(User::where('active', true))
    ->allowedFilters('name')
    ->get();
```

**Rationale:** The previous behavior silently discarded query conditions, leading to hard-to-debug data leaks. The exception makes the incorrect ordering immediately visible and suggests correct alternatives.

### Count Includes No Longer Auto-Allowed

**Breaking Change:** Allowing a relationship include no longer automatically allows its count variant.

**Before:**
```php
->allowedIncludes('posts')  // Implicitly allowed ?include=postsCount too
```

**After:**
```php
->allowedIncludes('posts')                // Only allows ?include=posts
->allowedIncludes('posts', 'postsCount')  // Explicitly allow both
// Or using the factory:
->allowedIncludes('posts', EloquentInclude::count('posts'))
```

Similarly, `disallowedIncludes('posts')` no longer automatically blocks `postsCount`. Each must be disallowed explicitly.

**Rationale:** Implicit auto-allowing violates the whitelist principle and can lead to unintended data exposure. Each allowed include should be explicitly declared.

### Filter Modifier Methods Now Mutate

**Breaking Change:** Filter modifier methods now **mutate** the original object instead of returning clones.

**Before:**
```php
$filter = EloquentFilter::exact('status');
$clone = $filter->withoutRelationConstraint();  // Returned clone, $filter unchanged
```

**After:**
```php
$filter = EloquentFilter::exact('status');
$filter->withoutRelationConstraint();  // Mutates $filter!

// For independent copies, use clone:
$original = EloquentFilter::exact('status');
$copy = (clone $original)->withoutRelationConstraint();  // $original unchanged
```

**Affected methods:**
- `ExactFilter::withRelationConstraint()` / `withoutRelationConstraint()`
- `PartialFilter::withRelationConstraint()` / `withoutRelationConstraint()` (inherits from ExactFilter)
- `ScopeFilter::withModelBinding()` / `withoutModelBinding()`
- `NullFilter::withInvertedLogic()` / `withoutInvertedLogic()`
- `RangeFilter::minKey()`, `maxKey()`
- `DateRangeFilter::fromKey()`, `toKey()`, `dateFormat()`
- `JsonContainsFilter::matchAll()`, `matchAny()`

**Rationale:** Aligns with Laravel Eloquent's fluent pattern where methods mutate the original object.

### ScopeFilter Model Binding Disabled by Default

**Breaking Change:** Model binding is now **disabled by default** for security. Method renamed from `resolveModelBindings()` to `withModelBinding()`.

**Before:**
```php
// Model binding was enabled by default
EloquentFilter::scope('byAuthor')  // Auto-resolved User model from ID

// To disable:
EloquentFilter::scope('byAuthor')->resolveModelBindings(false)
```

**After:**
```php
// Model binding is now disabled by default
EloquentFilter::scope('byAuthor')  // Value passed as-is (string/int)

// To enable model binding (if needed):
EloquentFilter::scope('byAuthor')->withModelBinding()
```

**Rationale:** Auto-loading models by ID without authorization is a security risk. Users who need this feature must now explicitly opt-in.

### Renamed Filter Methods

**Breaking Change:** Several filter methods have been renamed to follow Laravel's `with*`/`without*` naming convention.

| Old Method | New Methods |
|------------|-------------|
| `withRelationConstraint(false)` | `withoutRelationConstraint()` |
| `withRelationConstraint(true)` | `withRelationConstraint()` |
| `invertLogic(true)` | `withInvertedLogic()` |
| `invertLogic(false)` | `withoutInvertedLogic()` |
| `matchAll(false)` | `matchAny()` |
| `matchAll(true)` | `matchAll()` (no parameter) |

**Before:**
```php
EloquentFilter::exact('posts.status')->withRelationConstraint(false)
EloquentFilter::null('verified_at')->invertLogic(true)
EloquentFilter::jsonContains('tags')->matchAll(false)
```

**After:**
```php
EloquentFilter::exact('posts.status')->withoutRelationConstraint()
EloquentFilter::null('verified_at')->withInvertedLogic()
EloquentFilter::jsonContains('tags')->matchAny()
```

### New Sort Types

Two new sort types have been added:

```php
use Jackardios\QueryWizard\Eloquent\EloquentSort;

// Sort by relationship count
EloquentSort::count('posts')                    // ?sort=posts or ?sort=-posts
EloquentSort::count('comments')->alias('popularity')

// Sort by related model's aggregate
EloquentSort::relation('orders', 'total', 'sum')     // Sort by sum of order totals
EloquentSort::relation('posts', 'created_at', 'max') // Sort by newest post date
```

### Laravel Octane Support

The package is now fully compatible with Laravel Octane:
- `QueryParametersManager` uses `scoped()` binding for per-request instances
- No static state that leaks between requests

---

# Upgrade Guide: v2.x to v3.0

## Overview

Version 3.0 is a significant rewrite with a cleaner architecture:

- **Simplified class structure** - Removed trait-based architecture in favor of class inheritance
- **Fluent configuration API** - Method names changed from `setAllowed*()` to `allowed*()`
- **New filter types** - Added Range, DateRange, Null, JsonContains, Passthrough, and Operator filters
- **Resource Schemas** - Declarative configuration for reusable query definitions
- **Security limits** - Built-in protection against resource exhaustion attacks
- **Improved type safety** - Better interfaces and strict types throughout

## Breaking Changes Summary

| v2.x | v3.0 |
|------|------|
| `->setAllowedFilters([...])` | `->allowedFilters(...)` (variadic) |
| `->setAllowedSorts([...])` | `->allowedSorts(...)` |
| `->setDefaultSorts([...])` | `->defaultSorts(...)` |
| `new ExactFilter(...)` | `EloquentFilter::exact(...)` |
| `new FieldSort(...)` | `EloquentSort::field(...)` |
| `new RelationshipInclude(...)` | `EloquentInclude::relationship(...)` |
| `Model\ModelQueryWizard->build()` | `ModelQueryWizard->process()` |

## Migration Steps

### 1. Update Method Names

The `set` prefix has been removed from all configuration methods.

**Before (v2.x):**
```php
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;

$users = EloquentQueryWizard::for(User::class)
    ->setAllowedFilters(['name', 'email'])
    ->setAllowedSorts(['created_at'])
    ->setAllowedIncludes(['posts'])
    ->setDefaultSorts(['-created_at'])
    ->get();
```

**After (v3.0):**
```php
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;

$users = EloquentQueryWizard::for(User::class)
    ->allowedFilters('name', 'email')
    ->allowedSorts('created_at')
    ->allowedIncludes('posts')
    ->defaultSorts('-created_at')
    ->get();
```

**Note:** v3.0 methods accept variadic arguments, so you can pass items directly instead of wrapping in an array.

### 2. Update Filter Instantiation

Filters are now created using factory methods instead of constructors.

**Before (v2.x):**
```php
use Jackardios\QueryWizard\Eloquent\Filters\ExactFilter;
use Jackardios\QueryWizard\Eloquent\Filters\PartialFilter;
use Jackardios\QueryWizard\Eloquent\Filters\ScopeFilter;
use Jackardios\QueryWizard\Eloquent\Filters\TrashedFilter;
use Jackardios\QueryWizard\Eloquent\Filters\CallbackFilter;

->setAllowedFilters([
    new ExactFilter('status'),
    new PartialFilter('name'),
    new ScopeFilter('active'),
    new TrashedFilter('trashed'),
    new CallbackFilter('custom', function($wizard, $builder, $value, $property) {
        $builder->where('field', $value);
    }),
])
```

**After (v3.0):**
```php
use Jackardios\QueryWizard\Eloquent\EloquentFilter;

->allowedFilters(
    EloquentFilter::exact('status'),
    EloquentFilter::partial('name'),
    EloquentFilter::scope('active'),
    EloquentFilter::trashed(),
    EloquentFilter::callback('custom', function($query, $value, $property) {
        $query->where('field', $value);
    }),
)
```

### 3. Update Filter Options

Filter configuration now uses fluent methods instead of constructor arguments.

**Before (v2.x):**
```php
new ExactFilter('user_id', 'user', 'default_value', false)
// Arguments: property, alias, default, withRelationConstraint
```

**After (v3.0):**
```php
EloquentFilter::exact('user_id', 'user')
    ->default('default_value')
    ->withoutRelationConstraint()
```

### 4. Update Callback Signatures

Callback filter/sort/include signatures have changed - the wizard instance is no longer passed.

| Type | v2.x Signature | v3.0 Signature |
|------|---------------|----------------|
| Filter | `($wizard, $builder, $value, $property)` | `($query, $value, $property)` |
| Include | `($wizard, $builder)` | `($query, $relation)` |
| Sort | `($wizard, $builder, $direction)` | `($query, $direction, $property)` |

**Before (v2.x):**
```php
new CallbackFilter('custom', function($wizard, $builder, $value, $property) {
    $builder->where('field', $value);
});
```

**After (v3.0):**
```php
EloquentFilter::callback('custom', function($query, $value, $property) {
    $query->where('field', $value);
});
```

### 5. Update Sort Instantiation

**Before (v2.x):**
```php
use Jackardios\QueryWizard\Eloquent\Sorts\FieldSort;
use Jackardios\QueryWizard\Eloquent\Sorts\CallbackSort;

->setAllowedSorts([
    new FieldSort('name'),
    new CallbackSort('custom', function($wizard, $builder, $direction) {
        $builder->orderBy('field', $direction);
    }),
])
```

**After (v3.0):**
```php
use Jackardios\QueryWizard\Eloquent\EloquentSort;

->allowedSorts(
    EloquentSort::field('name'),
    EloquentSort::callback('custom', function($query, $direction, $property) {
        $query->orderBy('field', $direction);
    }),
)
```

### 6. Update Include Instantiation

**Before (v2.x):**
```php
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Eloquent\Includes\CountInclude;
use Jackardios\QueryWizard\Eloquent\Includes\CallbackInclude;

->setAllowedIncludes([
    new RelationshipInclude('posts'),
    new CountInclude('comments', 'commentsCount'),
    new CallbackInclude('custom', function($wizard, $builder) {
        $builder->with('relation');
    }),
])
```

**After (v3.0):**
```php
use Jackardios\QueryWizard\Eloquent\EloquentInclude;

->allowedIncludes(
    EloquentInclude::relationship('posts'),
    EloquentInclude::count('comments', 'commentsCount'),
    EloquentInclude::callback('custom', function($query, $relation) {
        $query->with('relation');
    }),
)
```

### 7. Update ModelQueryWizard

The namespace has changed from `Model\ModelQueryWizard` to root namespace.

**Before (v2.x):**
```php
use Jackardios\QueryWizard\Model\ModelQueryWizard;

$user = User::find(1);
$result = ModelQueryWizard::for($user)
    ->setAllowedIncludes(['posts'])
    ->setAllowedFields(['id', 'name'])
    ->build();
```

**After (v3.0):**
```php
use Jackardios\QueryWizard\ModelQueryWizard;

$user = User::find(1);
$result = ModelQueryWizard::for($user)
    ->allowedIncludes('posts')
    ->allowedFields('id', 'name')
    ->process();
```

**Note:** The `build()` method is now `process()` for ModelQueryWizard.

### 8. Update Namespace Imports

| v2.x | v3.0 |
|------|------|
| `Model\ModelQueryWizard` | `ModelQueryWizard` (root namespace) |
| `Filters\*Filter` | `EloquentFilter` (factory) |
| `Sorts\*Sort` | `EloquentSort` (factory) |
| `Includes\*Include` | `EloquentInclude` (factory) |

### 9. Replace Custom QueryWizard Classes with Schemas

In v2.x, a common pattern was to create dedicated QueryWizard subclasses for each resource by overriding configuration methods. Typically you needed two classes per resource: one for collections (plural) and one for single models (singular).

**Before (v2.x):**
```php
// app/QueryWizards/UsersQueryWizard.php (for collections)
class UsersQueryWizard extends EloquentQueryWizard
{
    protected function allowedFilters(): array
    {
        return ['name', 'email', 'status'];
    }

    protected function allowedSorts(): array
    {
        return ['created_at', 'name'];
    }

    protected function allowedIncludes(): array
    {
        return ['posts', 'profile'];
    }
}

// app/QueryWizards/UserQueryWizard.php (for single models)
class UserQueryWizard extends ModelQueryWizard
{
    protected function allowedIncludes(): array
    {
        return ['posts', 'profile'];  // Duplicated configuration!
    }

    protected function allowedFields(): array
    {
        return ['id', 'name', 'email'];
    }
}

// Usage
$users = UsersQueryWizard::for(User::class)->get();
$user = UserQueryWizard::for(User::find(1))->build();
```

**After (v3.0):**

Instead of two subclasses with duplicated configuration, use a single `ResourceSchema` that works with both `EloquentQueryWizard` and `ModelQueryWizard`:

```php
// app/Schemas/UserSchema.php
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
        return ['name', 'email', 'status'];
    }

    public function sorts(QueryWizardInterface $wizard): array
    {
        return ['created_at', 'name'];
    }

    public function includes(QueryWizardInterface $wizard): array
    {
        return ['posts', 'profile'];  // Shared between both wizards
    }

    public function fields(QueryWizardInterface $wizard): array
    {
        return ['id', 'name', 'email'];
    }
}

// Usage with EloquentQueryWizard (replaces UsersQueryWizard)
$users = EloquentQueryWizard::forSchema(UserSchema::class)->get();

// Usage with ModelQueryWizard (replaces UserQueryWizard)
$user = User::find(1);
ModelQueryWizard::for($user)->schema(UserSchema::class)->process();
```

**Conditional configuration based on wizard type:**

The `$wizard` parameter allows you to return different configurations depending on whether the schema is used with `EloquentQueryWizard` or `ModelQueryWizard`:

```php
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\ModelQueryWizard;

class UserSchema extends ResourceSchema
{
    public function model(): string
    {
        return User::class;
    }

    public function filters(QueryWizardInterface $wizard): array
    {
        // Filters only make sense for EloquentQueryWizard (database queries)
        // ModelQueryWizard works with already-loaded models, so filters are ignored
        return [
            'name',
            'email',
            EloquentFilter::exact('status'),
            EloquentFilter::scope('active'),
            EloquentFilter::dateRange('created_at'),
        ];
    }

    public function sorts(QueryWizardInterface $wizard): array
    {
        // Sorts also only apply to EloquentQueryWizard
        return ['created_at', 'name', 'email'];
    }

    public function includes(QueryWizardInterface $wizard): array
    {
        // Base includes shared by both wizards
        $includes = ['posts', 'profile', 'roles'];

        // Count/exists includes only work with EloquentQueryWizard
        if ($wizard instanceof EloquentQueryWizard) {
            $includes[] = EloquentInclude::count('posts');
            $includes[] = EloquentInclude::exists('subscription');
        }

        return $includes;
    }

    public function fields(QueryWizardInterface $wizard): array
    {
        $fields = ['id', 'name', 'email', 'created_at'];

        // Include sensitive fields only for single model requests
        if ($wizard instanceof ModelQueryWizard) {
            $fields[] = 'phone';
            $fields[] = 'address';
        }

        return $fields;
    }

    public function appends(QueryWizardInterface $wizard): array
    {
        $appends = ['full_name', 'avatar_url'];

        // Heavy computed appends only for single models (avoid N+1 on collections)
        if ($wizard instanceof ModelQueryWizard) {
            $appends[] = 'permissions_summary';
            $appends[] = 'activity_stats';
        }

        return $appends;
    }

    public function defaultIncludes(QueryWizardInterface $wizard): array
    {
        // Always load profile for single model, but not for collections
        if ($wizard instanceof ModelQueryWizard) {
            return ['profile'];
        }

        return [];
    }
}
```

**Benefits:**
- **No duplication**: One schema replaces two classes, shared configuration stays in one place
- **Reusability**: Same schema works with both `EloquentQueryWizard` and `ModelQueryWizard`
- **Separation of concerns**: Query configuration is separate from query execution
- **Flexibility**: Override schema settings per-request using `disallowed*()` methods
- **Testability**: Schemas are plain PHP classes, easy to unit test

## New Features in v3.0

### New Filter Types

```php
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Enums\FilterOperator;

EloquentFilter::range('price')           // ?filter[price][min]=10&filter[price][max]=100
EloquentFilter::dateRange('created_at')  // ?filter[created_at][from]=2024-01-01&filter[created_at][to]=2024-12-31
EloquentFilter::null('deleted_at')       // ?filter[deleted_at]=true → IS NULL
EloquentFilter::jsonContains('tags')     // ?filter[tags]=laravel,php
EloquentFilter::passthrough('context')   // Captured, not applied. Use getPassthroughFilters()

// Operator filter with comparison operators
EloquentFilter::operator('age', FilterOperator::GREATER_THAN)  // ?filter[age]=18 → age > 18
EloquentFilter::operator('price', FilterOperator::DYNAMIC)     // ?filter[price]=>=100 → price >= 100
// Operators: EQUAL, NOT_EQUAL, GREATER_THAN, GREATER_THAN_OR_EQUAL, LESS_THAN, LESS_THAN_OR_EQUAL, LIKE, NOT_LIKE, DYNAMIC
```

### Exists Include

```php
EloquentInclude::exists('posts')  // ?include=postsExists → adds posts_exists boolean attribute
```

### Resource Schemas

Schemas provide declarative, reusable configuration:

```php
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;

class UserSchema extends ResourceSchema
{
    public function model(): string
    {
        return User::class;
    }

    public function type(): string
    {
        return 'user';  // Key for ?fields[user]=id,name (default: camelCase of model)
    }

    public function filters(QueryWizardInterface $wizard): array
    {
        return [
            'name',
            EloquentFilter::partial('email'),
            EloquentFilter::exact('status'),
        ];
    }

    public function sorts(QueryWizardInterface $wizard): array
    {
        return ['created_at', 'name'];
    }

    public function includes(QueryWizardInterface $wizard): array
    {
        return ['posts', 'profile'];
    }

    public function defaultSorts(QueryWizardInterface $wizard): array
    {
        return ['-created_at'];
    }

    public function defaultFilters(QueryWizardInterface $wizard): array
    {
        return ['status' => 'active'];  // Applied when filter absent from request
    }
}

// Usage
EloquentQueryWizard::forSchema(UserSchema::class)->get();
```

### Security Limits

Protection against resource exhaustion attacks:

```php
// config/query-wizard.php
'limits' => [
    'max_includes_count' => 10,    // Max includes per request
    'max_include_depth' => 3,      // Max nesting (posts.comments.author)
    'max_filters_count' => 20,     // Max filters per request
    'max_appends_count' => 10,     // Max appends per request
    'max_append_depth' => 3,       // Max append nesting
    'max_sorts_count' => 5,        // Max sorts per request
],
```

### Disallowed Methods

Override schema configuration with wildcard support:

```php
EloquentQueryWizard::forSchema(UserSchema::class)
    ->disallowedFilters('status', 'secret_*')    // Wildcard matching
    ->disallowedIncludes('auditLogs')
    ->disallowedFields('*')                      // Block all fields
    ->disallowedFields('posts.*')                // Block direct children only
    ->disallowedFields('posts')                  // Block relation + all descendants
    ->get();
```

### Fluent Filter Modifiers

```php
EloquentFilter::exact('status')
    ->alias('state')                              // URL parameter name
    ->default('active')                           // Default value
    ->prepareValueWith(fn($v) => strtolower($v))  // Transform value
    ->asBoolean()                                 // Convert "true"/"1" → true, "false"/"0" → false
    ->when(fn($value) => $value !== 'all')        // Skip filter conditionally
```

### tap() Method

Modify the query builder directly:

```php
EloquentQueryWizard::for(User::class)
    ->tap(fn($query) => $query->where('tenant_id', auth()->user()->tenant_id))
    ->allowedFilters('name')
    ->get();
```

### applyPostProcessingTo()

Apply fields/appends to externally fetched models:

```php
$wizard = EloquentQueryWizard::for(User::class)
    ->allowedFields('id', 'name')
    ->allowedAppends('full_name');

$user = $wizard->toQuery()->find($id);  // find() bypasses wizard
$wizard->applyPostProcessingTo($user);  // Apply fields/appends manually
```

## Removed Features

- **Abstract base classes** (`Abstracts\*`) - replaced by factory methods
- **Old base classes** (`EloquentFilter`, `EloquentSort`, `EloquentInclude`) - now factories
- **Model handler classes** (`Model\Includes\*`, `Model\ModelInclude`)
- **Helper functions** (`instance_of_one_of()`)
- **Methods**: `makeDefault*Handler()`, `getAllowedFilters()`, `getFilters()` → `getPassthroughFilters()`, `handleModels()` → `applyPostProcessingTo()`

## Configuration Changes

### New Configuration Options

```php
// config/query-wizard.php
return [
    // Naming conventions
    'naming' => [
        'convert_parameters_to_snake_case' => false,  // ?filter[firstName] → first_name
    ],

    // Per-type separators (default: array_value_separator)
    'separators' => [
        'filters' => ';',  // Use semicolon to allow commas in filter values
    ],

    // Eager loading optimization
    'optimizations' => [
        'relation_select_mode' => 'safe',  // Auto-injects FK columns for eager loading
    ],

    // Security limits (null = disabled)
    'limits' => [
        'max_includes_count' => 10,
        'max_include_depth' => 3,
        'max_filters_count' => 20,
        'max_appends_count' => 20,
        'max_append_depth' => 3,
        'max_sorts_count' => 5,
    ],
];
```

### New Exception Types

Limit exceptions (extend `QueryLimitExceeded` → `InvalidQuery`):
- `MaxFiltersCountExceeded`, `MaxSortsCountExceeded`, `MaxIncludesCountExceeded`
- `MaxIncludeDepthExceeded`, `MaxAppendsCountExceeded`, `MaxAppendDepthExceeded`

Other:
- `InvalidFilterValue` - thrown when filter value fails validation

## Quick Migration Checklist

- [ ] Rename `setAllowed*()` → `allowed*()`, `setDefault*()` → `default*()`
- [ ] Replace filter/sort/include constructors with factory methods
- [ ] Update callback signatures: remove `$wizard`, rename `$builder` → `$query`
- [ ] Update `ModelQueryWizard`: new namespace, `build()` → `process()`
- [ ] Remove array wrappers (methods are now variadic)
- [ ] Add `->withModelBinding()` to ScopeFilters that need model binding
- [ ] Explicitly allow count/exists includes (no longer auto-allowed)
- [ ] Ensure config methods called before builder methods (or use `tap()`)
- [ ] Review renamed methods: `withRelationConstraint(false)` → `withoutRelationConstraint()`
- [ ] Review security limits in config
- [ ] Replace custom `*QueryWizard` subclasses with `ResourceSchema`

## Need Help?

If you encounter issues during migration, please open an issue on GitHub with:
1. Your v2.x code that needs migration
2. Any error messages you receive
3. Your Laravel and PHP versions

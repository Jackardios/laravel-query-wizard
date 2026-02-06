# Upgrade Guide

This document describes how to upgrade Laravel Query Wizard between versions.

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

### New Support Classes

The following classes have been extracted for better separation of concerns:

| Class | Purpose |
|-------|---------|
| `Support\ParameterParser` | Parses list/sort/fields parameters |
| `Support\FilterValueTransformer` | Transforms filter values (booleans, arrays) |
| `Concerns\HandlesAppends` | Shared append handling logic |

These are internal implementation details and should not affect most users.

### Updated Traits

- `HandlesAppends` trait is now used by both `BaseQueryWizard` and `ModelQueryWizard`
- `HandlesIncludes` and `HandlesFields` traits added for shared logic
- Reduces code duplication between wizard classes

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

### Conditional Filters with `when()`

Filters now support conditional application via `when()`:

```php
EloquentFilter::exact('status')
    ->when(fn($value) => $value !== 'all')  // Skip filter if value is 'all'

EloquentFilter::exact('user_id')
    ->when(fn($value) => auth()->check())   // Only apply if user is authenticated
```

### Laravel Octane Support

The package is now fully compatible with Laravel Octane:
- `QueryParametersManager` uses `scoped()` binding for per-request instances
- No static state that leaks between requests

---

# Upgrade Guide: v2.x to v3.0

This document describes how to upgrade Laravel Query Wizard from version 2.x to 3.0.

## Overview

Version 3.0 is a significant rewrite with a cleaner architecture:

- **Simplified class structure** - Removed trait-based architecture in favor of class inheritance
- **Fluent configuration API** - Method names changed from `setAllowed*()` to `allowed*()`
- **New filter types** - Added Range, DateRange, Null, JsonContains, and Passthrough filters
- **Resource Schemas** - Declarative configuration for reusable query definitions
- **Security limits** - Built-in protection against resource exhaustion attacks
- **Improved type safety** - Better interfaces and strict types throughout

## Breaking Changes Summary

| v2.x | v3.0 |
|------|------|
| `EloquentQueryWizard::for($subject)` | `EloquentQueryWizard::for($subject)` (unchanged) |
| `->setAllowedFilters([...])` | `->allowedFilters(...)` |
| `->setAllowedSorts([...])` | `->allowedSorts(...)` |
| `->setAllowedIncludes([...])` | `->allowedIncludes(...)` |
| `->setAllowedFields([...])` | `->allowedFields(...)` |
| `->setAllowedAppends([...])` | `->allowedAppends(...)` |
| `->setDefaultSorts([...])` | `->defaultSorts(...)` |
| `new ExactFilter($property, $alias)` | `EloquentFilter::exact($property, $alias)` |
| `Model\ModelQueryWizard` | `ModelQueryWizard` (moved to root namespace) |
| Traits in `Concerns/` | Consolidated into base classes |

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
| `Jackardios\QueryWizard\Eloquent\EloquentQueryWizard` | `Jackardios\QueryWizard\Eloquent\EloquentQueryWizard` (unchanged) |
| `Jackardios\QueryWizard\Model\ModelQueryWizard` | `Jackardios\QueryWizard\ModelQueryWizard` |
| `Jackardios\QueryWizard\Eloquent\Filters\ExactFilter` | `Jackardios\QueryWizard\Eloquent\EloquentFilter` (factory) |
| `Jackardios\QueryWizard\Eloquent\Filters\PartialFilter` | `Jackardios\QueryWizard\Eloquent\EloquentFilter` (factory) |
| `Jackardios\QueryWizard\Eloquent\Filters\ScopeFilter` | `Jackardios\QueryWizard\Eloquent\EloquentFilter` (factory) |
| `Jackardios\QueryWizard\Eloquent\Filters\TrashedFilter` | `Jackardios\QueryWizard\Eloquent\EloquentFilter` (factory) |
| `Jackardios\QueryWizard\Eloquent\Filters\CallbackFilter` | `Jackardios\QueryWizard\Eloquent\EloquentFilter` (factory) |
| `Jackardios\QueryWizard\Eloquent\Sorts\FieldSort` | `Jackardios\QueryWizard\Eloquent\EloquentSort` (factory) |
| `Jackardios\QueryWizard\Eloquent\Sorts\CallbackSort` | `Jackardios\QueryWizard\Eloquent\EloquentSort` (factory) |
| `Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude` | `Jackardios\QueryWizard\Eloquent\EloquentInclude` (factory) |
| `Jackardios\QueryWizard\Eloquent\Includes\CountInclude` | `Jackardios\QueryWizard\Eloquent\EloquentInclude` (factory) |
| `Jackardios\QueryWizard\Eloquent\Includes\CallbackInclude` | `Jackardios\QueryWizard\Eloquent\EloquentInclude` (factory) |

## New Features in v3.0

### New Filter Types

```php
use Jackardios\QueryWizard\Eloquent\EloquentFilter;

// Numeric range filtering
EloquentFilter::range('price')           // ?filter[price][min]=10&filter[price][max]=100

// Date range filtering with Carbon parsing
EloquentFilter::dateRange('created_at')  // ?filter[created_at][from]=2024-01-01&filter[created_at][to]=2024-12-31

// NULL/NOT NULL checking
EloquentFilter::null('deleted_at')       // ?filter[deleted_at]=true (IS NULL)

// JSON column containment
EloquentFilter::jsonContains('tags')     // ?filter[tags]=laravel,php

// Capture value without modifying query
EloquentFilter::passthrough('context')   // Retrieved via getPassthroughFilters()
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

Override schema configuration for specific endpoints:

```php
EloquentQueryWizard::forSchema(UserSchema::class)
    ->disallowedFilters('status', 'role')      // Remove from schema
    ->disallowedIncludes('sensitiveRelation')   // Remove from schema
    ->get();
```

### Fluent Filter Modifiers

```php
EloquentFilter::exact('status')
    ->alias('state')                           // URL parameter name
    ->default('active')                        // Default value
    ->prepareValueWith(fn($v) => strtolower($v))  // Transform value
```

## Removed Features

### Classes Removed

- `Jackardios\QueryWizard\Abstracts\AbstractFilter` - Use `EloquentFilter` factory
- `Jackardios\QueryWizard\Abstracts\AbstractInclude` - Use `EloquentInclude` factory
- `Jackardios\QueryWizard\Abstracts\AbstractSort` - Use `EloquentSort` factory
- `Jackardios\QueryWizard\Abstracts\AbstractQueryWizard` - Use `BaseQueryWizard`
- `Jackardios\QueryWizard\Eloquent\EloquentFilter` (old base class) - Replaced by factory
- `Jackardios\QueryWizard\Eloquent\EloquentInclude` (old base class) - Replaced by factory
- `Jackardios\QueryWizard\Eloquent\EloquentSort` (old base class) - Replaced by factory
- `Jackardios\QueryWizard\Model\ModelInclude` - Consolidated into includes
- All handler classes in `Model\Includes\*`

### Traits Restructured

The following traits still exist but are now internal shared traits with different responsibilities than in v2:

- `Jackardios\QueryWizard\Concerns\HandlesFilters` — filter validation
- `Jackardios\QueryWizard\Concerns\HandlesSorts` — sort validation
- `Jackardios\QueryWizard\Concerns\HandlesIncludes` — include handling and validation
- `Jackardios\QueryWizard\Concerns\HandlesFields` — field handling
- `Jackardios\QueryWizard\Concerns\HandlesAppends` — append handling and validation

### Methods Changed/Removed

| v2.x | v3.0 |
|------|------|
| `makeDefaultFilterHandler()` | Not needed - use factory methods |
| `makeDefaultIncludeHandler()` | Not needed - use factory methods |
| `makeDefaultSortHandler()` | Not needed - use factory methods |
| `getAllowedFilters()` | Internal only |
| `getFilters()` | `getPassthroughFilters()` for passthrough filters |
| `handleModels()` | `applyAppendsTo()` |
| `build()` on ModelQueryWizard | `process()` |

### Helper Functions Removed

- `instance_of_one_of()` - Was in `src/helpers.php`, no longer needed

## Configuration Changes

### New Configuration Options

```php
// config/query-wizard.php
return [
    // ... existing options ...

    // NEW: Security limits
    'limits' => [
        'max_includes_count' => 10,
        'max_include_depth' => 3,
        'max_filters_count' => 20,
        'max_appends_count' => 10,
        'max_append_depth' => 3,
        'max_sorts_count' => 5,
    ],
];
```

### New Exception Types

- `MaxFiltersCountExceeded`
- `MaxSortsCountExceeded`
- `MaxIncludesCountExceeded`
- `MaxIncludeDepthExceeded`
- `MaxAppendsCountExceeded`
- `MaxAppendDepthExceeded`

All extend `QueryLimitExceeded` which extends `InvalidQuery`.

## Quick Migration Checklist

- [ ] Update method names: `setAllowed*` → `allowed*`
- [ ] Update filter imports to use `EloquentFilter` factory
- [ ] Update sort imports to use `EloquentSort` factory
- [ ] Update include imports to use `EloquentInclude` factory
- [ ] Update callback signatures (remove `$wizard` parameter)
- [ ] Update `ModelQueryWizard` namespace and use `process()` instead of `build()`
- [ ] Remove array wrappers in method calls (now variadic)
- [ ] Review security limits in config (new feature)
- [ ] Consider using Resource Schemas for reusable configuration

## Need Help?

If you encounter issues during migration, please open an issue on GitHub with:
1. Your v2.x code that needs migration
2. Any error messages you receive
3. Your Laravel and PHP versions

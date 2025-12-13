# Upgrade Guide: v2.x to v3.0.0

This document describes how to upgrade Laravel Query Wizard from version 2.x to 3.0.0.

## Overview

Version 3.0.0 is a **complete rewrite** of the package with a new architecture based on:

- **Driver-based system** - Extensible architecture supporting multiple query backends
- **Schema-based API** - Declarative resource schemas for cleaner code organization
- **Strategy pattern** - Filters, sorts, and includes use pluggable strategies
- **Definition objects** - Immutable, fluent definition builders replace handler classes

### Key Benefits

- Cleaner separation of concerns
- Easier extensibility (custom drivers, strategies)
- Better type safety with interfaces and strict types
- Support for both list and single-item queries
- Context-aware schemas (different rules for list vs item views)

---

## Breaking Changes Summary

| v2.x | v3.0.0 |
|------|--------|
| `EloquentQueryWizard::for($subject)` | `QueryWizard::for($subject)` |
| `ModelQueryWizard::for($model)` | `QueryWizard::forItem($schema, $key)` |
| Handler classes (`ExactFilter`, etc.) | Definition objects (`FilterDefinition::exact()`) |
| `setAllowed*()` returns handlers | `setAllowed*()` returns wizard |
| Traits in `src/Concerns/` | Traits in `src/Wizards/Concerns/` |
| Direct method chaining on builder | Explicit `build()` or terminal methods |

---

## Migration Steps

### 1. Update Entry Point

**Before (v2.x):**
```php
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;

$users = EloquentQueryWizard::for(User::class)
    ->setAllowedFilters(['name', 'email'])
    ->get();
```

**After (v3.0.0):**
```php
use Jackardios\QueryWizard\QueryWizard;

$users = QueryWizard::for(User::class)
    ->setAllowedFilters('name', 'email')
    ->get();
```

The main entry point changed from `EloquentQueryWizard::for()` to `QueryWizard::for()`.

### 2. Replace Handler Classes with Definitions

Filter, include, and sort handlers have been replaced with definition objects.

#### Filters

**Before (v2.x):**
```php
use Jackardios\QueryWizard\Eloquent\Filters\ExactFilter;
use Jackardios\QueryWizard\Eloquent\Filters\PartialFilter;
use Jackardios\QueryWizard\Eloquent\Filters\ScopeFilter;
use Jackardios\QueryWizard\Eloquent\Filters\CallbackFilter;
use Jackardios\QueryWizard\Eloquent\Filters\TrashedFilter;

->setAllowedFilters([
    new ExactFilter('status'),
    new PartialFilter('name'),
    new ScopeFilter('active'),
    new TrashedFilter('trashed'),
    new CallbackFilter('custom', function($wizard, $builder, $value) {
        $builder->where('field', $value);
    }),
])
```

**After (v3.0.0):**
```php
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\FilterDefinition;

->setAllowedFilters(
    FilterDefinition::exact('status'),
    FilterDefinition::partial('name'),
    FilterDefinition::scope('active'),
    FilterDefinition::trashed(),
    FilterDefinition::callback('custom', function($query, $value, $property) {
        $query->where('field', $value);
    }),
)
```

**New filter types in v3.0.0:**
```php
FilterDefinition::range('price')           // Supports min/max range filtering
FilterDefinition::dateRange('created_at')  // Date range with Carbon parsing
FilterDefinition::null('deleted_at')       // Filter by null/not null
FilterDefinition::jsonContains('tags')     // JSON column contains value
```

#### Filter Options (Fluent API)

**Before (v2.x):**
```php
new ExactFilter('name', 'n', 'default_value', false) // alias, default, withRelationConstraint
```

**After (v3.0.0):**
```php
FilterDefinition::exact('name', 'n')
    ->default('default_value')
    ->withRelationConstraint(false)
    ->prepareValueWith(fn($v) => strtolower($v))
```

#### Includes

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

**After (v3.0.0):**
```php
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\IncludeDefinition;

->setAllowedIncludes(
    IncludeDefinition::relationship('posts'),
    IncludeDefinition::count('comments', 'commentsCount'),
    IncludeDefinition::callback('custom', function($query, $include, $fields) {
        $query->with('relation');
    }),
)
```

#### Sorts

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

**After (v3.0.0):**
```php
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\SortDefinition;

->setAllowedSorts(
    SortDefinition::field('name'),
    SortDefinition::callback('custom', function($query, $sort, $direction) {
        $query->orderBy('field', $direction);
    }),
)
```

### 3. Callback Signature Changes

Callback signatures have changed to be more consistent:

| Type | v2.x Signature | v3.0.0 Signature |
|------|---------------|------------------|
| Filter | `($wizard, $builder, $value, $property)` | `($query, $value, $property)` |
| Include | `($wizard, $builder)` | `($query, $include, $fields)` |
| Sort | `($wizard, $builder, $direction)` | `($query, $sort, $direction)` |

The wizard instance is no longer passed to callbacks. If you need access to parameters, inject `QueryParametersManager` or use closure binding.

### 4. ModelQueryWizard Replacement

`ModelQueryWizard` has been replaced with `ItemQueryWizard` accessed via schemas.

**Before (v2.x):**
```php
use Jackardios\QueryWizard\Model\ModelQueryWizard;

$user = User::find(1);
$result = ModelQueryWizard::for($user)
    ->setAllowedIncludes(['posts'])
    ->setAllowedFields(['id', 'name'])
    ->build();
```

**After (v3.0.0):**
```php
use Jackardios\QueryWizard\QueryWizard;
use Jackardios\QueryWizard\Schema\ResourceSchema;

class UserSchema extends ResourceSchema
{
    public function model(): string
    {
        return User::class;
    }

    public function includes(): array
    {
        return ['posts'];
    }

    public function fields(): array
    {
        return ['id', 'name', 'email'];
    }
}

// With ID (loads from database)
$user = QueryWizard::forItem(UserSchema::class, 1)->get();

// With already loaded model
$user = User::find(1);
$result = QueryWizard::forItem(UserSchema::class, $user)->get();
```

### 5. Namespace Changes

Update your imports according to this mapping:

| v2.x Namespace | v3.0.0 Namespace |
|---------------|------------------|
| `Jackardios\QueryWizard\Eloquent\EloquentQueryWizard` | `Jackardios\QueryWizard\QueryWizard` |
| `Jackardios\QueryWizard\Model\ModelQueryWizard` | `Jackardios\QueryWizard\QueryWizard` (use `::forItem()`) |
| `Jackardios\QueryWizard\Eloquent\Filters\*` | `Jackardios\QueryWizard\Drivers\Eloquent\Definitions\FilterDefinition` |
| `Jackardios\QueryWizard\Eloquent\Includes\*` | `Jackardios\QueryWizard\Drivers\Eloquent\Definitions\IncludeDefinition` |
| `Jackardios\QueryWizard\Eloquent\Sorts\*` | `Jackardios\QueryWizard\Drivers\Eloquent\Definitions\SortDefinition` |
| `Jackardios\QueryWizard\Concerns\*` | `Jackardios\QueryWizard\Wizards\Concerns\*` |
| `Jackardios\QueryWizard\Abstracts\*` | Removed (use interfaces in `Contracts\`) |

### 6. Using Schemas (New Feature)

v3.0.0 introduces `ResourceSchema` for declarative configuration:

```php
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\FilterDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\SortDefinition;

class PostSchema extends ResourceSchema
{
    public function model(): string
    {
        return Post::class;
    }

    public function type(): string
    {
        return 'posts'; // Used as key in ?fields[posts]=id,title
    }

    public function filters(): array
    {
        return [
            'title',
            'status',
            FilterDefinition::partial('content'),
            FilterDefinition::scope('published'),
        ];
    }

    public function includes(): array
    {
        return ['author', 'comments', 'commentsCount'];
    }

    public function sorts(): array
    {
        return ['created_at', 'title', '-id'];
    }

    public function fields(): array
    {
        return ['id', 'title', 'content', 'status', 'created_at'];
    }

    public function appends(): array
    {
        return ['excerpt', 'reading_time'];
    }

    public function defaultSorts(): array
    {
        return ['-created_at'];
    }

    public function defaultIncludes(): array
    {
        return ['author'];
    }
}
```

Usage:
```php
// List query
$posts = QueryWizard::forList(PostSchema::class)->get();

// Single item
$post = QueryWizard::forItem(PostSchema::class, $id)->get();
```

### 7. Context-Aware Schemas

Schemas can provide different configurations for list vs item views:

```php
use Jackardios\QueryWizard\Schema\SchemaContext;

class UserSchema extends ResourceSchema
{
    public function model(): string
    {
        return User::class;
    }

    public function filters(): array
    {
        return ['name', 'email', 'status', 'role'];
    }

    public function includes(): array
    {
        return ['posts', 'comments', 'profile'];
    }

    // Customize for list operations
    public function forList(): ?SchemaContextInterface
    {
        return SchemaContext::make()
            ->disallowIncludes(['posts', 'comments']) // Too expensive for lists
            ->defaultSorts(['-created_at']);
    }

    // Customize for item operations
    public function forItem(): ?SchemaContextInterface
    {
        return SchemaContext::make()
            ->disallowFilters(['status', 'role']) // Not needed for single item
            ->defaultIncludes(['profile']);
    }
}
```

### 8. Custom Strategies (New Feature)

You can now create custom filter/sort/include strategies:

```php
use Jackardios\QueryWizard\Contracts\FilterStrategyInterface;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;

class MyCustomFilterStrategy implements FilterStrategyInterface
{
    public function apply(mixed $query, FilterDefinitionInterface $filter, mixed $value): mixed
    {
        // Custom filtering logic
        return $query->where($filter->getProperty(), 'CUSTOM_OP', $value);
    }
}

// Use with FilterDefinition::custom()
FilterDefinition::custom('field', MyCustomFilterStrategy::class)
```

### 9. Custom Drivers (New Feature)

Create custom drivers for non-Eloquent query backends:

```php
use Jackardios\QueryWizard\Contracts\DriverInterface;

class ScoutDriver implements DriverInterface
{
    public function name(): string
    {
        return 'scout';
    }

    public function supports(mixed $subject): bool
    {
        return $subject instanceof ScoutBuilder;
    }

    // Implement other interface methods...
}

// Register in config/query-wizard.php
'drivers' => [
    'scout' => \App\QueryWizard\Drivers\ScoutDriver::class,
],

// Or register programmatically
DriverRegistry::register(new ScoutDriver());

// Use with QueryWizard::using()
QueryWizard::using('scout', $scoutBuilder)
    ->setAllowedFilters(...)
    ->get();
```

### 10. Configuration Changes

**New config options in v3.0.0:**

```php
// config/query-wizard.php
return [
    // ... existing options ...

    // Custom drivers to register
    'drivers' => [
        // 'scout' => \App\QueryWizard\Drivers\ScoutDriver::class,
    ],
];
```

---

## Removed Features

### Classes Removed

- `Jackardios\QueryWizard\Abstracts\AbstractFilter`
- `Jackardios\QueryWizard\Abstracts\AbstractInclude`
- `Jackardios\QueryWizard\Abstracts\AbstractSort`
- `Jackardios\QueryWizard\Abstracts\AbstractQueryWizard`
- `Jackardios\QueryWizard\Eloquent\EloquentQueryWizard`
- `Jackardios\QueryWizard\Eloquent\EloquentFilter`
- `Jackardios\QueryWizard\Eloquent\EloquentInclude`
- `Jackardios\QueryWizard\Eloquent\EloquentSort`
- `Jackardios\QueryWizard\Model\ModelQueryWizard`
- `Jackardios\QueryWizard\Model\ModelInclude`
- All handler classes in `Eloquent\Filters\*`, `Eloquent\Includes\*`, `Eloquent\Sorts\*`
- All handler classes in `Model\Includes\*`

### Methods Removed/Changed

- `makeDefaultFilterHandler()` - No longer needed
- `makeDefaultIncludeHandler()` - No longer needed
- `makeDefaultSortHandler()` - No longer needed
- `handleForwardedResult()` - Appends are now applied automatically
- `getEloquentBuilder()` - Use `getSubject()` or `build()`

### Helper Functions Removed

- `instance_of_one_of()` - Was unused, removed from `src/helpers.php`

---

## Quick Reference

### Common Patterns

```php
// List all with filters
$posts = QueryWizard::for(Post::class)
    ->setAllowedFilters('title', 'status')
    ->setAllowedSorts('created_at', 'title')
    ->setAllowedIncludes('author')
    ->get();

// With schema
$posts = QueryWizard::forList(PostSchema::class)->get();

// Single item
$post = QueryWizard::forItem(PostSchema::class, $id)->getOrFail();

// Custom query
$posts = QueryWizard::for(Post::query()->where('active', true))
    ->setAllowedFilters('category')
    ->paginate(20);

// Modify query inline
$posts = QueryWizard::for(Post::class)
    ->modifyQuery(fn($q) => $q->withTrashed())
    ->get();
```

### Filter Definition Cheat Sheet

```php
FilterDefinition::exact('field')           // WHERE field = value
FilterDefinition::exact('field', 'alias')  // Use ?filter[alias]=value
FilterDefinition::partial('field')         // WHERE field LIKE %value%
FilterDefinition::scope('scopeName')       // Calls scopeScopeName()
FilterDefinition::trashed()                // with_trashed, only_trashed, etc.
FilterDefinition::callback('name', fn($q, $v, $p) => ...)
FilterDefinition::range('price')           // ?filter[price][min]=10&filter[price][max]=100
FilterDefinition::dateRange('date')        // Same but parses dates
FilterDefinition::null('field')            // Filter by null/not null
FilterDefinition::jsonContains('tags')     // JSON contains
FilterDefinition::custom('field', MyStrategy::class)

// With options
FilterDefinition::exact('name')
    ->default('default_value')
    ->prepareValueWith(fn($v) => trim($v))
    ->withRelationConstraint(false)
    ->withOptions(['custom' => 'option'])
```

---

## Need Help?

If you encounter issues during migration, please open an issue on GitHub with:
1. Your v2.x code that needs migration
2. Any error messages you receive
3. Your Laravel and PHP versions

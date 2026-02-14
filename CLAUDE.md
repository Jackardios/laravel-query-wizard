# Laravel Query Wizard

Builds Eloquent queries from API request parameters like `?filter[status]=active&include=posts&sort=-created_at&fields[user]=id,name&append=full_name`.

**Requirements:** PHP 8.1+, Laravel 10/11/12

## Code Style

No redundant inline comments. PHPDoc annotations are fine.

## Commands

```bash
composer test                          # Run all tests
composer test:unit                     # Unit tests only
composer test:feature                  # Feature tests only
vendor/bin/phpunit --filter test_name  # Single test
composer analyse                       # Static analysis (PHPStan)
composer format                        # Format code with Pint
composer format-check                  # Check formatting
```

## Architecture

```
BaseQueryWizard (abstract)    # Config API, build logic, no execution
    ↓
EloquentQueryWizard           # Filters, sorts, includes, fields, appends → get(), paginate()
ModelQueryWizard              # Includes, fields, appends only → process()
```

### Key Files

| Purpose | Location |
|---------|----------|
| Base class | `src/BaseQueryWizard.php` |
| Eloquent wizard | `src/Eloquent/EloquentQueryWizard.php` |
| Model wizard | `src/ModelQueryWizard.php` |
| Factories | `src/Eloquent/Eloquent{Filter,Sort,Include}.php` |
| Implementations | `src/Eloquent/{Filters,Sorts,Includes}/*.php` |
| Schema | `src/Schema/ResourceSchema.php` |
| Config | `config/query-wizard.php` |
| Request parsing | `src/QueryParametersManager.php` |

## Usage

```php
// EloquentQueryWizard
EloquentQueryWizard::for(User::class)
    ->allowedFilters('name', EloquentFilter::partial('email'))
    ->allowedSorts('created_at', 'name')
    ->allowedIncludes('posts', 'postsCount')
    ->allowedFields('id', 'name', 'email')
    ->allowedAppends('full_name')
    ->defaultSorts('-created_at')
    ->get();

// With schema
EloquentQueryWizard::forSchema(UserSchema::class)->get();

// ModelQueryWizard (for already-loaded models)
ModelQueryWizard::for($user)
    ->allowedIncludes('posts')
    ->allowedFields('id', 'name')
    ->allowedAppends('full_name')
    ->process();

// Useful methods
$wizard->schema(UserSchema::class);     // Set schema after instantiation
$wizard->getPassthroughFilters();       // Get Collection of passthrough filter values
$wizard->toQuery();                     // Get underlying builder after building
$wizard->getSubject();                  // Get underlying builder without building
```

## Filter Types

| Type | Factory | Request |
|------|---------|---------|
| Exact | `EloquentFilter::exact('col')` | `?filter[col]=value` |
| Partial | `EloquentFilter::partial('col')` | `?filter[col]=val` (LIKE %val%) |
| Scope | `EloquentFilter::scope('name')` | `?filter[name]=arg` |
| Trashed | `EloquentFilter::trashed()` | `?filter[trashed]=with\|only` |
| Null | `EloquentFilter::null('col')` | `?filter[col]=true` (IS NULL) |
| Range | `EloquentFilter::range('col')` | `?filter[col][min]=1&filter[col][max]=10` |
| DateRange | `EloquentFilter::dateRange('col')` | `?filter[col][from]=...&filter[col][to]=...` |
| JsonContains | `EloquentFilter::jsonContains('col')` | `?filter[col]=a,b` |
| Callback | `EloquentFilter::callback('n', fn($q, $v, $p) => ...)` | `?filter[n]=val` |
| Passthrough | `EloquentFilter::passthrough('n')` | Captured but not applied |
| Operator | `EloquentFilter::operator('col', FilterOperator::GREATER_THAN)` | `?filter[col]=100` |
| Operator (dynamic) | `EloquentFilter::operator('col', FilterOperator::DYNAMIC)` | `?filter[col]=>=100` |

**FilterOperator enum:** `EQUAL`, `NOT_EQUAL`, `GREATER_THAN`, `GREATER_THAN_OR_EQUAL`, `LESS_THAN`, `LESS_THAN_OR_EQUAL`, `LIKE`, `NOT_LIKE`, `DYNAMIC`

## Sort & Include Types

```php
// Sorts
EloquentSort::field('col')                              // ?sort=col or ?sort=-col
EloquentSort::count('posts')                            // Sort by relationship count
EloquentSort::relation('orders', 'total', 'sum')        // Sort by aggregate (min|max|sum|avg|count|exists)
EloquentSort::callback('name', fn($q, $dir, $p) => ...)

// Includes
EloquentInclude::relationship('posts')                  // ?include=posts
EloquentInclude::count('posts')                         // ?include=postsCount
EloquentInclude::exists('posts')                        // ?include=postsExists
EloquentInclude::callback('name', fn($q, $rel) => ...)
```

## Fluent Modifiers

All modifiers **mutate** the original object:

```php
EloquentFilter::exact('status')
    ->alias('state')                      // URL name: ?filter[state]=...
    ->default('active')                   // Default when not in request
    ->prepareValueWith(fn($v) => strtolower($v))
    ->when(fn($value) => $value !== 'all') // Skip if returns false
    ->asBoolean()                         // Convert 'true'/'false'/'1'/'0'/'yes'/'no' to bool

// Filter-specific
->withoutRelationConstraint()             // ExactFilter, PartialFilter, NullFilter, OperatorFilter
->withModelBinding()                      // ScopeFilter
->withInvertedLogic()                     // NullFilter
->matchAny()                              // JsonContainsFilter (default: matchAll)
->minKey('from')->maxKey('to')            // RangeFilter
->fromKey('start')->toKey('end')          // DateRangeFilter
->dateFormat('Y-m-d')                     // DateRangeFilter
```

## Schema

```php
abstract class ResourceSchema {
    abstract public function model(): string;
    public function type(): string;                                    // For ?fields[type]=...
    public function filters(QueryWizardInterface $wizard): array;
    public function sorts(QueryWizardInterface $wizard): array;
    public function includes(QueryWizardInterface $wizard): array;
    public function fields(QueryWizardInterface $wizard): array;
    public function appends(QueryWizardInterface $wizard): array;
    public function defaultSorts(QueryWizardInterface $wizard): array;
    public function defaultFields(QueryWizardInterface $wizard): array;
    public function defaultIncludes(QueryWizardInterface $wizard): array;
    public function defaultAppends(QueryWizardInterface $wizard): array;
    public function defaultFilters(QueryWizardInterface $wizard): array;  // ['status' => 'active']
}
```

## Config Highlights

```php
// config/query-wizard.php
'request_data_source' => 'query_string',  // or 'body' for request body
'apply_filter_default_on_null' => false,  // true = use default() when filter value is null/empty
'naming' => [
    'convert_parameters_to_snake_case' => false,  // ?filter[firstName] → filter[first_name]
],
'separators' => [
    'filters' => ';',  // Per-type separator (default: ',')
],
'optimizations' => [
    'relation_select_mode' => 'safe',  // Auto-injects FK columns for eager loading
],
'limits' => [
    'max_includes_count' => 10,
    'max_include_depth' => 3,
    'max_filters_count' => 20,
    'max_appends_count' => 10,
    'max_append_depth' => 3,
    'max_sorts_count' => 5,
],
```

## Development

### Adding a New Filter

1. Create class in `src/Eloquent/Filters/` extending `AbstractFilter`
2. Implement `getType(): string` and `apply($query, $value)`
3. Add factory method to `src/Eloquent/EloquentFilter.php`
4. Add tests in `tests/Feature/Eloquent/`

## Common Gotchas

### 1. `allowedFilters([])` vs no call
```php
->allowedFilters([])  // FORBIDS all filters (throws InvalidFilterQuery)
// vs no call = uses schema filters
```

### 2. Count/Exists includes require explicit allowance
```php
->allowedIncludes('posts')                   // Only ?include=posts
->allowedIncludes('posts', 'postsCount')     // Also allows postsCount
```

### 3. Include depth validated by relation name, not alias
```php
// max_include_depth = 2
EloquentInclude::relationship('a.b.c.d')->alias('simple')  // Still fails (depth 4)
```

### 4. ScopeFilter model binding disabled by default
```php
EloquentFilter::scope('byAuthor')                    // Safe: values passed as-is
EloquentFilter::scope('byAuthor')->withModelBinding() // Loads models WITHOUT auth check
```

### 5. Manual post-processing for unwrapped methods
```php
// find() not wrapped by wizard — use applyPostProcessingTo()
$wizard = EloquentQueryWizard::for(User::class)
    ->allowedFields('id', 'name')
    ->allowedAppends('full_name');

$user = $wizard->toQuery()->find($id);
$wizard->applyPostProcessingTo($user);
```

### 6. Wildcard support in disallowed*() methods
```php
->disallowedFields('*')           // Block everything
->disallowedFields('posts.*')     // Block direct children only
->disallowedFields('posts')       // Block relation + all descendants
```

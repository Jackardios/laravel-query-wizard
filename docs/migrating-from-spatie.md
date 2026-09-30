# Migrating from spatie/laravel-query-builder

The request format is close to spatie/laravel-query-builder's (`filter[]`, `sort`, `include`, `fields[]`), and most
definitions have a direct counterpart. The differences below change results without an error, or turn a request that
worked into a 400, so check each one when porting an endpoint.

## Differences That Change Results Silently

### A string filter is exact, not partial

```php
// spatie: ->allowedFilters('name')  →  WHERE name LIKE '%value%'
->allowedFilters('name')                        // WHERE name = 'value'
->allowedFilters(EloquentFilter::partial('name')) // WHERE name LIKE '%value%'
```

### The second factory argument is the public name, not the column

spatie's `AllowedFilter::exact('name', 'user_passport_full_name')` answers `?filter[name]` and filters the
`user_passport_full_name` column. Here the column comes first and the public name second:

```php
EloquentFilter::exact('user_passport_full_name', 'name')
EloquentFilter::exact('user_passport_full_name')->alias('name')
EloquentSort::field('sort_order', 'order')
EloquentInclude::relationship('userProfile', 'profile')
```

A definition ported as is publishes the column name and filters the column you meant to hide.

### Boolean values are not converted

spatie maps `true` and `false` to booleans for every filter. Here `?filter[is_visible]=true` compares with the string
`'true'`. Call `asBoolean()`, which also accepts `1/0`, `yes/no` and `on/off` and rejects anything else with a 400:

```php
EloquentFilter::exact('is_visible')->asBoolean()
```

### Sort callbacks receive the direction, not `$descending`

spatie calls a sort with `bool $descending`. A callback sort here receives `'asc'` or `'desc'`, and since `'asc'` is
truthy, a ported `$descending ? 'desc' : 'asc'` always sorts descending:

```php
EloquentSort::callback('popular', fn ($query, string $direction) => $query->orderBy('views', $direction))
```

A custom sort class implements `SortInterface::apply(mixed $subject, SortDirection $direction)` (or extends
`AbstractSort`) instead of spatie's `Sort::__invoke()`.

## Differences That Turn Requests into 400s

### Count and exists includes are allowed one by one

spatie's `allowedIncludes('posts')` also allows `postsCount` and `postsExists`. Here each one is listed, and
`EloquentInclude::count()` takes the relation, not the include name:

```php
// spatie: AllowedInclude::count('friendsCount')
->allowedIncludes('posts', 'postsCount', EloquentInclude::count('friends'))
```

`EloquentInclude::count('friendsCount')` would answer `?include=friendsCountCount`.

### A nested include does not allow its parent

`allowedIncludes('posts.comments')` allows `?include=posts.comments` only. List `posts` as well to allow
`?include=posts`.

### Every parameter is validated

A `sort`, `include`, `fields` or `append` parameter that names nothing allowed is a 400, also on an endpoint that
never configures that parameter: `?sort=name` without `allowedSorts()` is rejected. Clients that send stray parameters
start failing; set `ignore_unknown` in the configuration to drop unknown names instead.

### Fieldsets are keyed by resource and relation name

spatie keys fieldsets by table name (`?fields[users]=id,name`). Here the root fieldset is keyed by the camelCase model
name (`?fields[user]=id,name`, or the schema's `type()`), and a relation's by its include name
(`?fields[posts]=id,title`). `?fields=id,name` addresses the root.

## Definitions

| spatie | Query Wizard |
|--------|--------------|
| `QueryBuilder::for(User::class)` | `EloquentQueryWizard::for(User::class)` |
| `QueryBuilder::for(User::class, $request)` | `EloquentQueryWizard::for(User::class)`; the wizard reads the current request |
| `AllowedFilter::partial('name')` | `EloquentFilter::partial('name')` |
| `AllowedFilter::exact('name', 'column')` | `EloquentFilter::exact('column', 'name')` |
| `AllowedFilter::scope('active')` | `EloquentFilter::scope('active')` |
| `AllowedFilter::callback('search', fn ($query, $value) => ...)` | `EloquentFilter::callback('search', fn ($query, $value) => ...)` |
| `AllowedFilter::trashed()` | `EloquentFilter::trashed()` |
| `->ignore('all')` | `->when(fn ($value) => $value !== 'all')` |
| `->nullable()` | no counterpart: a blank value applies no condition; add `EloquentFilter::null('column')` for IS NULL |
| `AllowedFilter::beginsWith()`, `endsWith()`, `belongsTo()` | a callback filter |
| `AllowedSort::field('order', 'sort_order')` | `EloquentSort::field('sort_order', 'order')` |
| `AllowedSort::custom('name', new MySort)` | a class extending `AbstractSort`, or `EloquentSort::callback()` |
| `->defaultSort('-created_at')` | `->defaultSorts('-created_at')` |
| `AllowedInclude::relationship('profile', 'userProfile')` | `EloquentInclude::relationship('userProfile', 'profile')` |
| `AllowedInclude::count('friendsCount')` | `EloquentInclude::count('friends')` |
| `AllowedInclude::exists('postsExists')` | `EloquentInclude::exists('posts')` |
| `AllowedInclude::sum('postsViewsSum', 'posts', 'views')` | a callback include with `->withRuntimeAttributes('posts_sum_views')` |
| `AllowedInclude::callback('latestPost', fn ($query) => ...)` | `EloquentInclude::callback('latestPost', fn ($query) => ...)` |

## Configuration

`config/query-wizard.php` replaces `config/query-builder.php`:

| spatie | Query Wizard |
|--------|--------------|
| `parameters.include`, `filter`, `sort`, `fields` | `parameters.includes`, `filters`, `sorts`, `fields` (and `appends`) |
| `delimiter` | `separators.default`, or a separator per parameter under `separators` |
| `suffixes.count`, `suffixes.exists` | `includes.count_suffix`, `includes.exists_suffix` |
| `disable_invalid_filter_query_exception` (and the sort and include ones) | `ignore_unknown.filters`, `sorts`, `includes`, `fields`, `appends` |

## Exceptions

The exceptions keep spatie's names (`InvalidFilterQuery`, `InvalidSortQuery`, `InvalidIncludeQuery`,
`InvalidFieldQuery`) under `Jackardios\QueryWizard\Exceptions`. All of them extend `InvalidQuery`, a 400 with an
`errorCode` and the request `parameter`; see [Error Handling](../README.md#error-handling).

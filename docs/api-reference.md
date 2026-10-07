# API Reference

## EloquentQueryWizard Methods

### Factory Methods

| Method | Description |
|--------|-------------|
| `for($subject)` | Create from model class, query builder, or relation; a second argument throws |
| `forSchema($schema)` | Create from a ResourceSchema class |
| `new EloquentQueryWizard($subject, $parameters, $config, $schema)` | Create from a query builder or relation with a `QueryParametersManager` of your own |

### Configuration Methods

| Method | Description |
|--------|-------------|
| `schema($schema)` | Set ResourceSchema for configuration |
| `allowedFilters(...$filters)` | Set allowed filters, replacing the schema's list and earlier calls |
| `addAllowedFilters(...$filters)` | Add to the allowed filters (to the schema's when `allowedFilters()` was not called) |
| `disallowedFilters(...$names)` | Remove filters; repeated calls add up (supports wildcards: `*`, `relation.*`, `relation`) |
| `allowedSorts(...$sorts)` | Set allowed sorts, replacing the schema's list and earlier calls |
| `addAllowedSorts(...$sorts)` | Add to the allowed sorts (to the schema's when `allowedSorts()` was not called) |
| `disallowedSorts(...$names)` | Remove sorts; repeated calls add up (supports wildcards: `*`, `relation.*`, `relation`) |
| `defaultSorts(...$sorts)` | Set default sorts (applied only when `sort` is absent) |
| `allowedIncludes(...$includes)` | Set allowed includes, replacing the schema's list and earlier calls |
| `addAllowedIncludes(...$includes)` | Add to the allowed includes (to the schema's when `allowedIncludes()` was not called) |
| `disallowedIncludes(...$names)` | Remove includes; repeated calls add up (supports wildcards: `*`, `relation.*`, `relation`) |
| `defaultIncludes(...$names)` | Set default includes (applied only when `include` is absent; `?include=` disables defaults) |
| `allowedFields(...$fields)` | Set allowed fields, replacing the schema's list and earlier calls (supports wildcards: `*`, `relation.*`) |
| `addAllowedFields(...$fields)` | Add to the allowed fields (to the schema's when `allowedFields()` was not called) |
| `disallowedFields(...$names)` | Remove fields; repeated calls add up (supports wildcards: `*`, `relation.*`, `relation`) |
| `defaultFields(...$fields)` | Set default fields (applied only when `fields` is absent; `?fields=` is an explicit empty root fieldset) |
| `allowedAppends(...$appends)` | Set allowed appends, replacing the schema's list and earlier calls (supports wildcards: `*`, `relation.*`) |
| `addAllowedAppends(...$appends)` | Add to the allowed appends (to the schema's when `allowedAppends()` was not called) |
| `disallowedAppends(...$names)` | Remove appends; repeated calls add up (supports wildcards: `*`, `relation.*`, `relation`) |
| `defaultAppends(...$appends)` | Set default appends (applied only when `append` is absent; `?append=` disables defaults) |
| `tap(callable $callback)` | Add a callback that modifies the query when the wizard builds, before filters, sorts and includes (not immediately, unlike `Builder::tap()`); its return value is ignored |

### Execution Methods

| Method | Description |
|--------|-------------|
| `get()` | Execute and return Collection |
| `first()` | Execute and return first result |
| `firstOrFail()` | Execute and return first result or throw exception |
| `paginate($perPage)` | Execute with pagination |
| `simplePaginate($perPage)` | Execute with simple pagination |
| `cursorPaginate($perPage)` | Execute with cursor pagination |
| `chunk($count, $callback)` | Process results in chunks with post-processing |
| `chunkById($count, $callback)` | Process results in chunks by ID with post-processing |
| `chunkByIdDesc($count, $callback)` | Same, in descending key order |
| `eachById($callback, $count)` | Process each model, chunked by ID, with post-processing |
| `each($callback, $count)` | Process each model with post-processing |
| `chunkMap($callback, $count)` | Map each post-processed model, returning a Collection |
| `lazy($chunkSize)` | Return LazyCollection with post-processing |
| `lazyById($chunkSize)` / `lazyByIdDesc($chunkSize)` | Return LazyCollection chunked by ID with post-processing |
| `cursor()` | Return cursor LazyCollection with post-processing; includes are eager loaded per 1000 models |
| `build()` | Build and return the query builder (`Builder\|Relation`); finalizes configuration |
| `toQuery()` | Build and return query builder; finalizes configuration |
| `getSubject()` | Get underlying query builder without building; finalizes configuration |
| `applyPostProcessingTo($results)` | Apply full post-processing (fields + appends) to results |
| `getPassthroughFilters()` | Get passthrough filter values using the same validation/default/prepare pipeline as normal filter execution |

### Reading Methods

| Method | Description |
|--------|-------------|
| `getAllowedFilters()` | Allowed filters by public name (`array<array-key, FilterInterface>`, copies), without the disallowed ones |
| `getAllowedSorts()` | Allowed sorts by public name (`array<array-key, SortInterface>`, copies) |
| `getAllowedIncludes()` | Allowed includes by public name (`array<array-key, IncludeInterface>`, copies) |
| `getAllowedFields()` | Allowed field names (`list<string>`), relation fields as dot paths |
| `getAllowedAppends()` | Allowed append names (`list<string>`), relation appends as dot paths |
| `getRequestedFilterNames()` | Filter names the request carries, as the build resolves them (not-allowed names included) |
| `getResourceKey()` | Key of the root fieldset (`?fields[key]=`) |
| `getSchema()` | The schema, or `null` |
| `getConfig()` | `QueryWizardConfig` as of the current build |
| `getParametersManager()` | The `QueryParametersManager` the wizard reads |

These read the configuration without building and leave the wizard configurable. A name that is a number is an integer
key. A schema method may read the lists it does not describe; reading its own list, directly or through another schema
method, throws `LogicException`.

Finders called through the wizard (`find()`, `findMany()`, `findOrFail()`, `findOr()`, `findSole()`, `sole()`,
`firstWhere()`, `firstOr()`) build the query and post-process their results; the result of a `findOr()`/`firstOr()`
fallback callback is returned untouched. Other builder methods are proxied after the build: a method that returns the
builder itself returns the wizard, anything else is returned as is.

## ModelQueryWizard Methods

### Factory Methods

| Method | Description |
|--------|-------------|
| `for($model)` | Create from a Model instance; a second argument throws |

### Configuration Methods

| Method | Description |
|--------|-------------|
| `schema($schema)` | Set ResourceSchema for configuration |
| `allowedIncludes(...$includes)` | Set allowed includes, replacing the schema's list and earlier calls |
| `addAllowedIncludes(...$includes)` | Add to the allowed includes (to the schema's when `allowedIncludes()` was not called) |
| `disallowedIncludes(...$names)` | Remove includes; repeated calls add up |
| `defaultIncludes(...$names)` | Set default includes (effective only when `include` is absent; applied without being allowed) |
| `allowedFields(...$fields)` | Set allowed fields, replacing the schema's list and earlier calls |
| `addAllowedFields(...$fields)` | Add to the allowed fields (to the schema's when `allowedFields()` was not called) |
| `disallowedFields(...$names)` | Remove fields; repeated calls add up |
| `defaultFields(...$fields)` | Set default fields (effective only when `fields` is absent) |
| `allowedAppends(...$appends)` | Set allowed appends, replacing the schema's list and earlier calls |
| `addAllowedAppends(...$appends)` | Add to the allowed appends (to the schema's when `allowedAppends()` was not called) |
| `disallowedAppends(...$names)` | Remove appends; repeated calls add up |
| `defaultAppends(...$appends)` | Set default appends (effective only when `append` is absent; applied without being allowed) |

All configuration methods must be called before `process()`. After processing, create a new `ModelQueryWizard` instance for any different configuration or request parameters.

### Execution Methods

| Method | Description |
|--------|-------------|
| `process()` | Apply includes, fields, appends and return the model; repeated calls are only safe when configuration and parameters are unchanged |
| `getModel()` | Get the underlying model instance |
| `getAllowedIncludes()`, `getAllowedFields()`, `getAllowedAppends()` | The allowed lists, as on `EloquentQueryWizard` |
| `getResourceKey()`, `getSchema()`, `getConfig()`, `getParametersManager()` | As on `EloquentQueryWizard` |

## Parameter Semantics

- Defaults apply only when the corresponding top-level parameter is absent.
- `?include=` means "include nothing" and does not merge `defaultIncludes()`.
- `?append=` means "append nothing" and does not merge `defaultAppends()`.
- `?fields=` means an explicit empty root fieldset.
- `?fields[relation]=` means an explicit empty fieldset for that relation.
- `?sort=` (also `?sort=-`, `?sort=,`) is invalid and throws `InvalidSortQuery`, also with `ignore_unknown.sorts`.
- `default*()` called with no arguments means "no defaults" (the schema's defaults are not used).
- Active `count` / `exists` includes remain visible even when the root fieldset is empty.

## Filter Factory Methods (EloquentFilter)

| Method | Description |
|--------|-------------|
| `exact($property, $alias)` | Exact match filter |
| `partial($property, $alias)` | LIKE search filter |
| `scope($scope, $alias)` | Model scope filter |
| `trashed($alias)` | Soft delete filter |
| `null($property, $alias)` | NULL check filter (`true` → IS NULL) |
| `notNull($property, $alias)` | NOT NULL check filter (`true` → IS NOT NULL) |
| `range($property, $alias)` | Numeric range filter |
| `dateRange($property, $alias)` | Date range filter |
| `jsonContains($property, $alias)` | JSON contains filter |
| `operator($property, $operator, $alias)` | Operator filter (=, !=, >, >=, <, <=, LIKE, NOT LIKE, `Dynamic`) |
| `callback($name, $callback, $alias)` | Custom callback filter |
| `passthrough($name, $alias)` | Passthrough filter |

## Filter Modifiers

### Common Modifiers (all filters)

| Method | Description |
|--------|-------------|
| `alias($name)` | URL parameter name |
| `default($value)` | Default value when absent |
| `prepareValueWith($callback)` | Add a step that transforms the value before applying; steps run in call order, a `null` result skips the filter |
| `when($callback)` | Conditionally skip filter |
| `withStructuredInput()` / `withoutStructuredInput()` | Skip raw shape validation and validate only the prepared value shape, or validate both (default) |
| `withValueSplitting()` / `withoutValueSplitting()` | Split string values by the filters separator, or keep them whole (default: split; `partial` keeps them whole) |
| `asBoolean()` | Add a step reading `true`/`false`/`1`/`0`/`yes`/`no`/`on`/`off` (any case) as booleans, item by item for lists; anything else throws `InvalidFilterValue`. Throws `LogicException` on filters that can't take booleans |

### Built-in Filter Value Shapes

- `exact`, `partial`, `operator`: scalar or flat list of scalars
- `scope`: single value or flat list without nested arrays
- `jsonContains`: scalar or flat list of scalars
- `null`, `trashed`: scalar only
- `range`, `dateRange`: array with only the boundary keys or a flat list of exactly two values

Malformed built-in filter payloads raise `InvalidFilterQuery::invalidFormat(...)`. Values a filter cannot read (a
non-boolean for `asBoolean()`/`null`, a non-number for `range`, a non-ISO date for `dateRange`, ...) raise
`InvalidFilterValue`. Blank values (whitespace, `,`, lists of blanks) are treated as absent.
`ignore_unknown.filters` suppresses neither; it only affects unknown filter names.

Use `withStructuredInput()` when a built-in filter should intentionally accept structured raw input that will be normalized inside `prepareValueWith()`. The prepared value is still validated against the built-in filter's contract before `apply()` runs.

### Filter-Specific Modifiers

| Filter | Method | Description |
|--------|--------|-------------|
| Exact, Partial, Null, Operator, Range, DateRange | `withoutRelationConstraint()` | Disable `whereHas` for dot notation |
| Scope | `withModelBinding()` | Load model by ID |
| JsonContains | `matchAny()` | Match any value (default: `matchAll()`) |
| Range | `minKey($key)`, `maxKey($key)` | Custom range keys |
| DateRange | `fromKey($key)`, `toKey($key)` | Custom date keys |
| DateRange | `dateFormat($format)` | Format every bound for the column (`'U'` = Unix timestamp) |
| DateRange | `asUnixTimestamp()` | Integer column of Unix timestamps; also accepts timestamps in the request |
| DateRange | `lenient()` | Also accept any date PHP can parse (`yesterday`, `-1 week`) |
| Operator (`Like`, `NotLike`) | `withValueSplitting()` | Split the value into phrases (default: one phrase) |

## Sort Factory Methods (EloquentSort)

| Method | Description |
|--------|-------------|
| `field($property, $alias)` | Column sort |
| `count($relation, $alias)` | Relationship count sort (a single relation; nested ones throw `InvalidArgumentException`) |
| `max($relation, $column, $alias)`, `min()`, `sum()`, `avg()` | Relationship aggregate sort, like `withMax()` (a single relation); the name defaults to the relation |
| `callback($name, $callback, $alias)` | Custom callback sort |

## Include Factory Methods (EloquentInclude)

| Method | Description |
|--------|-------------|
| `relationship($relation, $alias)` | Eager load relationship |
| `count($relation, $alias)` | Load relationship count |
| `exists($relation, $alias)` | Check relationship existence (adds boolean attribute) |
| `callback($name, $callback, $alias)` | Custom callback include; `->withRuntimeAttributes('attr', ...)` keeps the attributes it adds visible under sparse fieldsets |

Count / exists include aliases are request-facing only. The serialized attribute key remains Laravel's default runtime key (for example `posts_count` or `posts_exists`), and those runtime attributes stay visible even when root sparse fieldsets are applied.

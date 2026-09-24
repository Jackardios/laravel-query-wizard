# API Reference

## EloquentQueryWizard Methods

### Factory Methods

| Method | Description |
|--------|-------------|
| `for($subject)` | Create from model class, query builder, or relation |
| `forSchema($schema)` | Create from a ResourceSchema class |

### Configuration Methods

| Method | Description |
|--------|-------------|
| `schema($schema)` | Set ResourceSchema for configuration |
| `allowedFilters(...$filters)` | Set allowed filters |
| `disallowedFilters(...$names)` | Remove filters (supports wildcards: `*`, `relation.*`, `relation`) |
| `allowedSorts(...$sorts)` | Set allowed sorts |
| `disallowedSorts(...$names)` | Remove sorts (supports wildcards: `*`, `relation.*`, `relation`) |
| `defaultSorts(...$sorts)` | Set default sorts (applied only when `sort` is absent) |
| `allowedIncludes(...$includes)` | Set allowed includes |
| `disallowedIncludes(...$names)` | Remove includes (supports wildcards: `*`, `relation.*`, `relation`) |
| `defaultIncludes(...$names)` | Set default includes (applied only when `include` is absent; `?include=` disables defaults) |
| `allowedFields(...$fields)` | Set allowed fields (supports wildcards: `*`, `relation.*`) |
| `disallowedFields(...$names)` | Remove fields (supports wildcards: `*`, `relation.*`, `relation`) |
| `defaultFields(...$fields)` | Set default fields (applied only when `fields` is absent; `?fields=` is an explicit empty root fieldset) |
| `allowedAppends(...$appends)` | Set allowed appends (supports wildcards: `*`, `relation.*`) |
| `disallowedAppends(...$names)` | Remove appends (supports wildcards: `*`, `relation.*`, `relation`) |
| `defaultAppends(...$appends)` | Set default appends (applied only when `append` is absent; `?append=` disables defaults) |
| `tap(callable $callback)` | Add query modification callback |

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

Finders called through the wizard (`find()`, `findMany()`, `findOrFail()`, `findOr()`, `findSole()`, `sole()`,
`firstWhere()`, `firstOr()`) build the query and post-process their results; the result of a `findOr()`/`firstOr()`
fallback callback is returned untouched. Other builder methods are proxied after the build: a method that returns the
builder itself returns the wizard, anything else is returned as is.

## ModelQueryWizard Methods

### Factory Methods

| Method | Description |
|--------|-------------|
| `for($model)` | Create from a Model instance |

### Configuration Methods

| Method | Description |
|--------|-------------|
| `schema($schema)` | Set ResourceSchema for configuration |
| `allowedIncludes(...$includes)` | Set allowed includes |
| `disallowedIncludes(...$names)` | Remove includes |
| `defaultIncludes(...$names)` | Set default includes (effective only when `include` is absent and the names are also allowed by allowlist/schema) |
| `allowedFields(...$fields)` | Set allowed fields |
| `disallowedFields(...$names)` | Remove fields |
| `defaultFields(...$fields)` | Set default fields (effective only when `fields` is absent) |
| `allowedAppends(...$appends)` | Set allowed appends |
| `disallowedAppends(...$names)` | Remove appends |
| `defaultAppends(...$appends)` | Set default appends (effective only when `append` is absent and the names are also allowed by allowlist/schema) |

All configuration methods must be called before `process()`. After processing, create a new `ModelQueryWizard` instance for any different configuration or request parameters.

### Execution Methods

| Method | Description |
|--------|-------------|
| `process()` | Apply includes, fields, appends and return the model; repeated calls are only safe when configuration and parameters are unchanged |
| `getModel()` | Get the underlying model instance |

## Parameter Semantics

- Defaults apply only when the corresponding top-level parameter is absent.
- `?include=` means "include nothing" and does not merge `defaultIncludes()`.
- `?append=` means "append nothing" and does not merge `defaultAppends()`.
- `?fields=` means an explicit empty root fieldset.
- `?fields[relation]=` means an explicit empty fieldset for that relation.
- `?sort=` (also `?sort=-`, `?sort=,`) is invalid and throws `InvalidSortQuery`; with `disable_invalid_sort_query_exception` it counts as absent and default sorts apply.
- `default*()` called with no arguments means "no defaults" (the schema's defaults are not used).
- Active `count` / `exists` includes remain visible even when the root fieldset is empty.

## Filter Factory Methods (EloquentFilter)

| Method | Description |
|--------|-------------|
| `exact($property, $alias)` | Exact match filter |
| `partial($property, $alias)` | LIKE search filter |
| `scope($scope, $alias)` | Model scope filter |
| `trashed($alias)` | Soft delete filter |
| `null($property, $alias)` | NULL check filter |
| `range($property, $alias)` | Numeric range filter |
| `dateRange($property, $alias)` | Date range filter |
| `jsonContains($property, $alias)` | JSON contains filter |
| `operator($property, $operator, $alias)` | Operator filter (=, !=, >, >=, <, <=, LIKE, NOT LIKE, DYNAMIC) |
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
| `allowStructuredInput()` | Skip raw shape validation and validate only the prepared value shape |
| `withValueSplitting()` / `withoutValueSplitting()` | Split string values by the filters separator, or keep them whole (default: split; `partial` keeps them whole) |
| `asBoolean()` | Add a step reading `true`/`false`/`1`/`0`/`yes`/`no`/`on`/`off` (any case) as booleans, item by item for lists; anything else throws `InvalidFilterValue` |

### Built-in Filter Value Shapes

- `exact`, `partial`, `operator`: scalar or flat list of scalars
- `scope`: single value or flat list without nested arrays
- `null`, `trashed`: scalar only
- `range`, `dateRange`: array with boundary keys or a flat list of exactly two values

Malformed built-in filter payloads raise `InvalidFilterQuery::invalidFormat(...)`. Values a filter cannot read (a
non-boolean for `asBoolean()`/`null`, a non-number for `range`, a non-ISO date for `dateRange`, ...) raise
`InvalidFilterValue`. Blank values (whitespace, `,`, lists of blanks) are treated as absent.
`disable_invalid_filter_query_exception` suppresses neither; it only affects unknown filter names.

Use `allowStructuredInput()` when a built-in filter should intentionally accept structured raw input that will be normalized inside `prepareValueWith()`. The prepared value is still validated against the built-in filter's contract before `apply()` runs.

### Filter-Specific Modifiers

| Filter | Method | Description |
|--------|--------|-------------|
| Exact, Partial, Null, Operator, Range, DateRange | `withoutRelationConstraint()` | Disable `whereHas` for dot notation |
| Scope | `withModelBinding()` | Load model by ID |
| Null | `withInvertedLogic()` | Use IS NOT NULL |
| JsonContains | `matchAny()` | Match any value (default: `matchAll()`) |
| Range | `minKey($key)`, `maxKey($key)` | Custom range keys |
| DateRange | `fromKey($key)`, `toKey($key)` | Custom date keys |
| DateRange | `dateFormat($format)` | Format every bound for the column (`'U'` = Unix timestamp) |
| DateRange | `asUnixTimestamp()` | Integer column of Unix timestamps; also accepts timestamps in the request |
| DateRange | `lenient()` | Also accept any date PHP can parse (`yesterday`, `-1 week`) |
| Operator (LIKE, NOT_LIKE) | `withValueSplitting()` | Split the value into phrases (default: one phrase) |

## Sort Factory Methods (EloquentSort)

| Method | Description |
|--------|-------------|
| `field($property, $alias)` | Column sort |
| `count($relation, $alias)` | Relationship count sort (a single relation; nested ones throw `InvalidArgumentException`) |
| `relation($relation, $column, $aggregate, $alias)` | Relationship aggregate sort (min, max, sum, avg, count, exists; a single relation) |
| `callback($name, $callback, $alias)` | Custom callback sort |

## Include Factory Methods (EloquentInclude)

| Method | Description |
|--------|-------------|
| `relationship($relation, $alias)` | Eager load relationship |
| `count($relation, $alias)` | Load relationship count |
| `exists($relation, $alias)` | Check relationship existence (adds boolean attribute) |
| `callback($name, $callback, $alias)` | Custom callback include; `->withRuntimeAttributes('attr', ...)` keeps the attributes it adds visible under sparse fieldsets |

Count / exists include aliases are request-facing only. The serialized attribute key remains Laravel's default runtime key (for example `posts_count` or `posts_exists`), and those runtime attributes stay visible even when root sparse fieldsets are applied.

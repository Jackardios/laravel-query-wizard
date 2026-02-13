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
| `defaultSorts(...$sorts)` | Set default sorts |
| `allowedIncludes(...$includes)` | Set allowed includes |
| `disallowedIncludes(...$names)` | Remove includes (supports wildcards: `*`, `relation.*`, `relation`) |
| `defaultIncludes(...$names)` | Set default includes (applied only when include param is absent) |
| `allowedFields(...$fields)` | Set allowed fields (supports wildcards: `*`, `relation.*`) |
| `disallowedFields(...$names)` | Remove fields (supports wildcards: `*`, `relation.*`, `relation`) |
| `defaultFields(...$fields)` | Set default fields (applied when ?fields is absent) |
| `allowedAppends(...$appends)` | Set allowed appends (supports wildcards: `*`, `relation.*`) |
| `disallowedAppends(...$names)` | Remove appends (supports wildcards: `*`, `relation.*`, `relation`) |
| `defaultAppends(...$appends)` | Set default appends |
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
| `lazy($chunkSize)` | Return LazyCollection with post-processing |
| `cursor()` | Return cursor LazyCollection with post-processing |
| `toQuery()` | Build and return query builder |
| `getSubject()` | Get underlying query builder |
| `applyPostProcessingTo($results)` | Apply full post-processing (fields + appends) to results |
| `getPassthroughFilters()` | Get passthrough filter values |

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
| `defaultIncludes(...$names)` | Set default includes |
| `allowedFields(...$fields)` | Set allowed fields |
| `disallowedFields(...$names)` | Remove fields |
| `defaultFields(...$fields)` | Set default fields |
| `allowedAppends(...$appends)` | Set allowed appends |
| `disallowedAppends(...$names)` | Remove appends |
| `defaultAppends(...$appends)` | Set default appends |

### Execution Methods

| Method | Description |
|--------|-------------|
| `process()` | Apply includes, fields, appends and return the model |
| `getModel()` | Get the underlying model instance |

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

## Sort Factory Methods (EloquentSort)

| Method | Description |
|--------|-------------|
| `field($property, $alias)` | Column sort |
| `count($relation, $alias)` | Relationship count sort |
| `relation($relation, $column, $aggregate, $alias)` | Relationship aggregate sort |
| `callback($name, $callback, $alias)` | Custom callback sort |

## Include Factory Methods (EloquentInclude)

| Method | Description |
|--------|-------------|
| `relationship($relation, $alias)` | Eager load relationship |
| `count($relation, $alias)` | Load relationship count |
| `exists($relation, $alias)` | Check relationship existence (adds boolean attribute) |
| `callback($name, $callback, $alias)` | Custom callback include |

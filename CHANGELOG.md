# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [3.0.0] - Unreleased

Version 3 is a rewrite: fluent `allowed*()` configuration, `EloquentFilter`/`EloquentSort`/`EloquentInclude` factories,
resource schemas, `ModelQueryWizard::process()`, request limits. See [UPGRADE.md](UPGRADE.md) for migrating from v2.x
and from `dev-master` snapshots. The entries below cover the changes made before the release; pre-releases v3.0.0-rc.1
and v3.0.0-rc.2 were tagged on 2026-09-25, and the changes since each of them are listed first.

### Since v3.0.0-rc.2

Added:

- `BaseQueryWizard` has an `@api` protected constructor that initializes the subject, parameters, configuration and
  schema, plus `@api` `$subject`, `isBuilt()` and `resourceModel()`. The default `resolveAppendAccessorModel()` returns
  `resourceModel()` or the related model at the path, so a wizard that knows its model gets the wildcard-append checks
  by overriding one method.
- `@api`: `InvalidFilterValue::make()`, which now returns an instance of the class it is called on, and
  `FilterValueParser::trashedMode()` and `comparable()` (no longer `@internal`).
- `ParsedDate::upToBound()` and `afterBound()` return the comparison for "on or before" and "after" a value, with a
  date naming its whole day, and `FilterValueParser::defaultTimezone()` the timezone built-in filters read dates in.
  The date range and operator filters use them.
- `limits.max_filter_values_count` (default 1000, `null` disables it): a filter that receives more values, counting
  list items through nested lists, throws `MaxFilterValuesCountExceeded` (400,
  `max_filter_values_count_exceeded`). A value kept whole by `withoutValueSplitting()` counts as one; defaults are not
  counted.
- `limits.max_fields_count` (default 100, `null` disables it): a request naming more fields, counted across every
  fieldset, throws `MaxFieldsCountExceeded` (400, `max_fields_count_exceeded`).
- `dev-master` is aliased `3.x-dev`, so dependants can require `^3.0@dev` instead of `dev-master`.
- `@api` `BaseQueryWizard::resolveEloquentShape()` and `Eloquent\EloquentShape`, for wizards that load the models with
  an Eloquent query of their own. Called from `finalizeBuild()`, the factory validates relation fieldsets and appends and
  returns an immutable shape: `applyTo($query)` eager loads the includes with their fieldsets merged into existing
  constraints and narrows the root select, keeping aggregates, expressions, eager-load keys and the given required
  columns (selected, hidden unless requested); `postProcess($results)` applies the fieldsets and appends to a model, a
  collection, a paginator or an array. `EloquentQueryWizard` runs the same steps.
- `@api`: `normalizePublicPath()` and `resolveDefaultResourceKey()`.
- `InvalidIncludeQuery::invalidFormat()` (`invalid_include_format`), and `$message`/`$errorCode` constructor parameters
  like the other `Invalid*Query` exceptions.
- `addAllowedFilters()`, `addAllowedSorts()`, `addAllowedIncludes()`, `addAllowedFields()` and `addAllowedAppends()`
  add to the list set with `allowed*()` or, when none was set, to the schema's (`QueryWizardInterface` gains the
  include, field and append ones). `getConfiguredFilters()` returns the allowed filters before `disallowedFilters()`.
- `QueryWizardInterface` declares `schema()` and `getSchema()`, which both wizards had.
- `invalidFormat()` on the `Invalid*Query` exceptions and `InvalidRequestBody::malformedJson()` take `?Throwable
  $previous`; the format errors read from the request keep the parser's `InvalidArgumentException`, and a malformed
  body its `JsonException`, as the previous exception.
- `QueryParametersManager` is `final`; `getConfig()`, `getStateVersion()`, `getUnsplitFilters()` and
  `hasSimpleParameter()` are `@internal`. `QueryWizardConfig::getSeparator()` is private (use the per-type getters).
- Exceptions: `InvalidQuery::__construct()` is `@api`; the constructors of the `Invalid*Query` exceptions,
  `InvalidFilterValue` and `InvalidRequestBody` are `@internal` in favor of their named constructors.
- `@api` include contracts: `Contracts\EagerLoadsRelation` (implemented by relationship includes) gives a custom include
  relation fieldsets, relation field and append validation and the disallowed-path check;
  `Contracts\AppliesToModel` (`applyToModel(Model $model): void`, implemented by callback includes) lets
  `ModelQueryWizard` apply it to a loaded model.
- `EloquentFilter::notNull()` (`NullFilter::notNull()`): `true` matches NOT NULL, `false` NULL.
- `withoutStructuredInput()`, the counterpart of `withStructuredInput()`.
- Error code constants: `NOT_ALLOWED` and `INVALID_FORMAT` on `InvalidFilterQuery`, `InvalidSortQuery`,
  `InvalidIncludeQuery`, `InvalidFieldQuery` and `InvalidAppendQuery`, and `ERROR_CODE` on `InvalidFilterValue`,
  `InvalidRequestBody` and the `Max*Exceeded` exceptions.

Changed:

- `FilterOperator` cases are PascalCase, like `SortDirection`'s: `Equal`, `NotEqual`, `GreaterThan`,
  `GreaterThanOrEqual`, `LessThan`, `LessThanOrEqual`, `Like`, `NotLike`, `Dynamic` (were `EQUAL`, … `DYNAMIC`). The
  values are unchanged, so `FilterOperator::from('>=')` still works. `supportsArrayValues()` and `getSqlOperator()` are
  `@internal`. The error for a list sent to a comparison operator names `LIKE` and `NOT LIKE` among the operators that
  take lists.
- Configuration keys are grouped: `includes.count_suffix`/`exists_suffix`, `filters.apply_default_on_null`,
  `separators.default` (was `array_value_separator`) and `ignore_unknown.{filters,sorts,includes,fields,appends}` (was
  `disable_invalid_*_query_exception`). A published config with an old key throws `InvalidArgumentException` naming its
  replacement. `ignore_unknown` covers names that are not allowed only: an empty `?sort=` and a field token that cannot
  name a column are 400s with it on (they used to apply the default sorts and drop the token). `QueryWizardConfig`:
  `shouldIgnoreUnknown{Filters,Sorts,Includes,Fields,Appends}()` and `getDefaultSeparator()` replace
  `isInvalid*QueryExceptionDisabled()` and `getArrayValueSeparator()`.
- Includes, sorts, fields and appends are counted against their limits while the request is read, before any name is
  validated, and a list is split only until it names one item more than the limit (a 100 000-item `?include=` is
  rejected in microseconds instead of being split and deduplicated first). Repeated names, names that differ only in
  naming style under `convert_parameters_to_snake_case` and blank items count once or not at all, as before; appends
  are now counted as requested, including names that `ignore_unknown.appends` ignores.
  `MaxIncludesCountExceeded`, `MaxSortsCountExceeded` and `MaxAppendsCountExceeded` report `$count` as one more than the
  limit, and their messages no longer name a count.
- `EloquentQueryWizard::for()` no longer accepts a model instance: `for($user)` built `$user->newQuery()`, which selects
  every row of the table rather than that user. Pass the class or a query, or use `ModelQueryWizard::for($user)`.
- `OperatorFilter::make()` takes `($property, $operator, $alias)`, the order of `EloquentFilter::operator()`, and its
  constructor is protected like the other filters'.
- `allowed*()`, `disallowed*()` and `default*()` flatten nested arrays at any depth and skip `null` items. Any other item
  that is not a name (or, for filters, sorts and includes, a definition of that kind) throws
  `InvalidArgumentException`; a nested array used to fail with a PHP `Error`, and a non-string name was dropped.
- Default sorts, includes, fields and appends apply without being allowed; a default naming an allowed definition
  (an alias, a count sort) uses it, and one that `disallowed*()` removes throws `InvalidArgumentException`. Each kind
  followed its own rule: defaults outside the allow-list were dropped silently, except sorts, whose defaults any
  `allowedSorts()` or unrelated `disallowedSorts()` call turned off.
- `QueryWizardConfig::snapshot()`, taken once per build, validates every setting, so a broken value fails every request
  instead of the ones that read it (`ignore_unknown.filters => 'maybe'` was a 500 only for requests with
  an unknown filter). An unknown key inside `parameters`, `naming`, `separators`, `fields` or `limits` throws
  `InvalidArgumentException`; a typo such as `limits.max_filter_count` used to keep the default silently.
- Nested or keyed lists in `include`, `sort`, a fieldset or `append` are a 400 (`invalid_include_format`,
  `invalid_sort_format`, `invalid_field_format`, `invalid_append_format`): `?include[a][b]=x` was ignored with a 200,
  `?sort[a][b]=x` got a message about an empty sort, and `?fields[a][b]=x` dropped the key `b`.
- An empty alias, and a sort property or alias starting with `-`, throw `InvalidArgumentException` when the definition
  is made (an empty alias gave the definition an empty name; `field('-name')` ordered by a column named `-name`).
- `asBoolean()` throws `LogicException` on partial, range, date range, JSON contains and trashed filters and on operator
  filters other than `Equal`/`NotEqual`, which turned every request into a 400. `@api` hook:
  `AbstractFilter::supportsBooleanValues()`.
- `ModelQueryWizard::process()` throws `LogicException` for a requested include that is not a relationship, count or
  exists include and does not implement `AppliesToModel`, before changing the model; such an include was accepted and
  did nothing, and one whose `getType()` returned `'relationship'` was loaded without its constraint.
- `InvalidFilterValue::__construct()` drops its leading `int $statusCode` (`new InvalidFilterValue(422, …)`
  answered 422 where every other query error is a 400). `InvalidFilterQuery::invalidFormat()` takes an optional
  details string like the other format errors and shares their message: "The `filter` parameter has an invalid
  format." instead of "Invalid `filter` parameter format.".
- The wizards dispatch on types instead of `getType()` strings: passthrough filters are `PassthroughFilter`, count and
  exists includes `CountInclude` and `ExistsInclude`, and relationship semantics come from `EagerLoadsRelation`. A
  custom filter whose `getType()` returned `'passthrough'` was never applied, and a custom include returning
  `'relationship'` got fieldsets but no way to apply them to a loaded model.
- `ExactFilter`, `OperatorFilter`, `CallbackFilter`, `CallbackSort` and `CallbackInclude` are `final`, like the other
  built-in definitions, and `PartialFilter` no longer extends `ExactFilter` (`instanceof ExactFilter` was true for
  partial filters). The supported extension points are the `@api` bases `AbstractFilter`, `AbstractSort`,
  `AbstractInclude` and `AbstractRangeFilter` and the `HandlesRelationFiltering` trait; README "Extending" shows a
  custom filter.
- `FilterInterface` declares `shouldSplitValues()`, `allowsStructuredInput()` and `validateValueShape()`, which the
  wizard called only on `AbstractFilter` subclasses: a filter implementing the interface directly got nested arrays
  unchecked and could not keep values whole.
- A schema whose `model()` is not the wizard's model (or a parent class of it) throws `InvalidArgumentException` in
  `schema()` and the `EloquentQueryWizard` and `ModelQueryWizard` constructors.
- A schema `defaultFilters()` key that names no allowed filter (a typo, a column behind an alias) throws
  `InvalidArgumentException` when the wizard builds; it used to be ignored.
- Allowed filters, sorts or includes sharing a public name throw `InvalidArgumentException`; the last one used to win
  silently (with an empty `includes.count_suffix`, `?include=posts` loaded only the count).
- Reconfiguring a wizard from a schema method (`includes($wizard)` calling `$wizard->allowedFields()`) or from a
  `tap()` callback while it builds throws `LogicException`; it changed the configuration the running build had
  already partly read.
- `disallowed*()` calls add up; a second call used to replace the first and re-allow what it removed.
- A call that neither `EloquentQueryWizard` nor its builder handles (a method, macro, named scope or dynamic `where*`)
  throws `BadMethodCallException` naming the wizard before the request is read. A typo such as `allowedFilter()` was
  forwarded to the builder after the build, so a request with filters turned it into a 400.
- `SortInterface::apply()` takes the direction as a `SortDirection` (was `'asc'`/`'desc'`); a custom sort passes
  `$direction->value` to `orderBy()`. Callback sorts still receive the string. `Values\Sort` is a `final readonly`
  class with `getDirection(): SortDirection` and `isDescending()`, and its constructor throws
  `InvalidArgumentException` for a field with two leading `-` or with a leading `-` and a direction. `?sort=--name`
  is a 400 (`invalid_sort_format`); it ordered by a column named `-name`.
- `InvalidFilterValue::$filterName` is `?string`, `null` instead of `''` when the value was read without a filter;
  `InvalidFilterValue::make()` takes `null` for no filter.
- A fieldset for a relation that has no allowed fields names it: "Requested field(s) `relatedModels.id` are not
  allowed. No fields are allowed for `relatedModels`." instead of "No fields are allowed.", which read as if no root
  fields were allowed either.

Removed:

- `getType()` from `FilterInterface`, `SortInterface`, `IncludeInterface`, the abstract bases and the built-in
  definitions.
- `Values\Sort::getSortDirection()` (use `getDirection()->value`) and `Values\Sort::parseSortDirection()`.
- `EloquentSort::relation()` and `RelationSort`: `EloquentSort::max()`, `min()`, `sum()` and `avg()` replace them,
  named like Laravel's `withMax()`/`withSum()`, and return an `AggregateSort` (`getFunction()`, `getColumn()`).
  `relation()` took the aggregate as a case-sensitive string (`'MAX'` was an error) and also accepted `count`, which
  counted the column's non-null values under another name than `EloquentSort::count()`, and `exists`.
  An aggregate sort reuses an aggregate the query already selects (`withSum('orders', 'total')`), as count sorts do.
- `NullFilter::withInvertedLogic()` and `withoutInvertedLogic()`: use `EloquentFilter::notNull()` and
  `EloquentFilter::null()`.
- `minKey()` and `maxKey()` on `DateRangeFilter`, which duplicated `fromKey()` and `toKey()`; they moved from
  `AbstractRangeFilter` to `RangeFilter`.
- `allowStructuredInput()`, renamed `withStructuredInput()` like the other `with*()`/`without*()` modifiers.
- `getDefaultAliasSuffix()`, `getSuffixConfigKey()` and `withDefaultAlias()` from `IncludeInterface` and
  `AbstractInclude`: the wizard names count and exists includes without an alias after the `includes.count_suffix`
  and `includes.exists_suffix` settings itself, without changing the definition. `QueryWizardConfig::getIncludeAliasSuffix()` is
  private; use `getCountSuffix()` and `getExistsSuffix()`.
- The up-front relation-select plan: `prepareSafeRelationSelectPlan()`, `getSafeRelationSelectColumns()`,
  `applySafeRootFieldRequirements()`, `resetSafeRelationSelectState()` and their helpers, which only an external wizard
  used; `resolveEloquentShape()` replaces them. `EloquentQueryWizard::qualifyColumns()` and
  `applyIncludeKeepingEagerLoads()` are gone too, and the relation-key helpers of `HandlesSafeRelationSelect` moved to
  `@internal` classes. The `HandlesSafeRelationSelect` and `HandlesRelationPostProcessing` traits are `@internal`.

Fixed:

- Static analysis accepts builders and relations of concrete models: `EloquentQueryWizard::for()`, its constructor and
  `EloquentShape::applyTo()` take `Builder<covariant Model>` and `Relation<covariant Model, covariant Model, *>`, so
  `EloquentQueryWizard::for(User::query())` and `for($user->posts())` no longer fail PHPStan/Larastan with
  `argument.type`. PHPDoc only; `tests/Types` keeps it checked.
- A rejected query whose message repeats bytes that are not UTF-8 (`?sort=%B1`, `?filter[%B1]=1`, a filter value) is a
  400 again: the message replaces those bytes, so the JSON response no longer fails with a 500. An invalid filter
  value is repeated in the message up to 100 characters.
- `?append=*` and append names that are not UTF-8 are rejected (400) instead of failing with a 500 when
  `allowedAppends('*')` is set.
- A decimal that overflows to infinity (`?filter[price][min]=999…9.5`) is a 400 instead of a bound that matched no
  rows, and so is a JSON body number that does (`{"filter": {"id": 1e400}}`, `InvalidRequestBody`).
- A date bound after a day on which midnight was skipped by a daylight-saving change starts at midnight of the next
  day, not at 01:00.
- `applyPostProcessingTo()` and `EloquentShape::postProcess()` return a new lazy collection for a lazy collection,
  post-processing each model as it is read; they used to run its query, post-process models that were then dropped,
  and return a collection that ran the query again unprocessed. A generator, which they used up, throws
  `InvalidArgumentException`.

Documentation:

- README "Backward Compatibility" states what 3.x keeps stable: public members of classes not marked `@internal`,
  protected members marked `@api`, and the `@api` definition interfaces. The `Concerns` traits are `@internal`, and the
  constructors of `AbstractFilter`, `AbstractSort` and `AbstractInclude` and the value shape helpers are `@api`.
- README "Schema Overrides" warns that `disallowedFilters()` also drops the schema's default for that filter and shows
  how to keep a condition that must always hold.

### Since v3.0.0-rc.1

Changed:

- Static `GreaterThan`, `GreaterThanOrEqual`, `LessThan` and `LessThanOrEqual` operators read their value like
  the operand of a `Dynamic` comparison: a decimal number or an ISO 8601 date, else a 400. A date names the whole day, so
  `LessThanOrEqual` with `2024-01-31` matches all of January 31. A text value (`name > 'M'`) is now a 400.
- Sparse fieldsets always keep the key columns eager loading needs; the `optimizations.relation_select_mode` option is
  gone (its `'off'` mode left relations unmatched when a fieldset left out their keys). A published key is ignored.
- Filters using `HandlesRelationFiltering` override `resolveConstraint()` instead of `hasEffectiveConstraint()`: it
  reads the value once, `applyOnQuery()` receives what it returns, and `null` adds no condition. `ExactFilter` passes
  the value through unchanged, so its subclasses receive the request value as before.
- A filter value is validated again after `prepareValueWith()` only when preparation changed it; with
  `allowStructuredInput()` only the prepared value is validated. Filters override `validateValueShape()` alone.
- After a build, `getPassthroughFilters()` returns the values the build read without preparing the filters again.
- No longer `@api`: `getOwnFilterValueFromRequest()`, `resolveRuntimeAttributesByOwner()`,
  `resolveRuntimeAttributeNameForInclude()`, `withRuntimeAttributesInFieldTree()`,
  `resolveParentColumnsForEagerLoads()`, `applySafeRelationSelectToQuery()`, `topLevelEagerLoadNames()`,
  `convertFilterKeys()`, `assertJsonObjectBody()`, `OperatorFilter::applyLike()`, `parseDynamicOperator()` and
  `applyIncludeKeepingEagerLoads()`. Marked `@internal`: `FilterValueParser::trashedMode()`, `lenientDate()`,
  `unixTimestamp()` and `dynamic()`.
- `?filter=` with a blank value applies no filters (it was a 400).
- Range and date range filters reject keys other than their boundary keys with a 400 (a typo such as `form` was ignored).
- JSON contains filters reject keyed or nested values with a 400 and drop blank items.
- Scope filters check every value against its parameter's type, including unions, `bool`, `array` and class types, and
  read `bool` parameters as booleans (a cast made `false` true). A value no type accepts is a 400, not a `TypeError`.
- Boolean configuration options are validated: `'false'` and `'0'` are false, a value that is not a boolean throws
  `InvalidArgumentException`.
- `getQuery()` and `toBase()` called through the wizard finalize the configuration like `toQuery()`.
- After a build fails with its builder handed out, building the wizard again throws `LogicException`; so does a request
  change after the builder was handed out or changed through the wizard.
- Marked `@internal`: `Support\ParameterParser`, `FilterValueTransformer`, `NameConverter`, `RelationResolver` and
  `DotNotationTreeBuilder`.
- Traits used outside the wizards: `RequiresWizardContext` requires `invalidateBuild()`, and `HandlesSafeRelationSelect`
  requires `parseDefaultAppendsToGrouped()` instead of `getEffectiveDefaultAppends()`.

Fixed:

- `chunk()`, `chunkById()` and `chunkByIdDesc()` pass the page number to the callback.
- Range and `Dynamic` comparisons with fractions or integers beyond 64 bits work on PostgreSQL integer columns (they were
  500s).
- Two fieldsets for one relation, such as a relation and its alias, merge regardless of their order.
- A configuration call the wizard refuses leaves it unchanged.
- With snake-case conversion, sorts, includes and fieldsets that differ only in naming count once.
- Relation sorts accept a table-qualified column.
- A relation `LIKE` filter whose values are all blank adds no `whereHas`.
- Dotted parameter names (`'filters' => 'page.filter'`) read nested query-string values.
- A retry after a failed build reads the current configuration for filter, sort and include definitions.
- Relation fieldsets apply when a subclass overrides `finalizeBuild()` without calling the parent.
- Relation fieldsets are validated once per build instead of two or three times.

Performance:

- A build reads the package configuration and resolves the parameters manager at most twice each (it was dozens of
  times).
- Relation fieldsets narrow each eager load inside its constraint, from the relation Eloquent already built, instead of
  building every relation for an up-front plan; nested relation paths reuse their resolved parent.
- Schema `defaultFilters()` is called once per filter resolution instead of once per filter without a request value.

Removed:

- `optimizations.relation_select_mode`, `QueryWizardConfig::getRelationSelectMode()` and `isSafeRelationSelectEnabled()`.
- `Contracts\WizardContextInterface`; the wizards keep its public methods.
- `create()` on the limit exceptions (`MaxFiltersCountExceeded` and the others); construct them with `new`.
- `AbstractFilter::validateIncomingValueShape()`, `validatePreparedValueShape()` and `disallowStructuredInput()`,
  `BaseQueryWizard::validateIncomingFilterValueShape()` and `validatePreparedFilterValueShape()`.
- `hasEffectiveConstraint()` (see `resolveConstraint()`), `AbstractRangeFilter::applyOnQuery()` and `formatValue()`.
- `BaseQueryWizard::applyFiltersToSubject()`, `applySortsToSubject()`, `applyIncludesToSubject()` and
  `applyFieldsToSubject()`; `build()` applies the validated steps itself.
- Unused protected methods: `HandlesFields::isFieldsRequestEmpty()`, `HandlesAppends::extractRelationAttributes()` and
  `prefixGroupAttributes()`, `HandlesRelationAttributeValidation::isAttributeAllowed()`,
  `HandlesConfiguration::normalizePublicNames()`, `BaseQueryWizard::canApplyDefaultSortsWithoutAllowlist()`,
  `RelationResolver::clearCache()`. `ModelQueryWizard::invalidateProcessedState()`, `ensureMutableBeforeProcessing()`
  and `resolveRequestedIncludeNames()` are gone; `ModelQueryWizard` implements `invalidateBuild()` instead.

### Requirements

- PHP 8.2+ (tested on 8.2–8.5) and Laravel 12.61.1+ or 13.12.0+. Laravel 10 and 11 are no longer supported.

### Added

- `InvalidQuery::$errorCode` and `$parameter` on every exception, with stable codes per error.
- `InvalidFilterValue::$reason`, and messages that say what a filter expected.
- `InvalidRequestBody` for a malformed or non-object JSON body in `body` mode.
- `DateRangeFilter::lenient()` and `DateRangeFilter::asUnixTimestamp()`.
- `Support\FilterValueParser` and `Support\ParsedDate` for reading request values in custom filters.
- `Contracts\ProvidesRuntimeAttributes` and `CallbackInclude::withRuntimeAttributes()`: attributes added by an include
  stay visible under sparse fieldsets.
- Post-processed wrappers `lazyById()`, `lazyByIdDesc()`, `chunkByIdDesc()`, `eachById()`, `each()`, `chunkMap()`;
  finders called through the wizard (`find()`, `sole()`, `firstWhere()`, ...) post-process their results.
- Extension points marked `@api`, including `rollbackFailedBuild()`, `resolveConstraint()`,
  `resolveAppendAccessorModel()` and `QueryWizardConfig::snapshot()`.

### Changed

- Filter values a filter cannot read are rejected with a 400 instead of skipping the filter: `asBoolean()`, null,
  trashed, range, date range, `Dynamic` operator and partial filters.
- Blank filter values (whitespace, `,`, lists of blanks) are absent, and blank items are dropped from partial and LIKE
  lists; relation filters without a condition add no `whereHas`.
- Date range bounds must be dates or ISO 8601 date-times, are read in the application timezone, and a date-only `to`
  covers the whole day. `dateFormat()` formats every bound.
- `Dynamic` operators compare ISO dates and decimal numbers; operators inside lists are rejected.
- `Like`/`NotLike` operators match literally, accept lists and do not split values by default.
- `prepareValueWith()` calls chain instead of replacing each other.
- Scope filters check the number of values, and each value's type, against the scope's signature.
- Range filter lists must hold exactly two values, and range arrays only their boundary keys.
- A request filter key belongs to the deepest allowed filter name.
- Callback filters, sorts and includes replace the subject only with an instance of its class.
- Nested relations in count/aggregate sorts and count/exists includes throw when defined.
- Field and append names are validated more strictly (nested lists, dotted names, identifiers under wildcards,
  disallowed names under wildcards, accessors for wildcard appends, relation appends).
- Root default fields apply whenever the request has no root fieldset.
- Empty `default*()` calls mean "no defaults".
- Appends are validated during the build; `build()` returns `Builder|Relation` and finalizes the configuration.
- Builder calls through the wizard adopt only the same subject; clones keep the "configuration finalized" state.
- Developer defaults over a request limit throw `InvalidArgumentException`.
- Configuration values are validated, missing keys take package defaults, and each build reads the configuration once.
- Snake-case conversion renames filter names only, not keys inside filter values.
- Exception messages use the configured parameter names.

### Fixed

- Client includes no longer replace developer eager-load constraints; nested includes keep the parent's constraint.
- Sparse root fields keep the parent keys of every eager load, and relation selects keep `withCount` and definition
  selects; select bindings of preserved expressions are kept.
- Count/exists includes no longer duplicate a count already selected by a sort or the developer.
- `cursorPaginate()` and `chunkById()` work when the fieldset leaves out their order columns.
- `cursor()` loads includes.
- A failed build is rolled back instead of applying taps and filters twice on retry. If the builder was already handed
  out with `toQuery()`, `getSubject()` or `build()`, building again throws `LogicException`.
- `ModelQueryWizard` loads `exists` includes and validates the request before touching the model.
- `disallowedIncludes()` also matches an aliased include's relation path.
- Relation subjects (`for($user->posts())`) work with filters and sparse fieldsets.
- Relation filters recognize relations registered with `resolveRelationUsing()`.
- Partial filters work on non-text PostgreSQL columns.
- Sort parsing and field selection run in linear time; snake-case conversion uses a bounded cache.
- The scoped `QueryParametersManager` follows a rebound request.
- Several 500 errors on malformed input are now 400s.

### Security

- Under `allowedFields('*')`, a field name that differs from a disallowed field or a `$hidden` model attribute only in
  letter case is rejected. On MySQL, which matches column names without regard to case, `fields=NAME` returned the value
  of a hidden or disallowed `name` column.

### Removed

- `NullFilter::strict()` and `DateRangeFilter::strict()`: strict parsing is the default.
- `OperatorFilter::requiresNumericValue()` and `QueryParametersManager::convertFiltersArray()`.

## [2.1.3] and earlier

See the [GitHub releases](https://github.com/jackardios/laravel-query-wizard/releases).

[3.0.0]: https://github.com/Jackardios/laravel-query-wizard/compare/v2.1.3...HEAD
[2.1.3]: https://github.com/Jackardios/laravel-query-wizard/releases/tag/v2.1.3

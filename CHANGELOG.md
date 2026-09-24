# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [3.0.0] - Unreleased

Version 3 is a rewrite: fluent `allowed*()` configuration, `EloquentFilter`/`EloquentSort`/`EloquentInclude` factories,
resource schemas, `ModelQueryWizard::process()`, request limits. See [UPGRADE.md](UPGRADE.md) for migrating from v2.x
and from `dev-master` snapshots. The entries below cover the changes made before the release.

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
- Extension points marked `@api`, including `rollbackFailedBuild()`, `hasEffectiveConstraint()`,
  `applyIncludeKeepingEagerLoads()`, `resolveAppendAccessorModel()` and `QueryWizardConfig::snapshot()`.

### Changed

- Filter values a filter cannot read are rejected with a 400 instead of skipping the filter: `asBoolean()`, null,
  trashed, range, date range, DYNAMIC operator and partial filters.
- Blank filter values (whitespace, `,`, lists of blanks) are absent, and blank items are dropped from partial and LIKE
  lists; relation filters without a condition add no `whereHas`.
- Date range bounds must be dates or ISO 8601 date-times, are read in the application timezone, and a date-only `to`
  covers the whole day. `dateFormat()` formats every bound.
- DYNAMIC operators compare ISO dates and decimal numbers; operators inside lists are rejected.
- `LIKE`/`NOT_LIKE` operators match literally, accept lists and do not split values by default.
- `prepareValueWith()` calls chain instead of replacing each other.
- Scope filters check the number of values, and values for `int`/`float` parameters, against the scope's signature.
- Range filter lists must hold exactly two values.
- A request filter key belongs to the deepest allowed filter name.
- Callback filters, sorts and includes replace the subject only with an instance of its class.
- Empty sort variants honor `disable_invalid_sort_query_exception` and fall back to default sorts.
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
- A failed build is rolled back instead of applying taps and filters twice on retry (unless the builder was already
  handed out with `toQuery()`, `getSubject()` or `build()`).
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

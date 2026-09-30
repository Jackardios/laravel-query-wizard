<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Concerns\HandlesAppends;
use Jackardios\QueryWizard\Concerns\HandlesConfiguration;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesFilters;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;
use Jackardios\QueryWizard\Concerns\HandlesParameterScope;
use Jackardios\QueryWizard\Concerns\HandlesRelationPostProcessing;
use Jackardios\QueryWizard\Concerns\HandlesSafeRelationSelect;
use Jackardios\QueryWizard\Concerns\HandlesSorts;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\EagerLoadsRelation;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Eloquent\EloquentShape;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\Exceptions\MaxFilterValuesCountExceeded;
use Jackardios\QueryWizard\Exceptions\MaxSortsCountExceeded;
use Jackardios\QueryWizard\Filters\PassthroughFilter;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;
use Jackardios\QueryWizard\Support\FilterValueParser;
use Jackardios\QueryWizard\Values\Sort;
use Throwable;

/**
 * Abstract base class for query wizards.
 *
 * Provides common configuration API and query building logic.
 * Subclasses implement filter/sort/include application and query execution.
 *
 * @template TSubject
 */
abstract class BaseQueryWizard implements QueryWizardInterface
{
    use HandlesAppends;
    use HandlesConfiguration;
    use HandlesFields;
    use HandlesFilters;
    use HandlesIncludes;
    use HandlesParameterScope;
    use HandlesRelationPostProcessing;
    use HandlesSafeRelationSelect;
    use HandlesSorts;

    /**
     * The subject the build shapes. applyFilter(), the sorts and the shaping hooks
     * replace it when an operation returns a new instance.
     *
     * @var TSubject
     *
     * @api
     */
    protected mixed $subject;

    /** @var TSubject */
    protected mixed $originalSubject;

    protected QueryParametersManager $parameters;

    protected QueryWizardConfig $config;

    protected ?ResourceSchemaInterface $schema = null;

    /** @var array<callable(TSubject): mixed> */
    protected array $tapCallbacks = [];

    protected bool $built = false;

    private bool $building = false;

    /** @var array<string, mixed> */
    private array $builtPassthroughFilters = [];

    /**
     * Build-scope signature (parameters manager + request identity) used to
     * detect stale build cache when a wizard instance crosses request boundary.
     */
    protected ?string $builtScopeSignature = null;

    /**
     * @param  TSubject  $subject
     * @param  QueryParametersManager|null  $parameters  Null resolves the request-scoped manager on every read
     * @param  QueryWizardConfig|null  $config  Null uses the container's configuration
     *
     * @api
     */
    protected function __construct(
        mixed $subject,
        ?QueryParametersManager $parameters = null,
        ?QueryWizardConfig $config = null,
        ?ResourceSchemaInterface $schema = null,
    ) {
        $this->subject = $subject;
        $this->originalSubject = is_object($subject) ? clone $subject : $subject;
        $this->resolveParametersFromContainer = $parameters === null;
        $this->parameters = $parameters ?? app(QueryParametersManager::class);
        $this->config = $config ?? app(QueryWizardConfig::class);
        $this->schema = $schema;
    }

    /**
     * Whether the subject has been built for the current configuration and request.
     *
     * @api
     */
    protected function isBuilt(): bool
    {
        return $this->built;
    }

    /**
     * The resource's model, when the wizard knows it without running a query.
     *
     * The default resolveAppendAccessorModel() checks wildcard appends and the
     * letter case of hidden fields against it and its relations; null skips both.
     *
     * @api
     */
    protected function resourceModel(): ?Model
    {
        return null;
    }

    /**
     * The model whose accessors back appends at a relation path ('' for the root):
     * resourceModel() or the related model it reaches.
     *
     * @api
     */
    protected function resolveAppendAccessorModel(string $relationPath): ?Model
    {
        $model = $this->resourceModel();

        if ($model === null || $relationPath === '') {
            return $model;
        }

        return $this->relationResolverFor($model)->resolve($relationPath)?->getRelated();
    }

    /**
     * The Eloquent side of the build in progress, for a wizard that loads the
     * resource's models through an Eloquent query of its own.
     *
     * Call it from finalizeBuild(): it validates the relation fieldsets and
     * the appends, so an invalid request fails before the subject runs. Apply
     * the result to the loading query with applyTo() and to the loaded models
     * with postProcess().
     *
     * @param  array<string, IncludeInterface>  $includes  The includes applyValidatedIncludes() received, by requested name, in order
     * @param  array<string>|null  $rootFields  The fields applyFields() received; null when it was not called
     * @param  array<string>  $requiredRootColumns  Root columns the loader needs (e.g. the key it matches models by):
     *                                              selected under a root fieldset, hidden unless requested
     *
     * @throws InvalidFieldQuery
     * @throws InvalidAppendQuery
     *
     * @api
     */
    final protected function resolveEloquentShape(array $includes, ?array $rootFields, array $requiredRootColumns = []): EloquentShape
    {
        $relationshipPaths = [];

        foreach ($includes as $include) {
            if ($include instanceof EagerLoadsRelation) {
                $relationshipPaths[] = $include->getRelation();
            }
        }

        $relationFieldMap = $this->validatedRelationFieldMap();
        $relationFieldsByPath = $this->safeRelationFieldsByPath(array_values(array_unique($relationshipPaths)), $relationFieldMap);
        $runtimeAttributes = $this->resolveRuntimeAttributesByOwner(array_map('strval', array_keys($includes)), $includes);
        $rootRuntimeAttributes = $runtimeAttributes[''] ?? [];
        unset($runtimeAttributes['']);

        return new EloquentShape(
            includes: array_values($includes),
            relationFieldsByPath: $relationFieldsByPath,
            rootFields: $rootFields === null ? null : array_values($rootFields),
            runtimeOnlyRootFields: $rootFields === null ? [] : $this->runtimeOnlyRootFields($rootFields, $rootRuntimeAttributes),
            rootVisibleFields: $rootFields === null ? null : $this->visibleRootFields($rootFields, $rootRuntimeAttributes),
            requiredRootColumns: array_values($requiredRootColumns),
            appendTree: $this->getValidRequestedAppendsTree(),
            relationFieldTree: $this->withRuntimeAttributesInFieldTree($this->buildRelationFieldTree($relationFieldMap), $runtimeAttributes),
        );
    }

    /**
     * Invalidate the build state when configuration changes.
     *
     * This ensures that calling build() after configuration changes
     * will re-apply all filters, sorts, includes, and fields.
     *
     * @throws \LogicException While the wizard builds or a schema method runs
     *
     * @api
     */
    protected function invalidateBuild(): void
    {
        $this->assertNotReadingSchema();

        if ($this->building) {
            throw new \LogicException('The wizard cannot be reconfigured while it builds.');
        }

        $this->resetBuild($this->built);
    }

    private function resetBuild(bool $restoreSubject): void
    {
        if ($restoreSubject && isset($this->originalSubject)) {
            $this->subject = is_object($this->originalSubject)
                ? clone $this->originalSubject
                : $this->originalSubject;
        }

        $this->built = false;
        $this->builtScopeSignature = null;
        $this->builtPassthroughFilters = [];
        $this->forgetConfigurationMemo();
        $this->invalidateFilterCache();
        $this->invalidateSortCache();
        $this->invalidateIncludeCache();
    }

    /**
     * Apply field selection to subject.
     *
     * @param  array<string>  $fields
     *
     * @api
     */
    abstract protected function applyFields(array $fields): void;

    /**
     * Get the configuration, as of the current build.
     */
    public function getConfig(): QueryWizardConfig
    {
        return $this->configSnapshot($this->config);
    }

    /**
     * Get the parameters manager.
     */
    public function getParametersManager(): QueryParametersManager
    {
        if (! $this->building) {
            $this->parameters = $this->syncParametersManager($this->parameters);
        }

        return $this->parameters;
    }

    protected function resolveBuildScopeSignature(): string
    {
        return $this->resolveParametersScopeSignature($this->getParametersManager());
    }

    /**
     * Get the schema instance.
     */
    public function getSchema(): ?ResourceSchemaInterface
    {
        return $this->schema;
    }

    /**
     * Set allowed filters, replacing the schema's and any earlier call; addAllowedFilters() adds instead.
     *
     * Empty array means all filters are forbidden.
     * Not calling this method falls back to schema filters (if any).
     *
     * @param  FilterInterface|string|array<FilterInterface|string>  ...$filters
     */
    public function allowedFilters(FilterInterface|string|array ...$filters): static
    {
        $this->invalidateBuild();
        $this->allowedFilters = $this->flattenDefinitions($filters, FilterInterface::class);
        $this->allowedFiltersExplicitlySet = true;
        $this->addedAllowedFilters = [];

        return $this;
    }

    /**
     * Add to the allowed filters: the list set with allowedFilters(), or the schema's when none was set.
     *
     * @param  FilterInterface|string|array<FilterInterface|string>  ...$filters
     */
    public function addAllowedFilters(FilterInterface|string|array ...$filters): static
    {
        $this->invalidateBuild();
        $this->addedAllowedFilters = [...$this->addedAllowedFilters, ...$this->flattenDefinitions($filters, FilterInterface::class)];

        return $this;
    }

    /**
     * Disallow filters, including the schema's; repeated calls add to the list.
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedFilters(string|array ...$names): static
    {
        $this->invalidateBuild();
        $this->disallowedFilters = [...$this->disallowedFilters, ...$this->flattenStringArray($names)];

        return $this;
    }

    /**
     * Set allowed sorts, replacing the schema's and any earlier call; addAllowedSorts() adds instead.
     *
     * @param  SortInterface|string|array<SortInterface|string>  ...$sorts
     */
    public function allowedSorts(SortInterface|string|array ...$sorts): static
    {
        $this->invalidateBuild();
        $this->allowedSorts = $this->flattenDefinitions($sorts, SortInterface::class);
        $this->allowedSortsExplicitlySet = true;
        $this->addedAllowedSorts = [];

        return $this;
    }

    /**
     * Add to the allowed sorts: the list set with allowedSorts(), or the schema's when none was set.
     *
     * @param  SortInterface|string|array<SortInterface|string>  ...$sorts
     */
    public function addAllowedSorts(SortInterface|string|array ...$sorts): static
    {
        $this->invalidateBuild();
        $this->addedAllowedSorts = [...$this->addedAllowedSorts, ...$this->flattenDefinitions($sorts, SortInterface::class)];

        return $this;
    }

    /**
     * Disallow sorts, including the schema's; repeated calls add to the list.
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedSorts(string|array ...$names): static
    {
        $this->invalidateBuild();
        $this->disallowedSorts = [...$this->disallowedSorts, ...$this->flattenStringArray($names)];

        return $this;
    }

    /**
     * Set default sorts.
     *
     * Replaces the schema defaults; call it without arguments for no defaults.
     *
     * @param  string|Sort|array<string|Sort>  ...$sorts
     */
    public function defaultSorts(string|Sort|array ...$sorts): static
    {
        $this->invalidateBuild();
        $flatSorts = [];
        foreach ($sorts as $sort) {
            if (is_array($sort)) {
                foreach ($sort as $s) {
                    $flatSorts[] = $this->extractSortName($s);
                }
            } else {
                $flatSorts[] = $this->extractSortName($sort);
            }
        }
        $this->defaultSorts = $flatSorts;
        $this->defaultSortsExplicitlySet = true;

        return $this;
    }

    /**
     * Add a tap callback to modify the subject.
     *
     * Callback return value is ignored.
     *
     * @param  callable(TSubject): mixed  $callback
     */
    public function tap(callable $callback): static
    {
        $this->invalidateBuild();
        $this->tapCallbacks[] = $callback;

        return $this;
    }

    /**
     * Build the query (apply filters, sorts, includes, fields).
     *
     * Returns the underlying subject (e.g., Eloquent Builder) for execution.
     *
     * @return TSubject
     */
    public function build(): mixed
    {
        $currentScopeSignature = $this->resolveBuildScopeSignature();

        if ($this->built) {
            if ($this->builtScopeSignature === $currentScopeSignature) {
                return $this->subject;
            }

            $this->invalidateBuild();
        }

        $this->building = true;

        try {
            $this->forgetConfigurationMemo();
            $this->applyTapCallbacks();
            $this->prepareBuild();

            $filters = $this->resolvePreparedFilters();
            $sorts = $this->resolveSortsToApply();
            $includes = $this->resolveIncludesToApply();
            $fields = $this->resolveValidatedRootFields();

            $passthroughFilters = [];

            foreach ($filters as $name => ['filter' => $filter, 'value' => $value]) {
                if ($filter instanceof PassthroughFilter) {
                    $passthroughFilters[$name] = $value;
                } else {
                    $this->applyFilter($filter, $value);
                }
            }

            foreach ($sorts as [$sort, $direction]) {
                $this->subject = $sort->apply($this->subject, $direction);
            }

            if ($includes !== null) {
                $this->applyValidatedIncludes(...$includes);
            }

            if ($fields !== null) {
                $this->applyFields($fields);
            }

            $this->finalizeBuild();
        } catch (Throwable $e) {
            $this->rollbackFailedBuild();

            throw $e;
        } finally {
            $this->building = false;
        }

        $this->built = true;
        $this->builtScopeSignature = $currentScopeSignature;
        $this->builtPassthroughFilters = $passthroughFilters;

        return $this->subject;
    }

    /**
     * Undo a build that threw, so the next build starts from the original subject.
     *
     * Called with the exception still pending, before it is rethrown. Subclasses
     * that keep state derived from the build reset it here and call the parent.
     *
     * @api
     */
    protected function rollbackFailedBuild(): void
    {
        $this->resetBuild(true);
    }

    /**
     * Hook invoked after tap callbacks and before any query shaping is applied.
     *
     * @api
     */
    protected function prepareBuild(): void {}

    /**
     * Hook invoked once query shaping has been applied and before the build is
     * marked as complete.
     *
     * @api
     */
    protected function finalizeBuild(): void {}

    /**
     * Get the underlying subject without building.
     *
     * @return TSubject
     */
    public function getSubject(): mixed
    {
        return $this->subject;
    }

    /**
     * Get passthrough filter values from request.
     *
     * Reuses the values of a current build, so the filters are not prepared again.
     *
     * @return Collection<string, mixed>
     */
    public function getPassthroughFilters(): Collection
    {
        if ($this->built && $this->builtScopeSignature === $this->resolveBuildScopeSignature()) {
            return collect($this->builtPassthroughFilters);
        }

        $result = collect();

        foreach ($this->resolvePreparedFilters() as $name => $resolvedFilter) {
            if ($resolvedFilter['filter'] instanceof PassthroughFilter) {
                $result[$name] = $resolvedFilter['value'];
            }
        }

        return $result;
    }

    protected function applyTapCallbacks(): void
    {
        foreach ($this->tapCallbacks as $callback) {
            $callback($this->subject);
        }
    }

    /**
     * Resolve, validate, and prepare all current filters.
     *
     * @return array<string, array{filter: FilterInterface, value: mixed}>
     */
    protected function resolvePreparedFilters(): array
    {
        $filters = $this->getEffectiveFilters();
        $requestedFilterNames = $this->extractRequestedFilterNames();

        $this->validateFiltersLimit(count($requestedFilterNames));
        $this->validateRequestedFilterNames($requestedFilterNames, $this->resolveAllowedFilterNames($filters));

        $shadowedFilterNames = $this->resolveShadowedFilterNames($filters);
        $resolvedFilters = [];
        $this->schemaDefaultFilters = null;

        try {
            $this->assertSchemaDefaultFiltersAreKnown();

            foreach ($filters as $name => $filter) {
                if (isset($shadowedFilterNames[$name])) {
                    continue;
                }

                $preparedValue = $this->resolvePreparedFilterValue($filter);

                if ($preparedValue === null) {
                    continue;
                }

                $resolvedFilters[$name] = [
                    'filter' => $filter,
                    'value' => $preparedValue,
                ];
            }
        } finally {
            $this->schemaDefaultFilters = null;
        }

        return $resolvedFilters;
    }

    /**
     * Resolve, validate and prepare a single filter value.
     *
     * Raw value -> shape validation (skipped for structured input) ->
     * prepareValue() -> shape validation of a changed prepared value.
     * Returns null when the filter must be skipped.
     *
     * Override to support composite filters that resolve their leaves instead of
     * a single request key.
     *
     * @api
     */
    protected function resolvePreparedFilterValue(FilterInterface $filter): mixed
    {
        $value = $this->resolveFilterValue($filter);

        if ($value === null) {
            return null;
        }

        $structuredInputAllowed = $filter->allowsStructuredInput();

        if (! $structuredInputAllowed) {
            $this->validateFilterValueShape($filter, $value);
        }

        $preparedValue = $filter->prepareValue($value);

        if ($preparedValue === null) {
            return null;
        }

        if ($structuredInputAllowed || $preparedValue !== $value) {
            $this->validateFilterValueShape($filter, $preparedValue);
        }

        return $preparedValue;
    }

    /**
     * Resolve raw filter value from request/default according to filter presence rules.
     *
     * Priority: request value > filter->getDefault() > schema->defaultFilters()
     *
     * A blank value (see FilterValueParser::isBlank()) is absent: null is returned,
     * or the default when `filters.apply_default_on_null` is enabled.
     */
    protected function resolveFilterValue(FilterInterface $filter): mixed
    {
        $name = $this->normalizePublicPath($filter->getName());
        $splitValues = $filter->shouldSplitValues();
        [$inRequest, $value] = $this->getOwnFilterValueFromRequest($name, $splitValues);

        if ($inRequest) {
            if (! FilterValueParser::isBlank($value)) {
                $this->validateFilterValuesLimit($filter, $value);

                return $value;
            }

            if (! $this->getConfig()->shouldApplyFilterDefaultOnNull()) {
                return null;
            }
        }

        $default = $this->getFilterDefault($filter);

        return FilterValueParser::isBlank($default) ? null : $default;
    }

    private function validateFilterValuesLimit(FilterInterface $filter, mixed $value): void
    {
        $limit = $this->getConfig()->getMaxFilterValuesCount();

        if ($limit === null || ! is_array($value)) {
            return;
        }

        $count = self::countLeafValues($value, $limit);

        if ($count > $limit) {
            throw new MaxFilterValuesCountExceeded($filter->getName(), $count, $limit);
        }
    }

    /**
     * Scalars in a nested array, counting no further than one past $stopAfter.
     *
     * @param  array<array-key, mixed>  $value
     */
    private static function countLeafValues(array $value, int $stopAfter): int
    {
        $count = 0;

        foreach ($value as $item) {
            $count += is_array($item) ? self::countLeafValues($item, $stopAfter - $count) : 1;

            if ($count > $stopAfter) {
                break;
            }
        }

        return $count;
    }

    /**
     * @throws \InvalidArgumentException When a schema default names no allowed filter
     */
    private function assertSchemaDefaultFiltersAreKnown(): void
    {
        $defaults = $this->getSchemaDefaultFilters();

        if ($defaults === []) {
            return;
        }

        $known = [];
        foreach ($this->getConfiguredFilters() as $filter) {
            $known[is_string($filter) ? $filter : $filter->getName()] = true;
        }

        $unknown = array_diff(array_map(strval(...), array_keys($defaults)), array_keys($known));

        if ($unknown !== []) {
            throw new \InvalidArgumentException(
                'Schema defaultFilters() names no allowed filter: `'.implode('`, `', $unknown).'`. '
                ."Key each default by an allowed filter's public name (its alias, if it has one)."
            );
        }
    }

    /**
     * Get default value for a filter (from filter itself or schema).
     */
    protected function getFilterDefault(FilterInterface $filter): mixed
    {
        $filterDefault = $filter->getDefault();
        if ($filterDefault !== null) {
            return $filterDefault;
        }

        $schemaDefaults = $this->getSchemaDefaultFilters();

        return $schemaDefaults[$filter->getName()] ?? null;
    }

    /**
     * Apply a single filter to the subject.
     *
     * Override this method to customize how individual filters are applied.
     *
     * @api
     */
    protected function applyFilter(FilterInterface $filter, mixed $preparedValue): void
    {
        $this->subject = $filter->apply($this->subject, $preparedValue);
    }

    /**
     * @param  array<int, string>  $requestedFilterNames
     * @param  array<int, string>  $allowedFilterNames
     */
    protected function validateRequestedFilterNames(array $requestedFilterNames, array $allowedFilterNames): void
    {
        $allowedFilterNamesIndex = array_flip($allowedFilterNames);

        foreach ($requestedFilterNames as $filterName) {
            if (! isset($allowedFilterNamesIndex[$filterName]) && ! $this->getConfig()->shouldIgnoreUnknownFilters()) {
                throw InvalidFilterQuery::filtersNotAllowed(
                    collect([$filterName]),
                    collect($allowedFilterNames)
                );
            }
        }
    }

    private function validateFilterValueShape(FilterInterface $filter, mixed $value): void
    {
        $details = $filter->validateValueShape($value);

        if ($details !== null) {
            throw InvalidFilterQuery::invalidFormat($details);
        }
    }

    /**
     * Validate the requested (or default) sorts and resolve them to definitions.
     *
     * @return list<array{SortInterface, 'asc'|'desc'}>
     *
     * @throws InvalidSortQuery When requested sort is not allowed
     * @throws MaxSortsCountExceeded When sort count exceeds configured limit
     */
    private function resolveSortsToApply(): array
    {
        $sorts = $this->getEffectiveSorts();
        $parameters = $this->getParametersManager();
        $requestedSorts = $parameters->getSorts();
        $defaultSorts = $this->getEffectiveDefaultSorts();

        $sortRequested = $parameters->hasSimpleParameter('sorts');
        if ($sortRequested && $requestedSorts->isEmpty()) {
            $parameter = $this->getConfig()->getSortsParameterName() ?: 'sort';

            throw InvalidSortQuery::invalidFormat(
                "The `{$parameter}` parameter must contain at least one sort field when present."
            );
        }

        $sortsIndex = [];
        foreach ($sorts as $sort) {
            $sortsIndex[ltrim($this->normalizePublicPath($sort->getName()), '-')] = $sort;
        }

        if (! $sortRequested) {
            return $this->resolveDefaultSorts($defaultSorts, $sortsIndex);
        }

        if ($sortsIndex === []) {
            if (! $this->getConfig()->shouldIgnoreUnknownSorts()) {
                throw InvalidSortQuery::sortsNotAllowed(
                    $requestedSorts->map(fn (Sort $s) => $s->getField()),
                    collect([])
                );
            }

            return [];
        }

        $this->validateSortsLimit($requestedSorts->count());

        $allowedSortNames = array_keys($sortsIndex);
        $appliedSorts = [];
        $resolved = [];

        foreach ($requestedSorts as $sortValue) {
            /** @var Sort $sortValue */
            $field = $sortValue->getField();

            if (! isset($sortsIndex[$field])) {
                if (! $this->getConfig()->shouldIgnoreUnknownSorts()) {
                    throw InvalidSortQuery::sortsNotAllowed(collect([$field]), collect($allowedSortNames));
                }

                continue;
            }

            if (isset($appliedSorts[$field])) {
                continue;
            }
            $appliedSorts[$field] = true;

            $resolved[] = [$sortsIndex[$field], $sortValue->getDirection()];
        }

        return $resolved;
    }

    /**
     * Resolve the default sorts, which come from the developer and so apply
     * without being allowed: an allowed sort of the same name is used, else
     * the name is normalized like a string passed to allowedSorts().
     *
     * @param  list<string>  $defaultSorts
     * @param  array<string, SortInterface>  $sortsIndex
     * @return list<array{SortInterface, 'asc'|'desc'}>
     *
     * @throws \InvalidArgumentException When a default sort is disallowed or over the limit
     */
    private function resolveDefaultSorts(array $defaultSorts, array $sortsIndex): array
    {
        $resolved = [];
        $seen = [];

        foreach ($defaultSorts as $default) {
            $sortValue = new Sort($default);
            $field = $sortValue->getField();

            if (isset($seen[$field])) {
                continue;
            }
            $seen[$field] = true;

            $sort = $sortsIndex[$field] ?? null;

            if ($sort === null) {
                if ($this->disallowedSorts !== [] && $this->isNameDisallowed($field, $this->disallowedSorts)) {
                    throw new \InvalidArgumentException("Default sort `{$field}` is disallowed by disallowedSorts().");
                }

                $sort = $this->normalizeStringToSort($field);
            }

            $resolved[] = [$sort, $sortValue->getDirection()];
        }

        $this->assertDefaultSortsWithinLimit(count($resolved));

        return $resolved;
    }

    private function assertDefaultSortsWithinLimit(int $count): void
    {
        $this->assertDefaultWithinLimit('The number of default sorts', $count, $this->getConfig()->getMaxSortsCount(), 'max_sorts_count');
    }

    /**
     * Apply validated includes to the subject.
     *
     * Override this method to customize how includes are applied.
     *
     * @param  array<int, string>  $validRequestedIncludes
     * @param  array<string, IncludeInterface>  $includesIndex
     *
     * @api
     */
    protected function applyValidatedIncludes(array $validRequestedIncludes, array $includesIndex): void
    {
        foreach ($validRequestedIncludes as $includeName) {
            $include = $includesIndex[$includeName];
            $this->subject = $include->apply($this->subject);
        }
    }

    /**
     * A clone carries the subject forward exactly as it stands.
     *
     * The build flag is deliberately left alone. Rebuilding from the pristine
     * subject would drop anything the caller applied through the builder proxy
     * (->where(), ->orderBy(), ...), and leaving the flag set while reusing the
     * built subject would re-apply filters and sorts on top of themselves.
     * Carrying both across keeps the clone consistent with its source.
     *
     * Derived post-processing state describes this same subject, so subclasses
     * carry it over rather than clearing it - clearing state that only build()
     * can regenerate is what leaves a cloned wizard permanently incomplete.
     */
    public function __clone(): void
    {
        if (is_object($this->subject)) {
            $this->subject = clone $this->subject;
        }

        if (isset($this->originalSubject) && is_object($this->originalSubject)) {
            $this->originalSubject = clone $this->originalSubject;
        }
    }
}

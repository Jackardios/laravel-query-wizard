<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Concerns\HandlesAppends;
use Jackardios\QueryWizard\Concerns\HandlesConfiguration;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesFilters;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;
use Jackardios\QueryWizard\Concerns\HandlesParameterScope;
use Jackardios\QueryWizard\Concerns\HandlesSorts;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Contracts\WizardContextInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;
use Jackardios\QueryWizard\Values\Sort;

/**
 * Abstract base class for query wizards.
 *
 * Provides common configuration API and query building logic.
 * Subclasses implement filter/sort/include application and query execution.
 *
 * @template TSubject
 */
abstract class BaseQueryWizard implements QueryWizardInterface, WizardContextInterface
{
    use HandlesAppends;
    use HandlesConfiguration;
    use HandlesFields;
    use HandlesFilters;
    use HandlesIncludes;
    use HandlesParameterScope;
    use HandlesSorts;

    /** @var TSubject */
    protected mixed $subject;

    /** @var TSubject */
    protected mixed $originalSubject;

    protected QueryParametersManager $parameters;

    protected QueryWizardConfig $config;

    protected ?ResourceSchemaInterface $schema = null;

    /** @var array<callable(TSubject): mixed> */
    protected array $tapCallbacks = [];

    protected bool $built = false;

    /**
     * Build-scope signature (parameters manager + request identity) used to
     * detect stale build cache when a wizard instance crosses request boundary.
     */
    protected ?string $builtScopeSignature = null;

    /**
     * Invalidate the build state when configuration changes.
     *
     * This ensures that calling build() after configuration changes
     * will re-apply all filters, sorts, includes, and fields.
     */
    protected function invalidateBuild(): void
    {
        if ($this->built && isset($this->originalSubject)) {
            $this->subject = is_object($this->originalSubject)
                ? clone $this->originalSubject
                : $this->originalSubject;
        }

        $this->built = false;
        $this->builtScopeSignature = null;
        $this->invalidateFilterCache();
        $this->invalidateSortCache();
        $this->invalidateIncludeCache();
    }

    /**
     * Apply field selection to subject.
     *
     * @param  array<string>  $fields
     */
    abstract protected function applyFields(array $fields): void;

    /**
     * Get the configuration instance.
     */
    public function getConfig(): QueryWizardConfig
    {
        return $this->config;
    }

    /**
     * Get the parameters manager.
     */
    public function getParametersManager(): QueryParametersManager
    {
        $this->parameters = $this->syncParametersManager($this->parameters);

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
     * Set the resource schema for configuration.
     *
     * The schema provides default filters, sorts, includes, fields, and appends.
     * Explicit calls to allowed*() methods override schema definitions.
     *
     * @param  class-string<ResourceSchemaInterface>|ResourceSchemaInterface  $schema
     */
    public function schema(string|ResourceSchemaInterface $schema): static
    {
        $this->schema = is_string($schema) ? app($schema) : $schema;
        $this->invalidateBuild();

        return $this;
    }

    /**
     * Set allowed filters.
     *
     * Empty array means all filters are forbidden.
     * Not calling this method falls back to schema filters (if any).
     *
     * @param  FilterInterface|string|array<FilterInterface|string>  ...$filters
     */
    public function allowedFilters(FilterInterface|string|array ...$filters): static
    {
        $this->allowedFilters = $this->flattenDefinitions($filters);
        $this->allowedFiltersExplicitlySet = true;
        $this->invalidateBuild();

        return $this;
    }

    /**
     * Set disallowed filters (to override schema).
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedFilters(string|array ...$names): static
    {
        $this->disallowedFilters = $this->flattenStringArray($names);
        $this->invalidateBuild();

        return $this;
    }

    /**
     * Set allowed sorts.
     *
     * @param  SortInterface|string|array<SortInterface|string>  ...$sorts
     */
    public function allowedSorts(SortInterface|string|array ...$sorts): static
    {
        $this->allowedSorts = $this->flattenDefinitions($sorts);
        $this->allowedSortsExplicitlySet = true;
        $this->invalidateBuild();

        return $this;
    }

    /**
     * Set disallowed sorts (to override schema).
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedSorts(string|array ...$names): static
    {
        $this->disallowedSorts = $this->flattenStringArray($names);
        $this->invalidateBuild();

        return $this;
    }

    /**
     * Set default sorts.
     *
     * @param  string|Sort|array<string|Sort>  ...$sorts
     */
    public function defaultSorts(string|Sort|array ...$sorts): static
    {
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
        $this->invalidateBuild();

        return $this;
    }

    /**
     * Set allowed includes.
     *
     * @param  IncludeInterface|string|array<IncludeInterface|string>  ...$includes
     */
    public function allowedIncludes(IncludeInterface|string|array ...$includes): static
    {
        $this->allowedIncludes = $this->flattenDefinitions($includes);
        $this->allowedIncludesExplicitlySet = true;
        $this->invalidateBuild();

        return $this;
    }

    /**
     * Set disallowed includes (to override schema).
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedIncludes(string|array ...$names): static
    {
        $this->disallowedIncludes = $this->flattenStringArray($names);
        $this->invalidateBuild();

        return $this;
    }

    /**
     * Set default includes.
     *
     * @param  string|array<string>  ...$names
     */
    public function defaultIncludes(string|array ...$names): static
    {
        $this->defaultIncludes = $this->flattenStringArray($names);
        $this->invalidateBuild();

        return $this;
    }

    /**
     * Set allowed fields.
     *
     * Empty array means all fields are forbidden.
     * Use ['*'] to allow any fields requested by client.
     * Not calling this method falls back to schema fields (if any).
     *
     * @param  string|array<string>  ...$fields
     */
    public function allowedFields(string|array ...$fields): static
    {
        $this->allowedFields = $this->flattenStringArray($fields);
        $this->allowedFieldsExplicitlySet = true;
        $this->invalidateBuild();

        return $this;
    }

    /**
     * Set disallowed fields (to override schema).
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedFields(string|array ...$names): static
    {
        $this->disallowedFields = $this->flattenStringArray($names);
        $this->invalidateBuild();

        return $this;
    }

    /**
     * Set default fields.
     *
     * Applied only when request parameter is completely absent.
     *
     * @param  string|array<string>  ...$fields
     */
    public function defaultFields(string|array ...$fields): static
    {
        $this->defaultFields = $this->flattenStringArray($fields);
        $this->invalidateBuild();

        return $this;
    }

    /**
     * Set allowed appends.
     *
     * @param  string|array<string>  ...$appends
     */
    public function allowedAppends(string|array ...$appends): static
    {
        $this->allowedAppends = $this->flattenStringArray($appends);
        $this->allowedAppendsExplicitlySet = true;
        $this->invalidateBuild();

        return $this;
    }

    /**
     * Set disallowed appends (to override schema).
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedAppends(string|array ...$names): static
    {
        $this->disallowedAppends = $this->flattenStringArray($names);
        $this->invalidateBuild();

        return $this;
    }

    /**
     * Set default appends.
     *
     * @param  string|array<string>  ...$appends
     */
    public function defaultAppends(string|array ...$appends): static
    {
        $this->defaultAppends = $this->flattenStringArray($appends);
        $this->invalidateBuild();

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
        $this->tapCallbacks[] = $callback;
        $this->invalidateBuild();

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

        $this->applyTapCallbacks();
        $this->applyFiltersToSubject();
        $this->applySortsToSubject();
        $this->applyIncludesToSubject();
        $this->applyFieldsToSubject();

        $this->built = true;
        $this->builtScopeSignature = $currentScopeSignature;

        return $this->subject;
    }

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
     * @return Collection<string, mixed>
     */
    public function getPassthroughFilters(): Collection
    {
        $result = collect();

        foreach ($this->getEffectiveFilters() as $name => $filter) {
            if ($filter->getType() === 'passthrough') {
                $value = $this->resolveFilterValue($filter);

                if ($value !== null) {
                    $result[$name] = $filter->prepareValue($value);
                }
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

    protected function applyFiltersToSubject(): void
    {
        $filters = $this->getEffectiveFilters();
        $requestedFilterNames = $this->extractRequestedFilterNames();

        $this->validateFiltersLimit(count($requestedFilterNames));

        $allowedFilterNames = array_keys($filters);
        $allowedFilterNamesIndex = array_flip($allowedFilterNames);
        $prefixIndex = $this->buildPrefixIndex($allowedFilterNames);

        foreach ($requestedFilterNames as $filterName) {
            if (! $this->isValidFilterName($filterName, $allowedFilterNamesIndex, $prefixIndex)) {
                if (! $this->config->isInvalidFilterQueryExceptionDisabled()) {
                    throw InvalidFilterQuery::filtersNotAllowed(
                        collect([$filterName]),
                        collect($allowedFilterNames)
                    );
                }
            }
        }

        foreach ($filters as $filter) {
            $value = $this->resolveFilterValue($filter);

            if ($value === null) {
                continue;
            }

            $preparedValue = $filter->prepareValue($value);

            if ($preparedValue === null) {
                continue;
            }

            $this->applyFilter($filter, $preparedValue);
        }
    }

    /**
     * Resolve raw filter value from request/default according to filter presence rules.
     *
     * Priority: request value > filter->getDefault() > schema->defaultFilters()
     */
    protected function resolveFilterValue(FilterInterface $filter): mixed
    {
        $name = $filter->getName();
        $hasFilterInRequest = $this->getParametersManager()->hasFilter($name);

        if ($hasFilterInRequest) {
            $value = $this->getFilterValueFromRequest($name);

            if ($value === null && $this->config->shouldApplyFilterDefaultOnNull()) {
                return $this->getFilterDefault($filter);
            }

            return $value;
        }

        return $this->getFilterDefault($filter);
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
     */
    protected function applyFilter(FilterInterface $filter, mixed $preparedValue): void
    {
        $this->subject = $filter->apply($this->subject, $preparedValue);
    }

    /**
     * Apply sorts to the query subject.
     *
     * Validates requested sorts against allowed sorts, applies default sorts
     * if none requested, and enforces sort count limits.
     *
     * @throws InvalidSortQuery When requested sort is not allowed
     * @throws \Jackardios\QueryWizard\Exceptions\MaxSortsCountExceeded When sort count exceeds configured limit
     */
    protected function applySortsToSubject(): void
    {
        $sorts = $this->getEffectiveSorts();
        $requestedSorts = $this->getParametersManager()->getSorts();
        $defaultSorts = $this->getEffectiveDefaultSorts();

        $usingDefaults = $requestedSorts->isEmpty();
        $effectiveSorts = $usingDefaults
            ? collect($defaultSorts)->map(fn ($s) => new Sort($s))
            : $requestedSorts;

        if (empty($sorts) && $effectiveSorts->isNotEmpty()) {
            if ($usingDefaults) {
                foreach ($effectiveSorts as $sortValue) {
                    $sort = $this->normalizeStringToSort($sortValue->getField());
                    $this->subject = $sort->apply($this->subject, $sortValue->getDirection());
                }

                return;
            }

            if (! $this->config->isInvalidSortQueryExceptionDisabled()) {
                throw InvalidSortQuery::sortsNotAllowed(
                    $effectiveSorts->map(fn (Sort $s) => $s->getField()),
                    collect([])
                );
            }

            return;
        }

        if (empty($sorts)) {
            return;
        }

        $this->validateSortsLimit($effectiveSorts->count());

        $sortsIndex = [];
        foreach ($sorts as $sort) {
            $name = $sort->getName();
            $normalizedName = ltrim($name, '-');
            $sortsIndex[$normalizedName] = $sort;
        }

        $allowedSortNames = array_keys($sortsIndex);
        $appliedSorts = [];

        foreach ($effectiveSorts as $sortValue) {
            /** @var Sort $sortValue */
            $field = $sortValue->getField();

            if (! isset($sortsIndex[$field])) {
                if ($usingDefaults) {
                    continue;
                }

                if (! $this->config->isInvalidSortQueryExceptionDisabled()) {
                    throw InvalidSortQuery::sortsNotAllowed(collect([$field]), collect($allowedSortNames));
                }

                continue;
            }

            if (isset($appliedSorts[$field])) {
                continue;
            }
            $appliedSorts[$field] = true;

            $sort = $sortsIndex[$field];
            $this->subject = $sort->apply($this->subject, $sortValue->getDirection());
        }
    }

    protected function applyIncludesToSubject(): void
    {
        $includes = $this->getEffectiveIncludes();
        $requestedIncludes = $this->getMergedRequestedIncludes();
        $usingDefaults = $this->isIncludesRequestEmpty();

        $this->validateIncludesLimit(count($requestedIncludes));

        if (empty($includes) && ! empty($requestedIncludes)) {
            $defaults = $usingDefaults ? $this->getEffectiveDefaultIncludes() : [];
            $defaultsIndex = array_flip($defaults);
            $userOnlyIncludes = array_filter(
                $requestedIncludes,
                fn ($name) => ! isset($defaultsIndex[$name])
            );

            if (! empty($userOnlyIncludes) && ! $this->config->isInvalidIncludeQueryExceptionDisabled()) {
                throw InvalidIncludeQuery::includesNotAllowed(
                    collect($userOnlyIncludes),
                    collect([])
                );
            }

            return;
        }

        if (empty($includes)) {
            return;
        }

        $includesIndex = $this->buildIncludesIndex($includes);

        $defaults = $usingDefaults ? $this->getEffectiveDefaultIncludes() : [];
        $defaultsIndex = array_flip($defaults);

        $allowedIncludeNames = array_keys($includesIndex);
        $validRequestedIncludes = [];
        foreach ($requestedIncludes as $includeName) {
            if (! isset($includesIndex[$includeName])) {
                if (isset($defaultsIndex[$includeName])) {
                    continue;
                }

                if (! $this->config->isInvalidIncludeQueryExceptionDisabled()) {
                    throw InvalidIncludeQuery::includesNotAllowed(
                        collect([$includeName]),
                        collect($allowedIncludeNames)
                    );
                }

                continue;
            }

            $include = $includesIndex[$includeName];

            $this->validateIncludeDepth($include);
            $validRequestedIncludes[] = $includeName;
        }

        $this->applyValidatedIncludes($validRequestedIncludes, $includesIndex);
    }

    /**
     * Apply validated includes to the subject.
     *
     * Override this method to customize how includes are applied.
     *
     * @param  array<int, string>  $validRequestedIncludes
     * @param  array<string, IncludeInterface>  $includesIndex
     */
    protected function applyValidatedIncludes(array $validRequestedIncludes, array $includesIndex): void
    {
        foreach ($validRequestedIncludes as $includeName) {
            $include = $includesIndex[$includeName];
            $this->subject = $include->apply($this->subject);
        }
    }

    protected function applyFieldsToSubject(): void
    {
        $validFields = $this->resolveValidatedRootFields();

        if ($validFields !== null) {
            $this->applyFields($validFields);
        }
    }

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

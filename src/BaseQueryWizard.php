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
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\Exceptions\MaxSortsCountExceeded;
use Jackardios\QueryWizard\Filters\AbstractFilter;
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

    private bool $building = false;

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
     *
     * @api
     */
    protected function invalidateBuild(): void
    {
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
     * Set allowed filters.
     *
     * Empty array means all filters are forbidden.
     * Not calling this method falls back to schema filters (if any).
     *
     * @param  FilterInterface|string|array<FilterInterface|string>  ...$filters
     */
    public function allowedFilters(FilterInterface|string|array ...$filters): static
    {
        $this->invalidateBuild();
        $this->allowedFilters = $this->flattenDefinitions($filters);
        $this->allowedFiltersExplicitlySet = true;

        return $this;
    }

    /**
     * Set disallowed filters (to override schema).
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedFilters(string|array ...$names): static
    {
        $this->invalidateBuild();
        $this->disallowedFilters = $this->flattenStringArray($names);

        return $this;
    }

    /**
     * Set allowed sorts.
     *
     * @param  SortInterface|string|array<SortInterface|string>  ...$sorts
     */
    public function allowedSorts(SortInterface|string|array ...$sorts): static
    {
        $this->invalidateBuild();
        $this->allowedSorts = $this->flattenDefinitions($sorts);
        $this->allowedSortsExplicitlySet = true;

        return $this;
    }

    /**
     * Set disallowed sorts (to override schema).
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedSorts(string|array ...$names): static
    {
        $this->invalidateBuild();
        $this->disallowedSorts = $this->flattenStringArray($names);

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

            foreach ($filters as ['filter' => $filter, 'value' => $value]) {
                if ($filter->getType() !== 'passthrough') {
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
     * @return Collection<string, mixed>
     */
    public function getPassthroughFilters(): Collection
    {
        $result = collect();

        foreach ($this->resolvePreparedFilters() as $name => $resolvedFilter) {
            if ($resolvedFilter['filter']->getType() === 'passthrough') {
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

        return $resolvedFilters;
    }

    /**
     * Resolve, validate and prepare a single filter value.
     *
     * Raw value -> incoming shape validation -> prepareValue() -> prepared shape
     * validation. Returns null when the filter must be skipped.
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

        $this->validateIncomingFilterValueShape($filter, $value);

        $preparedValue = $filter->prepareValue($value);

        if ($preparedValue === null) {
            return null;
        }

        $this->validatePreparedFilterValueShape($filter, $preparedValue);

        return $preparedValue;
    }

    /**
     * Resolve raw filter value from request/default according to filter presence rules.
     *
     * Priority: request value > filter->getDefault() > schema->defaultFilters()
     *
     * A blank value (see FilterValueParser::isBlank()) is absent: null is returned,
     * or the default when `apply_filter_default_on_null` is enabled.
     */
    protected function resolveFilterValue(FilterInterface $filter): mixed
    {
        $name = $this->normalizePublicPath($filter->getName());
        $splitValues = ! $filter instanceof AbstractFilter || $filter->shouldSplitValues();
        [$inRequest, $value] = $this->getOwnFilterValueFromRequest($name, $splitValues);

        if ($inRequest) {
            if (! FilterValueParser::isBlank($value)) {
                return $value;
            }

            if (! $this->getConfig()->shouldApplyFilterDefaultOnNull()) {
                return null;
            }
        }

        $default = $this->getFilterDefault($filter);

        return FilterValueParser::isBlank($default) ? null : $default;
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
            if (! isset($allowedFilterNamesIndex[$filterName]) && ! $this->getConfig()->isInvalidFilterQueryExceptionDisabled()) {
                throw InvalidFilterQuery::filtersNotAllowed(
                    collect([$filterName]),
                    collect($allowedFilterNames)
                );
            }
        }
    }

    protected function validateIncomingFilterValueShape(FilterInterface $filter, mixed $value): void
    {
        if (! $filter instanceof AbstractFilter) {
            return;
        }

        $details = $filter->validateIncomingValueShape($value);

        if ($details !== null) {
            throw InvalidFilterQuery::invalidFormat($details);
        }
    }

    protected function validatePreparedFilterValueShape(FilterInterface $filter, mixed $value): void
    {
        if (! $filter instanceof AbstractFilter) {
            return;
        }

        $details = $filter->validatePreparedValueShape($value);

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
            if (! $this->getConfig()->isInvalidSortQueryExceptionDisabled()) {
                $parameter = $this->getConfig()->getSortsParameterName() ?: 'sort';

                throw InvalidSortQuery::invalidFormat(
                    "The `{$parameter}` parameter must contain at least one sort field when present."
                );
            }

            $sortRequested = false;
        }

        $usingDefaults = ! $sortRequested;
        $effectiveSorts = $usingDefaults
            ? collect($defaultSorts)->map(fn ($s) => new Sort($s))
            : $requestedSorts;

        if (empty($sorts) && $effectiveSorts->isNotEmpty()) {
            if ($usingDefaults) {
                if ($this->allowedSortsExplicitlySet || $this->disallowedSorts !== []) {
                    return [];
                }

                $resolved = [];
                foreach ($effectiveSorts as $sortValue) {
                    $resolved[] = [$this->normalizeStringToSort($sortValue->getField()), $sortValue->getDirection()];
                }

                $this->assertDefaultSortsWithinLimit(count($resolved));

                return $resolved;
            }

            if (! $this->getConfig()->isInvalidSortQueryExceptionDisabled()) {
                throw InvalidSortQuery::sortsNotAllowed(
                    $effectiveSorts->map(fn (Sort $s) => $s->getField()),
                    collect([])
                );
            }

            return [];
        }

        if (empty($sorts)) {
            return [];
        }

        if (! $usingDefaults) {
            $this->validateSortsLimit($effectiveSorts->count());
        }

        $sortsIndex = [];
        foreach ($sorts as $sort) {
            $name = $this->normalizePublicPath($sort->getName());
            $normalizedName = ltrim($name, '-');
            $sortsIndex[$normalizedName] = $sort;
        }

        $allowedSortNames = array_keys($sortsIndex);
        $appliedSorts = [];
        $resolved = [];

        foreach ($effectiveSorts as $sortValue) {
            /** @var Sort $sortValue */
            $field = $sortValue->getField();

            if (! isset($sortsIndex[$field])) {
                if ($usingDefaults) {
                    continue;
                }

                if (! $this->getConfig()->isInvalidSortQueryExceptionDisabled()) {
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

        if ($usingDefaults) {
            $this->assertDefaultSortsWithinLimit(count($resolved));
        }

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

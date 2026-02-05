<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Concerns\HandlesConfiguration;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\Exceptions\MaxFiltersCountExceeded;
use Jackardios\QueryWizard\Exceptions\MaxIncludeDepthExceeded;
use Jackardios\QueryWizard\Exceptions\MaxIncludesCountExceeded;
use Jackardios\QueryWizard\Exceptions\MaxSortsCountExceeded;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;
use Jackardios\QueryWizard\Values\Sort;

/**
 * Abstract base class for query wizards.
 *
 * Provides common configuration API and query building logic.
 * Subclasses implement filter/sort/include application and query execution.
 *
 * @phpstan-consistent-constructor
 */
abstract class BaseQueryWizard implements QueryWizardInterface
{
    use HandlesConfiguration;

    protected mixed $subject;
    protected QueryParametersManager $parameters;
    protected QueryWizardConfig $config;
    protected ?ResourceSchemaInterface $schema = null;

    // Configuration (instance level)
    /** @var array<FilterInterface|string> */
    protected array $allowedFilters = [];
    /** @var array<string> */
    protected array $disallowedFilters = [];
    /** @var array<SortInterface|string> */
    protected array $allowedSorts = [];
    protected bool $allowedSortsExplicitlySet = false;
    /** @var array<string> */
    protected array $disallowedSorts = [];
    /** @var array<string> */
    protected array $defaultSorts = [];
    /** @var array<IncludeInterface|string> */
    protected array $allowedIncludes = [];
    protected bool $allowedIncludesExplicitlySet = false;
    /** @var array<string> */
    protected array $disallowedIncludes = [];
    /** @var array<string> */
    protected array $defaultIncludes = [];
    /** @var array<string> */
    protected array $allowedFields = [];
    /** @var array<string> */
    protected array $disallowedFields = [];
    /** @var array<string> */
    protected array $allowedAppends = [];
    protected bool $allowedAppendsExplicitlySet = false;
    /** @var array<string> */
    protected array $disallowedAppends = [];
    /** @var array<string> */
    protected array $defaultAppends = [];
    /** @var array<callable> */
    protected array $tapCallbacks = [];

    // Build state
    protected bool $built = false;

    /**
     * Invalidate the build state when configuration changes.
     *
     * This ensures that calling build() after configuration changes
     * will re-apply all filters, sorts, includes, and fields.
     */
    protected function invalidateBuild(): void
    {
        $this->built = false;
    }

    // === ABSTRACT: Subclass MUST implement ===

    /**
     * Normalize a string filter to a FilterInterface instance.
     */
    abstract protected function normalizeStringToFilter(string $name): FilterInterface;

    /**
     * Normalize a string sort to a SortInterface instance.
     */
    abstract protected function normalizeStringToSort(string $name): SortInterface;

    /**
     * Normalize a string include to an IncludeInterface instance.
     */
    abstract protected function normalizeStringToInclude(string $name): IncludeInterface;

    /**
     * Apply field selection to subject.
     *
     * @param array<string> $fields
     */
    abstract protected function applyFields(array $fields): void;

    /**
     * Get the resource key for sparse fieldsets.
     */
    abstract public function getResourceKey(): string;

    /**
     * Get the configuration instance.
     */
    protected function getConfig(): QueryWizardConfig
    {
        return $this->config;
    }

    // === Configuration API ===

    /**
     * Set allowed filters.
     *
     * @param FilterInterface|string|array<FilterInterface|string> ...$filters
     */
    public function allowedFilters(FilterInterface|string|array ...$filters): static
    {
        $this->allowedFilters = $this->flattenDefinitions($filters);
        $this->invalidateBuild();
        return $this;
    }

    /**
     * Set disallowed filters (to override schema).
     *
     * @param string|array<string> ...$names
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
     * @param SortInterface|string|array<SortInterface|string> ...$sorts
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
     * @param string|array<string> ...$names
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
     * @param string|Sort|array<string|Sort> ...$sorts
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
     * @param IncludeInterface|string|array<IncludeInterface|string> ...$includes
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
     * @param string|array<string> ...$names
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
     * @param string|array<string> ...$names
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
     * @param string|array<string> ...$fields
     */
    public function allowedFields(string|array ...$fields): static
    {
        $this->allowedFields = $this->flattenStringArray($fields);
        $this->invalidateBuild();
        return $this;
    }

    /**
     * Set disallowed fields (to override schema).
     *
     * @param string|array<string> ...$names
     */
    public function disallowedFields(string|array ...$names): static
    {
        $this->disallowedFields = $this->flattenStringArray($names);
        $this->invalidateBuild();
        return $this;
    }

    /**
     * Set allowed appends.
     *
     * @param string|array<string> ...$appends
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
     * @param string|array<string> ...$names
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
     * @param string|array<string> ...$appends
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
     * @param callable(mixed): void $callback
     */
    public function tap(callable $callback): static
    {
        $this->tapCallbacks[] = $callback;
        $this->invalidateBuild();
        return $this;
    }

    // === Build API ===

    /**
     * Build the query (apply filters, sorts, includes, fields).
     *
     * Returns the underlying subject (e.g., Eloquent Builder) for execution.
     */
    public function build(): mixed
    {
        if ($this->built) {
            return $this->subject;
        }

        $this->applyTapCallbacks();
        $this->applyFiltersToSubject();
        $this->applySortsToSubject();
        $this->applyIncludesToSubject();
        $this->applyFieldsToSubject();

        $this->built = true;

        return $this->subject;
    }

    /**
     * Get the underlying subject without building.
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
                $value = $this->getFilterValueFromRequest($name) ?? $filter->getDefault();

                if ($value !== null) {
                    $result[$name] = $filter->prepareValue($value);
                }
            }
        }

        return $result;
    }

    /**
     * Apply appends to a collection of results.
     *
     * Call this after executing the query to apply allowed appends.
     *
     * @template T of \Traversable<mixed>|array<mixed>
     * @param T $results
     * @return T
     */
    public function applyAppendsTo(mixed $results): mixed
    {
        $appends = $this->getValidRequestedAppends();
        if (empty($appends)) {
            return $results;
        }

        foreach ($results as $item) {
            if (is_object($item) && method_exists($item, 'append')) {
                $item->append($appends);
            }
        }

        return $results;
    }

    // === Protected: Build Logic ===

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

        // Validate filter count limit
        $this->validateFiltersLimit(count($requestedFilterNames));

        // Build allowed filter names index for validation
        $allowedFilterNames = array_keys($filters);
        $allowedFilterNamesIndex = array_flip($allowedFilterNames);

        // Build prefix index for dot notation support
        $prefixIndex = $this->buildPrefixIndex($allowedFilterNames);

        // Validate requested filters
        foreach ($requestedFilterNames as $filterName) {
            if (!$this->isValidFilterName($filterName, $allowedFilterNamesIndex, $prefixIndex)) {
                if (!$this->config->isInvalidFilterQueryExceptionDisabled()) {
                    throw InvalidFilterQuery::filtersNotAllowed(
                        collect([$filterName]),
                        collect($allowedFilterNames)
                    );
                }
            }
        }

        // Apply filters
        foreach ($filters as $filter) {
            $name = $filter->getName();
            $value = $this->getFilterValueFromRequest($name) ?? $filter->getDefault();

            if ($value === null) {
                continue;
            }

            $preparedValue = $filter->prepareValue($value);
            $this->subject = $filter->apply($this->subject, $preparedValue);
        }
    }

    protected function applySortsToSubject(): void
    {
        $sorts = $this->getEffectiveSorts();
        $requestedSorts = $this->parameters->getSorts();
        $defaultSorts = $this->getEffectiveDefaultSorts();

        $effectiveSorts = $requestedSorts->isEmpty()
            ? collect($defaultSorts)->map(fn($s) => new Sort($s))
            : $requestedSorts;

        // If no allowed sorts but some are requested, throw exception
        // when allowedSorts was explicitly set to empty
        if (empty($sorts) && $this->allowedSortsExplicitlySet && $effectiveSorts->isNotEmpty()) {
            if (!$this->config->isInvalidSortQueryExceptionDisabled()) {
                throw InvalidSortQuery::sortsNotAllowed(
                    $effectiveSorts->map(fn(Sort $s) => $s->getField()),
                    collect([])
                );
            }
            return;
        }

        if (empty($sorts)) {
            return;
        }

        // Validate sort count limit
        $this->validateSortsLimit($effectiveSorts->count());

        // Build sorts index
        $sortsIndex = [];
        foreach ($sorts as $sort) {
            $name = $sort->getName();
            $normalizedName = ltrim($name, '-');
            $sortsIndex[$normalizedName] = $sort;
        }

        // Validate and apply sorts
        $allowedSortNames = array_keys($sortsIndex);
        $appliedFields = [];

        foreach ($effectiveSorts as $sortValue) {
            /** @var Sort $sortValue */
            $field = $sortValue->getField();

            if (!isset($sortsIndex[$field])) {
                if (!$this->config->isInvalidSortQueryExceptionDisabled()) {
                    throw InvalidSortQuery::sortsNotAllowed(collect([$field]), collect($allowedSortNames));
                }
                continue;
            }

            // Skip duplicate sorts
            if (isset($appliedFields[$field])) {
                continue;
            }
            $appliedFields[$field] = true;

            $sort = $sortsIndex[$field];
            $this->subject = $sort->apply($this->subject, $sortValue->getDirection());
        }
    }

    protected function applyIncludesToSubject(): void
    {
        $includes = $this->getEffectiveIncludes();
        $requestedIncludes = $this->getMergedRequestedIncludes();

        // Validate include limits
        $this->validateIncludesLimit(count($requestedIncludes));

        // If no allowed includes but some are requested, throw exception
        // when allowedIncludes was explicitly set to empty
        if (empty($includes) && $this->allowedIncludesExplicitlySet && !empty($requestedIncludes)) {
            if (!$this->config->isInvalidIncludeQueryExceptionDisabled()) {
                throw InvalidIncludeQuery::includesNotAllowed(
                    collect($requestedIncludes),
                    collect([])
                );
            }
            return;
        }

        if (empty($includes)) {
            return;
        }

        // Build includes index
        $includesIndex = [];
        foreach ($includes as $include) {
            $includesIndex[$include->getName()] = $include;
        }

        // Also add implicit count includes for relationships
        $countSuffix = $this->config->getCountSuffix();
        foreach ($includes as $include) {
            if ($include->getType() === 'relationship') {
                $countName = $include->getRelation() . $countSuffix;
                if (!isset($includesIndex[$countName])) {
                    $countInclude = $this->normalizeStringToInclude($countName);
                    $includesIndex[$countName] = $countInclude;
                }
            }
        }

        // Validate requested includes
        $allowedIncludeNames = array_keys($includesIndex);
        $validRequestedIncludes = [];
        foreach ($requestedIncludes as $includeName) {
            if (!isset($includesIndex[$includeName])) {
                if (!$this->config->isInvalidIncludeQueryExceptionDisabled()) {
                    throw InvalidIncludeQuery::includesNotAllowed(
                        collect([$includeName]),
                        collect($allowedIncludeNames)
                    );
                }
                continue;
            }

            $include = $includesIndex[$includeName];

            // Validate depth based on relation (not alias) to prevent bypass
            $this->validateIncludeDepth($include);
            $validRequestedIncludes[] = $includeName;
        }

        // Apply includes
        foreach ($validRequestedIncludes as $includeName) {
            $include = $includesIndex[$includeName];
            $this->subject = $include->apply($this->subject);
        }
    }

    protected function applyFieldsToSubject(): void
    {
        $allowedFields = $this->getEffectiveFields();
        $requestedFields = $this->parameters->getFields();
        $resourceKey = $this->getResourceKey();

        if (empty($allowedFields) || in_array('*', $allowedFields, true)) {
            // If no specific allowed fields or wildcard, apply whatever was requested
            $fields = $requestedFields->get($resourceKey, []);
            if (!empty($fields) && !in_array('*', $fields, true)) {
                $this->applyFields($fields);
            }
            return;
        }

        // Get requested fields for this resource
        $fields = $requestedFields->get($resourceKey, []);
        if (empty($fields) || in_array('*', $fields, true)) {
            return;
        }

        // Validate requested fields
        $invalidFields = array_diff($fields, $allowedFields);
        if (!empty($invalidFields)) {
            if (!$this->config->isInvalidFieldQueryExceptionDisabled()) {
                throw \Jackardios\QueryWizard\Exceptions\InvalidFieldQuery::fieldsNotAllowed(
                    collect($invalidFields),
                    collect($allowedFields)
                );
            }
            // Filter out invalid fields and continue
            $fields = array_intersect($fields, $allowedFields);
        }

        $this->applyFields($fields);
    }

    // === Protected: Resolution ===

    protected function getFilterValueFromRequest(string $name): mixed
    {
        return $this->parameters->getFilterValue($name);
    }

    /**
     * Get effective filters.
     *
     * @return array<string, FilterInterface>
     */
    protected function getEffectiveFilters(): array
    {
        $filters = !empty($this->allowedFilters)
            ? $this->allowedFilters
            : ($this->schema?->filters($this) ?? []);

        $filters = $this->normalizeFilters($filters);
        $filters = $this->removeDisallowedFilters(array_values($filters));

        // Re-index by name
        $result = [];
        foreach ($filters as $filter) {
            $result[$filter->getName()] = $filter;
        }
        return $result;
    }

    /**
     * Get effective sorts.
     *
     * @return array<string, SortInterface>
     */
    protected function getEffectiveSorts(): array
    {
        $sorts = !empty($this->allowedSorts)
            ? $this->allowedSorts
            : ($this->schema?->sorts($this) ?? []);

        $sorts = $this->normalizeSorts($sorts);
        $sorts = $this->removeDisallowedSorts(array_values($sorts));

        // Re-index by name
        $result = [];
        foreach ($sorts as $sort) {
            $result[$sort->getName()] = $sort;
        }
        return $result;
    }

    /**
     * Get effective default sorts.
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultSorts(): array
    {
        return !empty($this->defaultSorts)
            ? $this->defaultSorts
            : ($this->schema?->defaultSorts($this) ?? []);
    }

    /**
     * Get effective includes.
     *
     * @return array<IncludeInterface>
     */
    protected function getEffectiveIncludes(): array
    {
        $includes = !empty($this->allowedIncludes)
            ? $this->allowedIncludes
            : ($this->schema?->includes($this) ?? []);

        $includes = $this->normalizeIncludes($includes);
        return $this->removeDisallowedIncludesFromArray($includes, $this->disallowedIncludes);
    }

    /**
     * Get effective default includes.
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultIncludes(): array
    {
        return !empty($this->defaultIncludes)
            ? $this->defaultIncludes
            : ($this->schema?->defaultIncludes($this) ?? []);
    }

    /**
     * Get merged requested includes (defaults + request).
     *
     * @return array<string>
     */
    protected function getMergedRequestedIncludes(): array
    {
        $defaults = $this->getEffectiveDefaultIncludes();
        $requested = $this->parameters->getIncludes()->all();
        return array_unique(array_merge($defaults, $requested));
    }

    /**
     * Get effective fields.
     *
     * @return array<string>
     */
    protected function getEffectiveFields(): array
    {
        $fields = !empty($this->allowedFields)
            ? $this->allowedFields
            : ($this->schema?->fields($this) ?? ['*']);

        return $this->removeDisallowedStrings($fields, $this->disallowedFields);
    }

    /**
     * Get effective appends.
     *
     * @return array<string>
     */
    protected function getEffectiveAppends(): array
    {
        $appends = !empty($this->allowedAppends)
            ? $this->allowedAppends
            : ($this->schema?->appends($this) ?? []);

        return $this->removeDisallowedStrings($appends, $this->disallowedAppends);
    }

    /**
     * Get effective default appends.
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultAppends(): array
    {
        return !empty($this->defaultAppends)
            ? $this->defaultAppends
            : ($this->schema?->defaultAppends($this) ?? []);
    }

    /**
     * Get valid requested appends.
     *
     * @return array<string>
     */
    protected function getValidRequestedAppends(): array
    {
        $allowed = $this->getEffectiveAppends();
        $requested = $this->parameters->getAppends()->all();
        $defaults = $this->getEffectiveDefaultAppends();

        // Validate requested appends when allowedAppends was explicitly set
        if ($this->allowedAppendsExplicitlySet && !empty($requested)) {
            $invalidAppends = array_diff($requested, $allowed);
            if (!empty($invalidAppends) && !$this->config->isInvalidAppendQueryExceptionDisabled()) {
                throw \Jackardios\QueryWizard\Exceptions\InvalidAppendQuery::appendsNotAllowed(
                    collect($invalidAppends),
                    collect($allowed)
                );
            }
        }

        $validRequested = array_intersect($requested, $allowed);

        return array_unique(array_merge($defaults, $validRequested));
    }

    // === Protected: Normalization ===

    /**
     * Normalize filters to FilterInterface instances.
     *
     * @param array<FilterInterface|string> $filters
     * @return array<string, FilterInterface>
     */
    protected function normalizeFilters(array $filters): array
    {
        $result = [];
        foreach ($filters as $filter) {
            if (is_string($filter)) {
                $filter = $this->normalizeStringToFilter($filter);
            }
            $result[$filter->getName()] = $filter;
        }
        return $result;
    }

    /**
     * Normalize sorts to SortInterface instances.
     *
     * @param array<SortInterface|string> $sorts
     * @return array<string, SortInterface>
     */
    protected function normalizeSorts(array $sorts): array
    {
        $result = [];
        foreach ($sorts as $sort) {
            if (is_string($sort)) {
                $sort = $this->normalizeStringToSort($sort);
            }
            $result[$sort->getName()] = $sort;
        }
        return $result;
    }

    /**
     * Normalize includes to IncludeInterface instances.
     *
     * @param array<IncludeInterface|string> $includes
     * @return array<IncludeInterface>
     */
    protected function normalizeIncludes(array $includes): array
    {
        $countSuffix = $this->config->getCountSuffix();

        $result = [];
        foreach ($includes as $include) {
            if (is_string($include)) {
                $include = $this->normalizeStringToInclude($include);
            }

            // For count includes without alias, auto-apply count suffix
            if ($include->getType() === 'count' && $include->getAlias() === null) {
                $include = $include->alias($include->getRelation() . $countSuffix);
            }

            $result[] = $include;
        }
        return $result;
    }

    // === Protected: Filtering disallowed ===

    /**
     * Remove disallowed filters.
     *
     * @param array<FilterInterface> $filters
     * @return array<FilterInterface>
     */
    protected function removeDisallowedFilters(array $filters): array
    {
        return $this->removeDisallowedByName(
            $filters,
            $this->disallowedFilters,
            static fn(FilterInterface $f) => $f->getName()
        );
    }

    /**
     * Remove disallowed sorts.
     *
     * @param array<SortInterface> $sorts
     * @return array<SortInterface>
     */
    protected function removeDisallowedSorts(array $sorts): array
    {
        return $this->removeDisallowedByName(
            $sorts,
            $this->disallowedSorts,
            static fn(SortInterface $s) => $s->getName()
        );
    }

    /**
     * Remove disallowed includes.
     *
     * @param array<IncludeInterface> $includes
     * @param array<string> $disallowedIncludes
     * @return array<IncludeInterface>
     */
    protected function removeDisallowedIncludesFromArray(array $includes, array $disallowedIncludes): array
    {
        return $this->removeDisallowedByName(
            $includes,
            $disallowedIncludes,
            static fn(IncludeInterface $i) => $i->getName(),
            $this->config->getCountSuffix()
        );
    }

    // === Protected: Validation ===

    /**
     * Extract all requested filter names from request.
     *
     * @return array<string>
     */
    protected function extractRequestedFilterNames(): array
    {
        $maxFilterDepth = $this->config->getMaxFilterDepth();
        $filters = $this->getEffectiveFilters();
        $allowedFilterNamesIndex = array_flip(array_keys($filters));

        return $this->extractAllRequestedFilterNames(
            $this->parameters->getFilters()->all(),
            '',
            $allowedFilterNamesIndex,
            $maxFilterDepth
        );
    }

    /**
     * Extract all possible filter names from a nested request structure.
     *
     * @param array<string, mixed> $filters
     * @param array<string, int> $allowedFilterNamesIndex
     * @return array<string>
     */
    protected function extractAllRequestedFilterNames(
        array $filters,
        string $prefix = '',
        array $allowedFilterNamesIndex = [],
        ?int $maxDepth = null,
        int $currentDepth = 1
    ): array {
        $names = [];

        foreach ($filters as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            $names[] = $fullKey;

            if (isset($allowedFilterNamesIndex[$fullKey])) {
                continue;
            }

            $canRecurse = $maxDepth === null || $currentDepth < $maxDepth;
            if ($canRecurse && is_array($value) && !empty($value) && $this->isAssociativeArray($value)) {
                $names = array_merge(
                    $names,
                    $this->extractAllRequestedFilterNames(
                        $value,
                        $fullKey,
                        $allowedFilterNamesIndex,
                        $maxDepth,
                        $currentDepth + 1
                    )
                );
            }
        }

        return $names;
    }

    /**
     * Build prefix index for dot notation support.
     *
     * @param array<string> $allowedFilterNames
     * @return array<string, bool>
     */
    protected function buildPrefixIndex(array $allowedFilterNames): array
    {
        $prefixIndex = [];
        foreach ($allowedFilterNames as $name) {
            $parts = explode('.', $name);
            $prefix = '';
            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    break;
                }
                $prefix = $prefix === '' ? $part : $prefix . '.' . $part;
                $prefixIndex[$prefix] = true;
            }
        }
        return $prefixIndex;
    }

    /**
     * Check if a filter name is valid.
     *
     * @param array<string, int> $allowedFilterNamesIndex
     * @param array<string, bool> $prefixIndex
     */
    protected function isValidFilterName(string $filterName, array $allowedFilterNamesIndex, array $prefixIndex): bool
    {
        if (isset($allowedFilterNamesIndex[$filterName])) {
            return true;
        }

        return isset($prefixIndex[$filterName]);
    }

    protected function validateFiltersLimit(int $count): void
    {
        $limit = $this->config->getMaxFiltersCount();
        if ($limit !== null && $count > $limit) {
            throw MaxFiltersCountExceeded::create($count, $limit);
        }
    }

    protected function validateSortsLimit(int $count): void
    {
        $limit = $this->config->getMaxSortsCount();
        if ($limit !== null && $count > $limit) {
            throw MaxSortsCountExceeded::create($count, $limit);
        }
    }

    protected function validateIncludesLimit(int $count): void
    {
        $limit = $this->config->getMaxIncludesCount();
        if ($limit !== null && $count > $limit) {
            throw MaxIncludesCountExceeded::create($count, $limit);
        }
    }

    /**
     * Validate include depth based on relation name (not alias).
     *
     * This prevents bypassing depth limits by using a simple alias
     * for a deeply nested relation.
     */
    protected function validateIncludeDepth(IncludeInterface $include): void
    {
        $relation = $include->getRelation();
        $depth = substr_count($relation, '.') + 1;
        $limit = $this->config->getMaxIncludeDepth();
        if ($limit !== null && $depth > $limit) {
            throw MaxIncludeDepthExceeded::create($include->getName(), $depth, $limit);
        }
    }

    // === Protected: Helper Methods ===

    /**
     * Extract sort name from Sort object or string.
     */
    protected function extractSortName(string|Sort $sort): string
    {
        if ($sort instanceof Sort) {
            $prefix = $sort->getDirection() === 'desc' ? '-' : '';
            return $prefix . $sort->getField();
        }
        return $sort;
    }

    /**
     * Clone the wizard.
     */
    public function __clone(): void
    {
        if (is_object($this->subject)) {
            $this->subject = clone $this->subject;
        }
        $this->built = false;
    }
}

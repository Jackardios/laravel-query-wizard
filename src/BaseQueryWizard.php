<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Concerns\HandlesAppends;
use Jackardios\QueryWizard\Concerns\HandlesConfiguration;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;
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
 */
abstract class BaseQueryWizard implements QueryWizardInterface
{
    use HandlesAppends;
    use HandlesConfiguration;
    use HandlesFields;
    use HandlesIncludes;

    protected mixed $subject;

    protected QueryParametersManager $parameters;

    protected QueryWizardConfig $config;

    protected ?ResourceSchemaInterface $schema = null;

    /** @var array<FilterInterface|string> */
    protected array $allowedFilters = [];

    protected bool $allowedFiltersExplicitlySet = false;

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

    protected bool $allowedFieldsExplicitlySet = false;

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
     * @param  array<string>  $fields
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

    /**
     * Get the parameters manager.
     */
    protected function getParametersManager(): QueryParametersManager
    {
        return $this->parameters;
    }

    /**
     * Get the schema instance.
     */
    protected function getSchema(): ?ResourceSchemaInterface
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
     * @param  callable(mixed): void  $callback
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
            $name = $filter->getName();
            $value = $this->getFilterValueFromRequest($name) ?? $filter->getDefault();

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
     * @throws MaxSortsCountExceeded When sort count exceeds configured limit
     */
    protected function applySortsToSubject(): void
    {
        $sorts = $this->getEffectiveSorts();
        $requestedSorts = $this->parameters->getSorts();
        $defaultSorts = $this->getEffectiveDefaultSorts();

        $effectiveSorts = $requestedSorts->isEmpty()
            ? collect($defaultSorts)->map(fn ($s) => new Sort($s))
            : $requestedSorts;

        if (empty($sorts) && $effectiveSorts->isNotEmpty()) {
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
        $appliedFields = [];

        foreach ($effectiveSorts as $sortValue) {
            /** @var Sort $sortValue */
            $field = $sortValue->getField();

            if (! isset($sortsIndex[$field])) {
                if (! $this->config->isInvalidSortQueryExceptionDisabled()) {
                    throw InvalidSortQuery::sortsNotAllowed(collect([$field]), collect($allowedSortNames));
                }

                continue;
            }

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

        $this->validateIncludesLimit(count($requestedIncludes));

        if (empty($includes) && ! empty($requestedIncludes)) {
            if (! $this->config->isInvalidIncludeQueryExceptionDisabled()) {
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

        $includesIndex = $this->buildIncludesIndex($includes);

        $allowedIncludeNames = array_keys($includesIndex);
        $validRequestedIncludes = [];
        foreach ($requestedIncludes as $includeName) {
            if (! isset($includesIndex[$includeName])) {
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
        $allowedFields = $this->getEffectiveFields();
        $requestedFields = $this->parameters->getFields();
        $resourceKey = $this->getResourceKey();

        $fields = $requestedFields->get($resourceKey, []);

        if (in_array('*', $allowedFields, true)) {
            if (! empty($fields) && ! in_array('*', $fields, true)) {
                $this->applyFields($fields);
            }

            return;
        }

        if (empty($allowedFields)) {
            if (! empty($fields) && ! in_array('*', $fields, true)) {
                if (! $this->config->isInvalidFieldQueryExceptionDisabled()) {
                    throw \Jackardios\QueryWizard\Exceptions\InvalidFieldQuery::fieldsNotAllowed(
                        collect($fields),
                        collect([])
                    );
                }
            }

            return;
        }

        if (empty($fields) || in_array('*', $fields, true)) {
            return;
        }

        $invalidFields = array_diff($fields, $allowedFields);
        if (! empty($invalidFields)) {
            if (! $this->config->isInvalidFieldQueryExceptionDisabled()) {
                throw \Jackardios\QueryWizard\Exceptions\InvalidFieldQuery::fieldsNotAllowed(
                    collect($invalidFields),
                    collect($allowedFields)
                );
            }
            $fields = array_intersect($fields, $allowedFields);
        }

        if (! empty($fields)) {
            $this->applyFields($fields);
        }
    }

    protected function getFilterValueFromRequest(string $name): mixed
    {
        return $this->parameters->getFilterValue($name);
    }

    /**
     * Get effective filters.
     *
     * If allowedFilters() was called explicitly, use those (even if empty).
     * Otherwise, fall back to schema filters (if any).
     * Empty result means all filters are forbidden.
     *
     * @return array<string, FilterInterface>
     */
    protected function getEffectiveFilters(): array
    {
        $filters = $this->allowedFiltersExplicitlySet
            ? $this->allowedFilters
            : ($this->schema?->filters($this) ?? []);

        $disallowed = $this->disallowedFilters;
        $result = [];

        foreach ($filters as $filter) {
            if (is_string($filter)) {
                $filter = $this->normalizeStringToFilter($filter);
            }
            $name = $filter->getName();

            if (! empty($disallowed) && $this->isNameDisallowed($name, $disallowed)) {
                continue;
            }

            $result[$name] = $filter;
        }

        return $result;
    }

    /**
     * Get effective sorts.
     *
     * If allowedSorts() was called explicitly, use those (even if empty).
     * Otherwise, fall back to schema sorts (if any).
     * Empty result means all sorts are forbidden.
     *
     * @return array<string, SortInterface>
     */
    protected function getEffectiveSorts(): array
    {
        $sorts = $this->allowedSortsExplicitlySet
            ? $this->allowedSorts
            : ($this->schema?->sorts($this) ?? []);

        $disallowed = $this->disallowedSorts;
        $result = [];

        foreach ($sorts as $sort) {
            if (is_string($sort)) {
                $sort = $this->normalizeStringToSort($sort);
            }
            $name = $sort->getName();

            if (! empty($disallowed) && $this->isNameDisallowed($name, $disallowed)) {
                continue;
            }

            $result[$name] = $sort;
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
        return ! empty($this->defaultSorts)
            ? $this->defaultSorts
            : ($this->schema?->defaultSorts($this) ?? []);
    }

    /**
     * Extract all requested filter names from request.
     *
     * @return array<string>
     */
    protected function extractRequestedFilterNames(): array
    {
        $filters = $this->getEffectiveFilters();
        $allowedFilterNamesIndex = array_flip(array_keys($filters));

        return $this->extractAllRequestedFilterNames(
            $this->parameters->getFilters()->all(),
            '',
            $allowedFilterNamesIndex,
        );
    }

    /**
     * Extract all possible filter names from a nested request structure.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, int>  $allowedFilterNamesIndex
     * @return array<string>
     */
    protected function extractAllRequestedFilterNames(
        array $filters,
        string $prefix = '',
        array $allowedFilterNamesIndex = [],
    ): array {
        $names = [];

        foreach ($filters as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $names[] = $fullKey;

            if (isset($allowedFilterNamesIndex[$fullKey])) {
                continue;
            }

            if (is_array($value) && ! empty($value) && $this->isAssociativeArray($value)) {
                $names = array_merge(
                    $names,
                    $this->extractAllRequestedFilterNames(
                        $value,
                        $fullKey,
                        $allowedFilterNamesIndex,
                    )
                );
            }
        }

        return $names;
    }

    /**
     * Build prefix index for dot notation support.
     *
     * @param  array<string>  $allowedFilterNames
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
                $prefix = $prefix === '' ? $part : $prefix.'.'.$part;
                $prefixIndex[$prefix] = true;
            }
        }

        return $prefixIndex;
    }

    /**
     * Check if a filter name is valid.
     *
     * @param  array<string, int>  $allowedFilterNamesIndex
     * @param  array<string, bool>  $prefixIndex
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

    /**
     * Extract sort name from Sort object or string.
     */
    protected function extractSortName(string|Sort $sort): string
    {
        if ($sort instanceof Sort) {
            $prefix = $sort->getDirection() === 'desc' ? '-' : '';

            return $prefix.$sort->getField();
        }

        return $sort;
    }

    /**
     * Clone the wizard.
     *
     * Creates an independent copy with the same build state.
     * If the original was built, the clone will also be built
     * (with already-applied operations) to avoid double-application.
     *
     * To reconfigure a cloned wizard, call resetBuild() first.
     */
    public function __clone(): void
    {
        if (is_object($this->subject)) {
            $this->subject = clone $this->subject;
        }
    }

    /**
     * Reset the build state to allow reconfiguration.
     *
     * WARNING: If the subject already has operations applied (from a previous build),
     * calling this and then build() will apply operations again.
     * Only use this if you know the subject is in a clean state.
     */
    public function resetBuild(): static
    {
        $this->built = false;

        return $this;
    }
}

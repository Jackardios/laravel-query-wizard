<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Exceptions\MaxFiltersCountExceeded;

/**
 * Shared filter handling logic for query wizards.
 */
trait HandlesFilters
{
    use RequiresWizardContext;

    /** @var array<FilterInterface|string> */
    protected array $allowedFilters = [];

    protected bool $allowedFiltersExplicitlySet = false;

    /** @var array<string> */
    protected array $disallowedFilters = [];

    /** @var array<string, FilterInterface>|null */
    protected ?array $cachedEffectiveFilters = null;

    /** @var array<string, list<string>>|null */
    private ?array $cachedNestedFilterNames = null;

    /**
     * Schema default filters, read once while the filters are resolved.
     *
     * @var array<string, mixed>|null
     */
    private ?array $schemaDefaultFilters = null;

    /**
     * @api
     */
    abstract protected function normalizeStringToFilter(string $name): FilterInterface;

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
        if ($this->cachedEffectiveFilters !== null) {
            return $this->cachedEffectiveFilters;
        }

        $filters = $this->allowedFiltersExplicitlySet
            ? $this->allowedFilters
            : ($this->getSchema()?->filters($this) ?? []);

        $disallowed = $this->disallowedFilters;
        $result = [];

        foreach ($filters as $filter) {
            if (is_string($filter)) {
                $filter = $this->normalizeStringToFilter($filter);
            }
            $name = $this->normalizePublicPath($filter->getName());

            if (! empty($disallowed) && $this->isNameDisallowed($name, $disallowed)) {
                continue;
            }

            $result[$name] = $filter;
        }

        return $this->cachedEffectiveFilters = $result;
    }

    protected function getFilterValueFromRequest(string $name, bool $splitValues = true): mixed
    {
        return $this->getParametersManager()->getFilterValue($name, $splitValues);
    }

    /**
     * Names accepted as filter keys in the request.
     *
     * Override when a filter is composite: a container contributes the names of
     * its leaves instead of its own name.
     *
     * @param  array<string, FilterInterface>  $filters
     * @return array<int, string>
     *
     * @api
     */
    protected function resolveAllowedFilterNames(array $filters): array
    {
        return array_keys($filters);
    }

    /**
     * Effective filters whose request key is consumed by another filter and which
     * therefore must not be applied on their own.
     *
     * @param  array<string, FilterInterface>  $filters
     * @return array<string, true>
     *
     * @api
     */
    protected function resolveShadowedFilterNames(array $filters): array
    {
        return [];
    }

    /**
     * Allowed filter names nested under another allowed name, relative to it
     * and keyed by it: `name` => ['first'] for `name` and `name.first`.
     *
     * @return array<string, list<string>>
     */
    private function getNestedFilterNames(): array
    {
        if ($this->cachedNestedFilterNames !== null) {
            return $this->cachedNestedFilterNames;
        }

        $names = $this->resolveAllowedFilterNames($this->getEffectiveFilters());
        $namesIndex = array_flip($names);
        $nested = [];

        foreach ($names as $name) {
            $prefix = $name;

            while (($dot = strrpos($prefix, '.')) !== false) {
                $prefix = substr($prefix, 0, $dot);

                if (isset($namesIndex[$prefix])) {
                    $nested[$prefix][] = substr($name, $dot + 1);
                }
            }
        }

        return $this->cachedNestedFilterNames = $nested;
    }

    /**
     * Extract all requested filter names from request.
     *
     * Each request key belongs to the deepest allowed filter name it falls under.
     * Uses set-based counting to prevent duplicate filter names from being counted multiple times.
     *
     * @return array<string>
     */
    protected function extractRequestedFilterNames(): array
    {
        $filters = $this->getEffectiveFilters();
        $allowedFilterNamesIndex = array_flip($this->resolveAllowedFilterNames($filters));
        /** @var array<string, true> $requestedFilterNamesSet */
        $requestedFilterNamesSet = [];

        $this->extractAllRequestedFilterNamesUnique(
            $this->getParametersManager()->getFilters()->all(),
            $requestedFilterNamesSet,
            '',
            $allowedFilterNamesIndex,
            $this->getNestedFilterNames(),
        );

        return array_keys($requestedFilterNamesSet);
    }

    /**
     * Extract all unique filter names from a nested request structure.
     *
     * Uses a set (associative array with true values) for O(1) duplicate detection.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, true>  $namesSet
     * @param  array<string, int>  $allowedFilterNamesIndex
     * @param  array<string, list<string>>  $nestedFilterNames  See getNestedFilterNames()
     * @param  string|null  $owner  The allowed filter name the keys fall under
     */
    protected function extractAllRequestedFilterNamesUnique(
        array $filters,
        array &$namesSet,
        string $prefix,
        array $allowedFilterNamesIndex,
        array $nestedFilterNames = [],
        ?string $owner = null,
    ): void {
        foreach ($filters as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix.'.'.$this->normalizePublicPath((string) $key);
            $isRecursable = is_array($value) && ! empty($value) && $this->isAssociativeArray($value);
            $keyOwner = $owner;

            if (isset($allowedFilterNamesIndex[$fullKey])) {
                if (! $isRecursable || ! isset($nestedFilterNames[$fullKey])) {
                    $namesSet[$fullKey] = true;

                    continue;
                }

                $keyOwner = $fullKey;
            }

            if ($isRecursable) {
                /** @var array<string, mixed> $value */
                $this->extractAllRequestedFilterNamesUnique(
                    $value,
                    $namesSet,
                    $fullKey,
                    $allowedFilterNamesIndex,
                    $nestedFilterNames,
                    $keyOwner,
                );

                continue;
            }

            $namesSet[$keyOwner ?? $fullKey] = true;
        }
    }

    /**
     * The request value of a filter, without the keys that belong to filters
     * nested under its name.
     *
     * @return array{bool, mixed} Whether the filter is in the request, and its value
     *
     * @api
     */
    protected function getOwnFilterValueFromRequest(string $name, bool $splitValues): array
    {
        if (! $this->getParametersManager()->hasFilter($name)) {
            return [false, null];
        }

        $value = $this->getFilterValueFromRequest($name, $splitValues);
        $nestedNames = $this->getNestedFilterNames()[$name] ?? [];

        if ($nestedNames === [] || ! is_array($value) || $value === []) {
            return [true, $value];
        }

        foreach ($nestedNames as $nestedName) {
            $value = $this->withoutFilterPath($value, $nestedName);
        }

        return [$value !== [], $value];
    }

    /**
     * Remove the keys at a filter name path, matching each key as its
     * normalized public name.
     *
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function withoutFilterPath(array $value, string $path): array
    {
        foreach ($value as $key => $item) {
            $name = $this->normalizePublicPath((string) $key);

            if ($name === $path) {
                unset($value[$key]);
            } elseif (is_array($item) && str_starts_with($path, $name.'.')) {
                $value[$key] = $this->withoutFilterPath($item, substr($path, strlen($name) + 1));
            }
        }

        return $value;
    }

    protected function validateFiltersLimit(int $count): void
    {
        $limit = $this->getConfig()->getMaxFiltersCount();
        if ($limit !== null && $count > $limit) {
            throw new MaxFiltersCountExceeded($count, $limit);
        }
    }

    protected function invalidateFilterCache(): void
    {
        $this->cachedEffectiveFilters = null;
        $this->cachedNestedFilterNames = null;
    }

    /**
     * Get default filter values from schema.
     *
     * @return array<string, mixed>
     */
    protected function getSchemaDefaultFilters(): array
    {
        return $this->schemaDefaultFilters ??= $this->getSchema()?->defaultFilters($this) ?? [];
    }
}

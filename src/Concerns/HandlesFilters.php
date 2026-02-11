<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Exceptions\MaxFiltersCountExceeded;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;

/**
 * Shared filter handling logic for query wizards.
 */
trait HandlesFilters
{
    /** @var array<FilterInterface|string> */
    protected array $allowedFilters = [];

    protected bool $allowedFiltersExplicitlySet = false;

    /** @var array<string> */
    protected array $disallowedFilters = [];

    /** @var array<string, FilterInterface>|null */
    protected ?array $cachedEffectiveFilters = null;

    abstract protected function getConfig(): QueryWizardConfig;

    abstract protected function getParametersManager(): QueryParametersManager;

    abstract protected function getSchema(): ?ResourceSchemaInterface;

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
            $name = $filter->getName();

            if (! empty($disallowed) && $this->isNameDisallowed($name, $disallowed)) {
                continue;
            }

            $result[$name] = $filter;
        }

        return $this->cachedEffectiveFilters = $result;
    }

    protected function getFilterValueFromRequest(string $name): mixed
    {
        return $this->getParametersManager()->getFilterValue($name);
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
        $requestedFilterNames = [];

        $this->extractAllRequestedFilterNames(
            $this->getParametersManager()->getFilters()->all(),
            $requestedFilterNames,
            '',
            $allowedFilterNamesIndex,
        );

        return $requestedFilterNames;
    }

    /**
     * Extract all possible filter names from a nested request structure.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string>  $names
     * @param  array<string, int>  $allowedFilterNamesIndex
     */
    protected function extractAllRequestedFilterNames(
        array $filters,
        array &$names,
        string $prefix = '',
        array $allowedFilterNamesIndex = [],
    ): void {
        foreach ($filters as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (isset($allowedFilterNamesIndex[$fullKey])) {
                $names[] = $fullKey;

                continue;
            }

            $isRecursable = is_array($value) && ! empty($value) && $this->isAssociativeArray($value);

            if ($isRecursable) {
                $this->extractAllRequestedFilterNames(
                    $value,
                    $names,
                    $fullKey,
                    $allowedFilterNamesIndex,
                );

                continue;
            }

            $names[] = $fullKey;
        }
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
        $limit = $this->getConfig()->getMaxFiltersCount();
        if ($limit !== null && $count > $limit) {
            throw MaxFiltersCountExceeded::create($count, $limit);
        }
    }

    protected function invalidateFilterCache(): void
    {
        $this->cachedEffectiveFilters = null;
    }
}

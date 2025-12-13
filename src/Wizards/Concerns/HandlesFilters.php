<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Wizards\Concerns;

use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;

trait HandlesFilters
{
    /** @var array<FilterDefinitionInterface|string> */
    protected array $allowedFilters = [];

    protected bool $filtersApplied = false;

    /**
     * Set allowed filters
     *
     * @param FilterDefinitionInterface|string|array<FilterDefinitionInterface|string> ...$filters
     */
    public function setAllowedFilters(FilterDefinitionInterface|string|array ...$filters): static
    {
        $this->allowedFilters = $this->flattenDefinitions($filters);
        return $this;
    }

    /**
     * Get effective filters (schema + context applied)
     *
     * @return array<FilterDefinitionInterface>
     */
    protected function getEffectiveFilters(): array
    {
        $filters = !empty($this->allowedFilters)
            ? $this->allowedFilters
            : ($this->schema?->filters() ?? []);

        $context = $this->resolveContext();
        if ($context !== null) {
            if ($context->getAllowedFilters() !== null) {
                $filters = $context->getAllowedFilters();
            }

            $disallowed = $context->getDisallowedFilters();
            if (!empty($disallowed)) {
                $filters = $this->removeDisallowed($filters, $disallowed, fn($item) =>
                    $item instanceof FilterDefinitionInterface ? $item->getName() : $item
                );
            }
        }

        return $this->normalizeFilters($filters);
    }

    /**
     * Apply filters to subject
     */
    protected function applyFilters(): void
    {
        if ($this->filtersApplied) {
            return;
        }

        if (!in_array('filters', $this->driver->capabilities(), true)) {
            $this->filtersApplied = true;
            return;
        }

        $filters = $this->getEffectiveFilters();

        $filtersIndex = [];
        foreach ($filters as $filter) {
            $filtersIndex[$filter->getName()] = $filter;
        }

        $allowedFilterNames = array_keys($filtersIndex);
        $allowedFilterNamesIndex = array_flip($allowedFilterNames);

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

        $requestedFilterNames = $this->extractAllRequestedFilterNames(
            $this->parameters->getFilters()->all(),
            '',
            $allowedFilterNamesIndex
        );

        foreach ($requestedFilterNames as $filterName) {
            if (!$this->isValidFilterName($filterName, $allowedFilterNamesIndex, $prefixIndex)) {
                if (!$this->config->isInvalidFilterQueryExceptionDisabled()) {
                    throw \Jackardios\QueryWizard\Exceptions\InvalidFilterQuery::filtersNotAllowed(
                        collect([$filterName]),
                        collect($allowedFilterNames)
                    );
                }
            }
        }

        foreach ($filters as $filter) {
            $name = $filter->getName();
            $value = $this->parameters->getFilterValue($name) ?? $filter->getDefault();

            if ($value === null) {
                continue;
            }

            $value = $filter->prepareValue($value);
            $this->subject = $this->driver->applyFilter($this->subject, $filter, $value);
        }

        $this->filtersApplied = true;
    }

    /**
     * Normalize filters to FilterDefinitionInterface
     *
     * @param array<FilterDefinitionInterface|string> $filters
     * @return array<FilterDefinitionInterface>
     */
    protected function normalizeFilters(array $filters): array
    {
        return array_map(
            fn($filter) => $this->driver->normalizeFilter($filter),
            $filters
        );
    }

    /**
     * Extract all possible filter names from a nested request structure
     *
     * @param array<string, mixed> $filters
     * @param array<string, int> $allowedFilterNamesIndex Hash-based index for O(1) lookup
     * @return array<string>
     */
    protected function extractAllRequestedFilterNames(array $filters, string $prefix = '', array $allowedFilterNamesIndex = []): array
    {
        $names = [];

        foreach ($filters as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            $names[] = $fullKey;

            if (isset($allowedFilterNamesIndex[$fullKey])) {
                continue;
            }

            if (is_array($value) && !empty($value) && $this->isAssociativeArray($value)) {
                $names = array_merge($names, $this->extractAllRequestedFilterNames($value, $fullKey, $allowedFilterNamesIndex));
            }
        }

        return $names;
    }

    /**
     * Check if a filter name is valid
     *
     * @param array<string, int> $allowedFilterNamesIndex Hash-based index for O(1) lookup
     * @param array<string, bool> $prefixIndex Hash-based index of valid prefixes
     */
    protected function isValidFilterName(string $filterName, array $allowedFilterNamesIndex, array $prefixIndex): bool
    {
        if (isset($allowedFilterNamesIndex[$filterName])) {
            return true;
        }

        return isset($prefixIndex[$filterName]);
    }

    /**
     * @param array<mixed> $array
     */
    protected function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }
}

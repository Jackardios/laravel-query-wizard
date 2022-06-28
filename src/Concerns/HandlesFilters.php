<?php

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Abstracts\AbstractFilter;
use Jackardios\QueryWizard\Exceptions\InvalidFilterHandler;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;

trait HandlesFilters
{
    /**
     * @param string $filterName
     * @return AbstractFilter
     */
    abstract public function makeDefaultFilterHandler(string $filterName);

    private ?Collection $allowedFilters = null;
    private ?Collection $preparedFilters = null;

    /**
     * @return AbstractFilter[]|string[]
     */
    protected function allowedFilters(): array
    {
        return [];
    }

    public function getAllowedFilters(): Collection
    {
        if (!($this->allowedFilters instanceof Collection)) {
            $allowedFiltersFromCallback = $this->allowedFilters();

            if ($allowedFiltersFromCallback) {
                $this->setAllowedFilters($allowedFiltersFromCallback);
            } else {
                return collect();
            }
        }

        return $this->allowedFilters;
    }

    public function setAllowedFilters($filters): static
    {
        $filters = is_array($filters) ? $filters : func_get_args();

        $autoCreatedHandlers = collect([]);
        $userDefinedHandlers = collect($filters)
            ->filter()
            ->mapWithKeys(function($filter) use (&$autoCreatedHandlers) {
                if (is_string($filter)) {
                    $filter = $this->makeDefaultFilterHandler($filter);
                }

                if (! instance_of_one_of($filter, $this->baseFilterHandlerClasses)) {
                    new InvalidFilterHandler($this->baseFilterHandlerClasses);
                }

                $autoCreatedHandlers->push($filter->createExtra());

                return [$filter->getName() => $filter];
            });

        $autoCreatedHandlers = $autoCreatedHandlers
            ->flatten()
            ->mapWithKeys(fn (AbstractFilter $handler) => [$handler->getName() => $handler]);

        $this->allowedFilters = $autoCreatedHandlers->merge($userDefinedHandlers);

        $this->prepareFilters();

        return $this;
    }

    public function getFilters(): Collection
    {
        $allowedFilters = $this->getAllowedFilters();
        if (is_null($this->preparedFilters) || $allowedFilters->isEmpty()) {
            return collect();
        }

        return $this->preparedFilters;
    }

    protected function prepareFilters(): static
    {
        $requestedFilters = $this->parametersManager->getFilters();
        $allowedFilters = $this->getAllowedFilters();

        if (is_null($this->preparedFilters)) {
            $this->preparedFilters = $allowedFilters
                ->filter(fn (AbstractFilter $filter) => $filter->hasDefault())
                ->map(fn (AbstractFilter $filter) => $filter->hasPrepareValueCallback()
                    ? $filter->getPrepareValueCallback()($filter->getDefault())
                    : $filter->getDefault()
                );
        }
        $unknownFilterKeys = collect();
        $this->prepareFiltersAndGetUnknownKeys($requestedFilters, $allowedFilters, $unknownFilterKeys);

        if ($unknownFilterKeys->isNotEmpty()) {
            $this->preparedFilters = null;
            throw InvalidFilterQuery::filtersNotAllowed($unknownFilterKeys, $allowedFilters->keys());
        }

        return $this;
    }

    private function prepareFiltersAndGetUnknownKeys(
        \ArrayAccess|array $requestedFilters,
        Collection $allowedFilters,
        Collection $unknownFilterKeys,
        string $keysPrefix = ''): Collection
    {
        foreach($requestedFilters as $requestedFilterKey => $requestedFilterValue) {
            $prefixedKey = $keysPrefix . $requestedFilterKey;

            /** @var AbstractFilter|null $filterHandler */
            $filterHandler = $allowedFilters->get($prefixedKey);
            if ($filterHandler) {
                $requestedFilterValue = $this->prepareFilterValue($filterHandler, $requestedFilterValue);

                if (filled($requestedFilterValue)) {
                    $this->preparedFilters[$prefixedKey] = $requestedFilterValue;
                }
                continue;
            }

            if(is_array($requestedFilterValue)) {
                $this->prepareFiltersAndGetUnknownKeys($requestedFilterValue, $allowedFilters, $unknownFilterKeys, $prefixedKey . '.');
                continue;
            }

            $unknownFilterKeys[] = $prefixedKey;
        }

        return $unknownFilterKeys;
    }

    private function prepareFilterValue(AbstractFilter $filter, mixed $filterValue) {
        if (blank($filterValue) && $filter->hasDefault()) {
            $filterValue = $filter->getDefault();
        }

        if ($filter->hasPrepareValueCallback()) {
            $filterValue = $filter->getPrepareValueCallback()($filterValue);
        }

        return $filterValue;
    }
}

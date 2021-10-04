<?php

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Abstracts\Handlers\Filters\AbstractFilter;
use Jackardios\QueryWizard\Exceptions\InvalidFilterHandler;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;

trait HandlesFilters
{
    /**
     * @param string $filterName
     * @return AbstractFilter
     */
    abstract public function makeDefaultFilterHandler(string $filterName);

    protected ?Collection $allowedFilters = null;

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

    public function setAllowedFilters($filters): self
    {
        $filters = is_array($filters) ? $filters : func_get_args();

        // auto-created handlers should only be merged after user-defined handlers,
        // otherwise the user-defined handlers will be overwritten
        $autoCreatedHandlers = [];
        $this->allowedFilters = collect($filters)
            ->filter()
            ->map(function($filter) use (&$autoCreatedHandlers) {
                if (is_string($filter)) {
                    $filter = $this->makeDefaultFilterHandler($filter);
                }

                $baseHandlerClasses = $this->queryHandler::getBaseFilterHandlerClasses();
                if (! instance_of_one_of($filter, $baseHandlerClasses)) {
                    new InvalidFilterHandler($baseHandlerClasses);
                }

                $autoCreatedHandlers[] = $filter->createOther();

                return $filter;
            })
            ->merge($autoCreatedHandlers)
            ->flatten()
            ->unique(fn (AbstractFilter $handler) => $handler->getName())
            ->mapWithKeys(fn (AbstractFilter $handler) => [$handler->getName() => $handler]);

        $this->ensureAllFiltersAllowed();

        return $this;
    }

    public function getFilters(): Collection
    {
        $allowedFilters = $this->getAllowedFilters();
        if ($allowedFilters->isEmpty()) {
            return collect();
        }

        $requestedFilters = $this->request->filters();
        $allowedFilters->each(function(AbstractFilter $filter) use ($requestedFilters) {
            $filterName = $filter->getName();
            if ($filter->hasDefault() && !$requestedFilters->has($filterName)) {
                $requestedFilters[$filterName] = $filter->getDefault();
            }
        });
        return $requestedFilters;
    }

    protected function ensureAllFiltersAllowed(): self
    {
        $requestedFilters = $this->request->filters()->keys();
        $allowedFilters = $this->getAllowedFilters()->keys();

        $unknownFilters = $requestedFilters->diff($allowedFilters);

        if ($unknownFilters->isNotEmpty()) {
            throw InvalidFilterQuery::filtersNotAllowed($unknownFilters, $allowedFilters);
        }

        return $this;
    }
}

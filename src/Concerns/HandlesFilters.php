<?php

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Exceptions\InvalidFilterHandler;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;

trait HandlesFilters
{
    protected ?Collection $allowedFilters = null;

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

        $this->allowedFilters = collect($filters)
            ->mapWithKeys(function($handler) {
                $baseHandlerClass = $this->queryHandler::getBaseFilterHandlerClass();

                if (is_string($handler)) {
                    $handler = $this->queryHandler->makeDefaultFilterHandler($handler);
                } else if (! $handler instanceof $baseHandlerClass) {
                    throw new InvalidFilterHandler($baseHandlerClass);
                }

                return [$handler->getName() => $handler];
            });

        $this->ensureAllFiltersAllowed();

        return $this;
    }

    public function handleFilters(): self
    {
        $filters = $this->request->filters();
        $handlers = $this->getAllowedFilters();
        $this->queryHandler->filter($filters, $handlers);
        return $this;
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

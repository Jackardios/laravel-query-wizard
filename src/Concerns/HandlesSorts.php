<?php

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Abstracts\Handlers\Sorts\AbstractSort;
use Jackardios\QueryWizard\Exceptions\InvalidSortHandler;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\Values\Sort;

trait HandlesSorts
{
    protected ?Collection $allowedSorts = null;
    protected ?Collection $defaultSorts = null;

    protected function allowedSorts(): array
    {
        return [];
    }

    public function getAllowedSorts(): Collection
    {
        if (!($this->allowedSorts instanceof Collection)) {
            $allowedSortsFromCallback = $this->allowedSorts();

            if ($allowedSortsFromCallback) {
                $this->setAllowedSorts($allowedSortsFromCallback);
            } else {
                return collect();
            }
        }

        return $this->allowedSorts;
    }

    public function setAllowedSorts($sorts): self
    {
        $sorts = is_array($sorts) ? $sorts : func_get_args();

        // auto-created handlers should only merge after user-defined handlers,
        // otherwise the user-defined handlers will be overwritten
        $autoCreatedHandlers = [];
        $this->allowedSorts = collect($sorts)
            ->filter()
            ->map(function($sort) use (&$autoCreatedHandlers) {
                if (is_string($sort)) {
                    $sort = $this->queryHandler->makeDefaultSortHandler(ltrim($sort, '-'));
                }

                $baseHandlerClass = $this->queryHandler::getBaseSortHandlerClass();
                if (! ($sort instanceof $baseHandlerClass)) {
                    new InvalidSortHandler($baseHandlerClass);
                }

                $autoCreatedHandlers[] = $sort->createOther();

                return $sort;
            })
            ->merge($autoCreatedHandlers)
            ->flatten()
            ->unique(fn (AbstractSort $handler) => $handler->getName())
            ->mapWithKeys(fn (AbstractSort $handler) => [$handler->getName() => $handler]);

        $this->ensureAllSortsAllowed();

        return $this;
    }

    protected function defaultSorts(): array
    {
        return [];
    }

    public function getDefaultSorts(): Collection
    {
        if (!($this->defaultSorts instanceof Collection)) {
            $defaultSortsFromCallback = $this->defaultSorts();

            if ($defaultSortsFromCallback) {
                $this->setDefaultSorts($defaultSortsFromCallback);
            } else {
                return collect();
            }
        }

        return $this->defaultSorts;
    }

    public function setDefaultSorts($sorts): self
    {
        $sorts = is_array($sorts) ? $sorts : func_get_args();

        $this->defaultSorts = collect($sorts)
            ->filter()
            ->map(fn($sort) => ($sort instanceof Sort) ? $sort : new Sort((string)$sort))
            ->unique(fn(Sort $sort) => $sort->getField())
            ->values();

        return $this;
    }

    public function getSorts(): Collection
    {
        if($this->getAllowedSorts()->isEmpty()) {
            return collect();
        }

        $sorts = $this->request->sorts();
        return $sorts->isNotEmpty() ? $sorts : $this->getDefaultSorts();
    }

    protected function ensureAllSortsAllowed(): self
    {
        $requestedSorts = $this->request->sorts()->map(function (Sort $sort) {
            return $sort->getField();
        });
        $allowedSorts = $this->getAllowedSorts()->keys();

        $unknownSorts = $requestedSorts->diff($allowedSorts);

        if ($unknownSorts->isNotEmpty()) {
            throw InvalidSortQuery::sortsNotAllowed($unknownSorts, $allowedSorts);
        }

        return $this;
    }
}

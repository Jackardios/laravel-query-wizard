<?php

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Abstracts\AbstractSort;
use Jackardios\QueryWizard\Exceptions\InvalidSortHandler;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\Values\Sort;

trait HandlesSorts
{
    /**
     * @param string $sortName
     * @return AbstractSort
     */
    abstract public function makeDefaultSortHandler(string $sortName);

    private ?Collection $allowedSorts = null;
    private ?Collection $defaultSorts = null;

    /**
     * @return AbstractSort[]|string[]
     */
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

    public function setAllowedSorts($sorts): static
    {
        $sorts = is_array($sorts) ? $sorts : func_get_args();

        // auto-created handlers should only be merged after user-defined handlers,
        // otherwise the user-defined handlers will be overwritten
        $autoCreatedHandlers = collect([]);
        $userDefinedHandlers = collect($sorts)
            ->filter()
            ->mapWithKeys(function($sort) use (&$autoCreatedHandlers) {
                if (is_string($sort)) {
                    $sort = $this->makeDefaultSortHandler(ltrim($sort, '-'));
                }

                if (! instance_of_one_of($sort, $this->baseSortHandlerClasses)) {
                    new InvalidSortHandler($this->baseSortHandlerClasses);
                }

                $autoCreatedHandlers->push($sort->createExtra());

                return [$sort->getName() => $sort];
            });

        $autoCreatedHandlers = $autoCreatedHandlers
            ->flatten()
            ->mapWithKeys(fn (AbstractSort $handler) => [$handler->getName() => $handler]);

        $this->allowedSorts = $autoCreatedHandlers->merge($userDefinedHandlers);

        $this->ensureAllSortsAllowed();

        return $this;
    }

    /**
     * @return AbstractSort[]|string[]
     */
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

    public function setDefaultSorts($sorts): static
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

        $sorts = $this->parametersManager->getSorts();

        return $sorts->isEmpty() ? $this->getDefaultSorts() : $sorts;
    }

    protected function ensureAllSortsAllowed(): static
    {
        $requestedSorts = $this->parametersManager->getSorts()->map(function (Sort $sort) {
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

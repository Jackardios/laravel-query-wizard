<?php

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Exceptions\InvalidSortHandler;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\Values\Sort;

trait HandlesSorts
{
    protected ?Collection $allowedSorts = null;

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

        $this->allowedSorts = collect($sorts)
            ->mapWithKeys(function($handler) {
                $baseHandlerClass = $this->queryHandler::getBaseSortHandlerClass();

                if (is_string($handler)) {
                    $handler = $this->queryHandler->makeDefaultSortHandler($handler);
                } else if (! $handler instanceof $baseHandlerClass) {
                    throw new InvalidSortHandler($baseHandlerClass);
                }

                return [$handler->getName() => $handler];
            });

        $this->ensureAllSortsAllowed();

        return $this;
    }

    public function handleSorts(): self
    {
        $sorts = $this->request->sorts();
        $handlers = $this->getAllowedSorts();
        $this->queryHandler->sort($sorts, $handlers);
        return $this;
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

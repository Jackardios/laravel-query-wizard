<?php

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeHandler;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;

trait HandlesIncludes
{
    protected ?Collection $allowedIncludes = null;

    protected function allowedIncludes(): array
    {
        return [];
    }

    public function getAllowedIncludes(): Collection
    {
        if (!($this->allowedIncludes instanceof Collection)) {
            $allowedIncludesFromCallback = $this->allowedIncludes();

            if ($allowedIncludesFromCallback) {
                $this->setAllowedIncludes($allowedIncludesFromCallback);
            } else {
                return collect();
            }
        }

        return $this->allowedIncludes;
    }

    public function setAllowedIncludes($includes): self
    {
        $includes = is_array($includes) ? $includes : func_get_args();

        $this->allowedIncludes = collect($includes)
            ->mapWithKeys(function($handler) {
                $baseHandlerClass = $this->queryHandler::getBaseIncludeHandlerClass();

                if (is_string($handler)) {
                    $handler = $this->queryHandler->makeDefaultIncludeHandler($handler);
                } else if (! $handler instanceof $baseHandlerClass) {
                    throw new InvalidIncludeHandler($baseHandlerClass);
                }

                return [$handler->getName() => $handler];
            });

        $this->ensureAllIncludesAllowed();

        return $this;
    }

    public function getIncludes(): Collection
    {
        $this->getAllowedIncludes();
        return $this->request->includes();
    }

    protected function ensureAllIncludesAllowed(): self
    {
        $requestedIncludes = $this->request->includes();
        $allowedIncludes = $this->getAllowedIncludes()->keys();

        $unknownIncludes = $requestedIncludes->diff($allowedIncludes);

        if ($unknownIncludes->isNotEmpty()) {
            throw InvalidIncludeQuery::includesNotAllowed($unknownIncludes, $allowedIncludes);
        }

        return $this;
    }
}

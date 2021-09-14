<?php

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Abstracts\Handlers\Includes\AbstractInclude;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeHandler;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;

trait HandlesIncludes
{
    /**
     * @param string $includeName
     * @return AbstractInclude
     */
    abstract public function makeDefaultIncludeHandler(string $includeName);

    protected ?Collection $allowedIncludes = null;
    protected ?Collection $defaultIncludes = null;

    /**
     * @return AbstractInclude[]|string[]
     */
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

        // auto-created handlers should only be merged after user-defined handlers,
        // otherwise the user-defined handlers will be overwritten
        $autoCreatedHandlers = [];
        $this->allowedIncludes = collect($includes)
            ->filter()
            ->map(function($include) use (&$autoCreatedHandlers) {
                if (is_string($include)) {
                    $include = $this->makeDefaultIncludeHandler($include);
                }

                $baseHandlerClass = $this->queryHandler::getBaseIncludeHandlerClass();
                if (! ($include instanceof $baseHandlerClass)) {
                    new InvalidIncludeHandler($baseHandlerClass);
                }

                $autoCreatedHandlers[] = $include->createOther();

                return $include;
            })
            ->merge($autoCreatedHandlers)
            ->flatten()
            ->unique(fn (AbstractInclude $handler) => $handler->getName())
            ->mapWithKeys(fn (AbstractInclude $handler) => [$handler->getName() => $handler]);

        $this->ensureAllIncludesAllowed();

        return $this;
    }

    /**
     * @return AbstractInclude[]|string[]
     */
    protected function defaultIncludes(): array
    {
        return [];
    }

    public function getDefaultIncludes(): Collection
    {
        if (!($this->defaultIncludes instanceof Collection)) {
            $defaultIncludesFromCallback = $this->defaultIncludes();

            if ($defaultIncludesFromCallback) {
                $this->setDefaultIncludes($defaultIncludesFromCallback);
            } else {
                return collect();
            }
        }

        return $this->defaultIncludes;
    }

    public function setDefaultIncludes($includes): self
    {
        $includes = is_array($includes) ? $includes : func_get_args();

        $this->defaultIncludes = collect($includes)
            ->filter()
            ->unique()
            ->values();

        return $this;
    }

    public function getIncludes(): Collection
    {
        if($this->getAllowedIncludes()->isEmpty()) {
            return collect();
        }

        $includes = $this->request->includes();
        return $includes->isNotEmpty() ? $includes : $this->getDefaultIncludes();
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

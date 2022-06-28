<?php

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Abstracts\AbstractInclude;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeHandler;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;

trait HandlesIncludes
{
    /**
     * @param string $includeName
     * @return AbstractInclude
     */
    abstract public function makeDefaultIncludeHandler(string $includeName);

    private ?Collection $allowedIncludes = null;
    private ?Collection $defaultIncludes = null;

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

    public function setAllowedIncludes($includes): static
    {
        $includes = is_array($includes) ? $includes : func_get_args();

        $autoCreatedHandlers = collect([]);
        $userDefinedHandlers = collect($includes)
            ->filter()
            ->mapWithKeys(function($include) use (&$autoCreatedHandlers) {
                if (is_string($include)) {
                    $include = $this->makeDefaultIncludeHandler($include);
                }

                if (! instance_of_one_of($include, $this->baseIncludeHandlerClasses)) {
                    new InvalidIncludeHandler($this->baseIncludeHandlerClasses);
                }

                $autoCreatedHandlers->push($include->createExtra());

                return [$include->getName() => $include];
            });

        $autoCreatedHandlers = $autoCreatedHandlers
            ->flatten()
            ->mapWithKeys(fn (AbstractInclude $handler) => [$handler->getName() => $handler]);

        $this->allowedIncludes = $autoCreatedHandlers->merge($userDefinedHandlers);

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

    public function setDefaultIncludes($includes): static
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

        $includes = $this->parametersManager->getIncludes();

        return $includes->isEmpty() ? $this->getDefaultIncludes() : $includes;
    }

    protected function ensureAllIncludesAllowed(): static
    {
        $requestedIncludes = $this->parametersManager->getIncludes();
        $allowedIncludes = $this->getAllowedIncludes()->keys();

        $unknownIncludes = $requestedIncludes->diff($allowedIncludes);

        if ($unknownIncludes->isNotEmpty()) {
            throw InvalidIncludeQuery::includesNotAllowed($unknownIncludes, $allowedIncludes);
        }

        return $this;
    }
}

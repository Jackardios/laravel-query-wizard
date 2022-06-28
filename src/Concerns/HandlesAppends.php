<?php

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;

trait HandlesAppends
{
    private ?Collection $allowedAppends = null;
    private ?Collection $defaultAppends = null;

    /**
     * @return string[]
     */
    protected function allowedAppends(): array
    {
        return [];
    }

    public function getAllowedAppends(): Collection
    {
        if (!($this->allowedAppends instanceof Collection)) {
            $allowedAppendsFromCallback = $this->allowedAppends();

            if ($allowedAppendsFromCallback) {
                $this->setAllowedAppends($allowedAppendsFromCallback);
            } else {
                return collect();
            }
        }

        return $this->allowedAppends;
    }

    public function setAllowedAppends($appends): static
    {
        $appends = is_array($appends) ? $appends : func_get_args();

        $this->allowedAppends = collect($appends)
            ->filter()
            ->unique()
            ->values();

        $this->ensureAllAppendsAllowed();

        return $this;
    }

    /**
     * @return string[]
     */
    protected function defaultAppends(): array
    {
        return [];
    }

    public function getDefaultAppends(): Collection
    {
        if (!($this->defaultAppends instanceof Collection)) {
            $defaultAppendsFromCallback = $this->defaultAppends();

            if ($defaultAppendsFromCallback) {
                $this->setDefaultAppends($defaultAppendsFromCallback);
            } else {
                return collect();
            }
        }

        return $this->defaultAppends;
    }

    public function setDefaultAppends($appends): static
    {
        $appends = is_array($appends) ? $appends : func_get_args();

        $this->defaultAppends = collect($appends)
            ->filter()
            ->unique()
            ->values();

        return $this;
    }

    public function getAppends(): Collection
    {
        if ($this->getAllowedAppends()->isEmpty()) {
            return collect();
        }

        $requestedAppends = $this->parametersManager->getAppends();

        return $requestedAppends->isEmpty() ? $this->getDefaultAppends() : $requestedAppends;
    }

    protected function ensureAllAppendsAllowed(): static
    {
        $requestedAppends = $this->parametersManager->getAppends();

        $unknownAppends = $requestedAppends->diff($this->allowedAppends);

        if ($unknownAppends->isNotEmpty()) {
            throw InvalidAppendQuery::appendsNotAllowed($unknownAppends, $this->allowedAppends);
        }

        return $this;
    }
}

<?php

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;

trait HandlesAppends
{
    protected ?Collection $allowedAppends = null;
    protected ?Collection $defaultAppends = null;

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

    public function setAllowedAppends($appends): self
    {
        $appends = is_array($appends) ? $appends : func_get_args();

        $this->allowedAppends = collect($appends)
            ->filter()
            ->unique()
            ->values();

        $this->ensureAllAppendsAllowed();

        return $this;
    }

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

    public function setDefaultAppends($appends): self
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

        $appends = $this->request->appends();
        return $appends->isNotEmpty() ? $appends : $this->getDefaultAppends();
    }

    protected function ensureAllAppendsAllowed(): self
    {
        $requestedAppends = $this->request->appends();

        $unknownAppends = $requestedAppends->diff($this->allowedAppends);

        if ($unknownAppends->isNotEmpty()) {
            throw InvalidAppendQuery::appendsNotAllowed($unknownAppends, $this->allowedAppends);
        }

        return $this;
    }
}

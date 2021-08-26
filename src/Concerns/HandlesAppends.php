<?php

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;

trait HandlesAppends
{
    protected ?Collection $allowedAppends = null;

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

    public function handleAppends(): self
    {
        $this->getAllowedAppends();
        $this->queryHandler->append($this->request->appends());
        return $this;
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

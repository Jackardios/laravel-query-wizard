<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Wizards\Concerns;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;

trait HandlesAppends
{
    /** @var array<string> */
    protected array $allowedAppends = [];

    /**
     * Set allowed appends
     *
     * @param string|array<string> ...$appends
     */
    public function setAllowedAppends(string|array ...$appends): static
    {
        $this->allowedAppends = $this->flattenStringArray(array_map(
            fn($a) => is_array($a) ? $a : [$a],
            $appends
        ));
        return $this;
    }

    /**
     * Get effective appends (schema + context applied)
     *
     * @return array<string>
     */
    protected function getEffectiveAppends(): array
    {
        $appends = !empty($this->allowedAppends)
            ? $this->allowedAppends
            : ($this->schema?->appends() ?? []);

        $context = $this->resolveContext();
        if ($context !== null) {
            if ($context->getAllowedAppends() !== null) {
                $appends = $context->getAllowedAppends();
            }

            $disallowed = $context->getDisallowedAppends();
            if (!empty($disallowed)) {
                $appends = array_filter($appends, fn($append) => !in_array($append, $disallowed, true));
            }
        }

        return array_values($appends);
    }

    /**
     * Get effective default appends
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultAppends(): array
    {
        $context = $this->resolveContext();
        if ($context?->getDefaultAppends() !== null) {
            return $context->getDefaultAppends();
        }

        return $this->schema?->defaultAppends() ?? [];
    }

    /**
     * Validate requested appends
     */
    protected function validateAppends(): void
    {
        if (!in_array('appends', $this->driver->capabilities(), true)) {
            return;
        }

        $allowedAppends = $this->getEffectiveAppends();
        $requestedAppends = $this->parameters->getAppends();

        if (empty($allowedAppends)) {
            return;
        }

        $invalidAppends = $requestedAppends->filter(function ($append) use ($allowedAppends) {
            return !$this->isAppendAllowed($append, $allowedAppends);
        });

        if ($invalidAppends->isNotEmpty()) {
            throw InvalidAppendQuery::appendsNotAllowed($invalidAppends, collect($allowedAppends));
        }
    }

    /**
     * Check if an append is allowed (supports exact match and wildcards)
     *
     * @param array<string> $allowedAppends
     */
    protected function isAppendAllowed(string $append, array $allowedAppends): bool
    {
        if (in_array($append, $allowedAppends, true)) {
            return true;
        }

        foreach ($allowedAppends as $allowed) {
            if (str_ends_with($allowed, '.*')) {
                $prefix = substr($allowed, 0, -1);
                if (str_starts_with($append, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the requested appends that are in the allowed list
     *
     * @return array<string>
     */
    protected function getValidRequestedAppends(): array
    {
        $allowedAppends = $this->getEffectiveAppends();
        $requestedAppends = $this->parameters->getAppends();
        $defaultAppends = $this->getEffectiveDefaultAppends();

        if ($requestedAppends->isEmpty() && !empty($defaultAppends)) {
            return array_filter(
                $defaultAppends,
                fn($append) => $this->isAppendAllowed($append, $allowedAppends)
            );
        }

        return $requestedAppends
            ->filter(fn($append) => $this->isAppendAllowed($append, $allowedAppends))
            ->values()
            ->all();
    }

    /**
     * Apply appends to result
     *
     * @param Collection<int|string, mixed>|mixed $result
     * @return Collection<int|string, mixed>|mixed
     */
    protected function applyAppendsToResult(mixed $result): mixed
    {
        $effectiveAppends = $this->getValidRequestedAppends();

        if (!empty($effectiveAppends)) {
            $this->driver->applyAppends($result, $effectiveAppends);
        }

        return $result;
    }

    /**
     * Get the requested appends
     *
     * @return Collection<int, string>
     */
    public function getAppends(): Collection
    {
        return $this->parameters->getAppends();
    }
}

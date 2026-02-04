<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Wizards\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Jackardios\QueryWizard\Enums\Capability;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Exceptions\UnsupportedCapability;

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
        $context = $this->resolveContext();

        $appends = $this->resolveAllowedDefinitions(
            $this->allowedAppends,
            fn() => $this->schema?->appends() ?? [],
            $context !== null ? fn() => $context->getAllowedAppends() : null,
            $context !== null ? fn() => $context->getDisallowedAppends() : null,
            fn($item) => $item
        );

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

        return $this->resolveEffectiveDefaults(
            [],
            $context !== null ? fn() => $context->getDefaultAppends() : null,
            fn() => $this->schema?->defaultAppends() ?? []
        );
    }

    /**
     * Validate requested appends
     */
    protected function validateAppends(): void
    {
        if (!in_array(Capability::APPENDS->value, $this->driver->capabilities(), true)) {
            if ($this->config->shouldThrowOnUnsupportedCapability()) {
                throw UnsupportedCapability::make(Capability::APPENDS->value, $this->driver->name());
            }

            if ($this->config->shouldLogUnsupportedCapability()) {
                Log::warning("Driver '{$this->driver->name()}' does not support appends capability");
            }

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

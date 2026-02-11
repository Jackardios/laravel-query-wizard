<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Exceptions\MaxIncludeDepthExceeded;
use Jackardios\QueryWizard\Exceptions\MaxIncludesCountExceeded;

/**
 * Shared include handling logic for query wizards.
 */
trait HandlesIncludes
{
    use RequiresWizardContext;

    /** @var array<IncludeInterface|string> */
    protected array $allowedIncludes = [];

    protected bool $allowedIncludesExplicitlySet = false;

    /** @var array<string> */
    protected array $disallowedIncludes = [];

    /** @var array<string> */
    protected array $defaultIncludes = [];

    /** @var array<IncludeInterface>|null */
    protected ?array $cachedEffectiveIncludes = null;

    /**
     * Normalize a string include to an IncludeInterface instance.
     */
    abstract protected function normalizeStringToInclude(string $name): IncludeInterface;

    /**
     * Get effective includes.
     *
     * If allowedIncludes() was called explicitly, use those (even if empty).
     * Otherwise, fall back to schema includes (if any).
     * Empty result means all includes are forbidden.
     *
     * @return array<IncludeInterface>
     */
    protected function getEffectiveIncludes(): array
    {
        if ($this->cachedEffectiveIncludes !== null) {
            return $this->cachedEffectiveIncludes;
        }

        $includes = $this->allowedIncludesExplicitlySet
            ? $this->allowedIncludes
            : ($this->getSchema()?->includes($this) ?? []);

        $disallowed = $this->disallowedIncludes;
        $result = [];

        foreach ($includes as $include) {
            if (is_string($include)) {
                $include = $this->normalizeStringToInclude($include);
            }

            $configKey = $include->getSuffixConfigKey();
            $suffix = $configKey !== null
                ? (string) config('query-wizard.'.$configKey, $include->getDefaultAliasSuffix())
                : null;
            $include = $include->withDefaultAlias($suffix);

            $name = $include->getName();

            if (! empty($disallowed) && $this->isNameDisallowed($name, $disallowed)) {
                continue;
            }

            $result[] = $include;
        }

        return $this->cachedEffectiveIncludes = $result;
    }

    /**
     * Get effective default includes.
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultIncludes(): array
    {
        return ! empty($this->defaultIncludes)
            ? $this->defaultIncludes
            : ($this->getSchema()?->defaultIncludes($this) ?? []);
    }

    /**
     * Check if includes request is completely absent (for defaults logic).
     */
    protected function isIncludesRequestEmpty(): bool
    {
        return $this->getParametersManager()->getIncludes()->isEmpty();
    }

    /**
     * Get effective requested includes.
     *
     * Uses defaults only when request parameter is absent.
     *
     * @return array<string>
     */
    protected function getMergedRequestedIncludes(): array
    {
        $requested = $this->getParametersManager()->getIncludes()->all();

        if (! empty($requested)) {
            return array_values(array_unique($requested));
        }

        return array_values(array_unique($this->getEffectiveDefaultIncludes()));
    }

    /**
     * Build includes index.
     *
     * @param  array<IncludeInterface>  $includes
     * @return array<string, IncludeInterface>
     */
    protected function buildIncludesIndex(array $includes): array
    {
        $index = [];
        foreach ($includes as $include) {
            $index[$include->getName()] = $include;
        }

        return $index;
    }

    protected function validateIncludesLimit(int $count): void
    {
        $limit = $this->getConfig()->getMaxIncludesCount();
        if ($limit !== null && $count > $limit) {
            throw MaxIncludesCountExceeded::create($count, $limit);
        }
    }

    /**
     * Validate include depth based on relation name (not alias).
     *
     * This prevents bypassing depth limits by using a simple alias
     * for a deeply nested relation.
     */
    protected function validateIncludeDepth(IncludeInterface $include): void
    {
        $relation = $include->getRelation();
        $depth = substr_count($relation, '.') + 1;
        $limit = $this->getConfig()->getMaxIncludeDepth();
        if ($limit !== null && $depth > $limit) {
            throw MaxIncludeDepthExceeded::create($include->getName(), $depth, $limit);
        }
    }

    protected function invalidateIncludeCache(): void
    {
        $this->cachedEffectiveIncludes = null;
    }
}

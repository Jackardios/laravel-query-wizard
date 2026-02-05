<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;

/**
 * Shared include handling logic for query wizards.
 *
 * This trait contains methods used by both BaseQueryWizard
 * and ModelQueryWizard for handling includes functionality.
 *
 * Classes using this trait must provide:
 * - getConfig(): QueryWizardConfig
 * - getParametersManager(): QueryParametersManager
 * - getSchema(): ?ResourceSchemaInterface
 * - normalizeStringToInclude(string $name): IncludeInterface
 * - $allowedIncludes: array property
 * - $allowedIncludesExplicitlySet: bool property
 * - $disallowedIncludes: array property
 * - $defaultIncludes: array property
 */
trait HandlesIncludes
{
    /**
     * Get the configuration instance.
     */
    abstract protected function getConfig(): QueryWizardConfig;

    /**
     * Get the parameters manager.
     */
    abstract protected function getParametersManager(): QueryParametersManager;

    /**
     * Get the schema instance.
     */
    abstract protected function getSchema(): ?ResourceSchemaInterface;

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
        $includes = $this->allowedIncludesExplicitlySet
            ? $this->allowedIncludes
            : ($this->getSchema()?->includes($this) ?? []);

        // Single pass: normalize and filter disallowed
        $countSuffix = $this->getConfig()->getCountSuffix();
        $disallowed = $this->disallowedIncludes;
        $result = [];

        foreach ($includes as $include) {
            if (is_string($include)) {
                $include = $this->normalizeStringToInclude($include);
            }

            // For count includes without alias, auto-apply count suffix
            if ($include->getType() === 'count' && $include->getAlias() === null) {
                $include = $include->alias($include->getRelation().$countSuffix);
            }

            $name = $include->getName();

            // Skip if disallowed (check name, prefix match, and count suffix)
            if (! empty($disallowed) && $this->isNameDisallowed($name, $disallowed, $countSuffix)) {
                continue;
            }

            $result[] = $include;
        }

        return $result;
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
     * Get merged requested includes (defaults + request).
     *
     * @return array<string>
     */
    protected function getMergedRequestedIncludes(): array
    {
        $defaults = $this->getEffectiveDefaultIncludes();
        $requested = $this->getParametersManager()->getIncludes()->all();

        return array_unique(array_merge($defaults, $requested));
    }

    /**
     * Build includes index with implicit count includes.
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

        // Add implicit count includes for relationships
        $countSuffix = $this->getConfig()->getCountSuffix();
        foreach ($includes as $include) {
            if ($include->getType() === 'relationship') {
                $countName = $include->getRelation().$countSuffix;
                if (! isset($index[$countName])) {
                    $countInclude = $this->normalizeStringToInclude($countName);
                    $index[$countName] = $countInclude;
                }
            }
        }

        return $index;
    }
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Wizards\Concerns;

use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;

trait HandlesIncludes
{
    /** @var array<IncludeDefinitionInterface|string> */
    protected array $allowedIncludes = [];

    /** @var array<string> */
    protected array $defaultIncludes = [];

    protected bool $includesApplied = false;

    /**
     * Set allowed includes
     *
     * @param IncludeDefinitionInterface|string|array<IncludeDefinitionInterface|string> ...$includes
     */
    public function setAllowedIncludes(IncludeDefinitionInterface|string|array ...$includes): static
    {
        $this->allowedIncludes = $this->flattenDefinitions($includes);
        return $this;
    }

    /**
     * Set default includes
     *
     * @param string|array<string> ...$includes
     */
    public function setDefaultIncludes(string|array ...$includes): static
    {
        $this->defaultIncludes = $this->flattenStringArray($includes);
        return $this;
    }

    /**
     * Get effective includes (schema + context applied)
     *
     * @return array<IncludeDefinitionInterface>
     */
    protected function getEffectiveIncludes(): array
    {
        $includes = !empty($this->allowedIncludes)
            ? $this->allowedIncludes
            : ($this->schema?->includes() ?? []);

        $context = $this->resolveContext();
        if ($context !== null) {
            if ($context->getAllowedIncludes() !== null) {
                $includes = $context->getAllowedIncludes();
            }

            $disallowed = $context->getDisallowedIncludes();
            if (!empty($disallowed)) {
                $includes = $this->removeDisallowed($includes, $disallowed, fn($item) =>
                    $item instanceof IncludeDefinitionInterface ? $item->getName() : $item
                );
            }
        }

        return $this->normalizeIncludes($includes);
    }

    /**
     * Get effective default includes
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultIncludes(): array
    {
        $context = $this->resolveContext();
        if ($context?->getDefaultIncludes() !== null) {
            return $context->getDefaultIncludes();
        }

        return !empty($this->defaultIncludes)
            ? $this->defaultIncludes
            : ($this->schema?->defaultIncludes() ?? []);
    }

    /**
     * Apply includes to subject (for list queries)
     */
    protected function applyIncludes(): void
    {
        if ($this->includesApplied) {
            return;
        }

        if (!in_array('includes', $this->driver->capabilities(), true)) {
            $this->includesApplied = true;
            return;
        }

        $includes = $this->getEffectiveIncludes();
        $requestedIncludes = $this->parameters->getIncludes();
        $defaultIncludes = $this->getEffectiveDefaultIncludes();
        $countSuffix = $this->config->getCountSuffix();

        $includesIndex = [];
        foreach ($includes as $include) {
            $includesIndex[$include->getName()] = $include;

            $relation = $include->getRelation();
            if ($include->getType() === 'relationship') {
                if (str_contains($relation, '.')) {
                    $parts = explode('.', $relation);
                    $firstLevel = $parts[0];
                    if (!isset($includesIndex[$firstLevel])) {
                        $includesIndex[$firstLevel] = $this->driver->normalizeInclude($firstLevel);
                    }
                    $firstLevelCount = $firstLevel . $countSuffix;
                    if (!isset($includesIndex[$firstLevelCount])) {
                        $includesIndex[$firstLevelCount] = $this->driver->normalizeInclude($firstLevelCount);
                    }
                } else {
                    $countName = $relation . $countSuffix;
                    if (!isset($includesIndex[$countName])) {
                        $includesIndex[$countName] = $this->driver->normalizeInclude($countName);
                    }
                }
            }
        }

        $effectiveIncludes = collect($defaultIncludes)
            ->merge($requestedIncludes)
            ->unique()
            ->values();

        $allowedIncludeNames = array_keys($includesIndex);
        foreach ($effectiveIncludes as $includeName) {
            if (!isset($includesIndex[$includeName])) {
                throw InvalidIncludeQuery::includesNotAllowed(collect([$includeName]), collect($allowedIncludeNames));
            }
        }

        foreach ($effectiveIncludes as $includeName) {
            $include = $includesIndex[$includeName];
            $fields = $this->getFieldsByKey($include->getRelation()) ?? [];
            $this->subject = $this->driver->applyInclude($this->subject, $include, $fields);
        }

        $this->includesApplied = true;
    }

    /**
     * Normalize includes to IncludeDefinitionInterface
     *
     * @param array<IncludeDefinitionInterface|string|null> $includes
     * @return array<IncludeDefinitionInterface>
     */
    protected function normalizeIncludes(array $includes): array
    {
        return array_values(array_filter(array_map(
            fn($include) => $include !== null && $include !== '' ? $this->driver->normalizeInclude($include) : null,
            $includes
        )));
    }

    /**
     * Get fields for a specific key (for nested relations)
     *
     * @return array<string>|null
     */
    abstract public function getFieldsByKey(string $key): ?array;
}

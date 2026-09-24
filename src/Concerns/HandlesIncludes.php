<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use Jackardios\QueryWizard\Exceptions\MaxIncludeDepthExceeded;
use Jackardios\QueryWizard\Exceptions\MaxIncludesCountExceeded;
use Jackardios\QueryWizard\Support\EagerLoads;

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
                ? $this->getConfig()->getIncludeAliasSuffix($configKey, $include->getDefaultAliasSuffix())
                : null;
            $include = $include->withDefaultAlias($suffix);

            $name = $include->getName();

            if (! empty($disallowed) && $this->isIncludeDisallowed($include, $name, $disallowed)) {
                continue;
            }

            $result[] = $include;
        }

        return $this->cachedEffectiveIncludes = $result;
    }

    /**
     * A relationship include is also denied by its relation path, so an alias
     * can't load a disallowed relation. Count and exists includes only load
     * an aggregate and are matched by name alone.
     *
     * @param  array<string>  $disallowed
     */
    private function isIncludeDisallowed(IncludeInterface $include, string $name, array $disallowed): bool
    {
        if ($this->isNameDisallowed($name, $disallowed)) {
            return true;
        }

        return $include->getType() === 'relationship'
            && $include->getRelation() !== $name
            && $this->isNameDisallowed($include->getRelation(), $disallowed);
    }

    /**
     * Get effective default includes.
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultIncludes(): array
    {
        $defaults = ! empty($this->defaultIncludes)
            ? $this->defaultIncludes
            : ($this->getSchema()?->defaultIncludes($this) ?? []);

        return $this->normalizePublicPaths($defaults);
    }

    /**
     * Check if includes request parameter is completely absent (for defaults logic).
     */
    protected function isIncludesRequestEmpty(): bool
    {
        return ! $this->getParametersManager()->hasSimpleParameter('includes');
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
        $parameters = $this->getParametersManager();
        $requested = $parameters->getIncludes()->all();

        if ($parameters->hasSimpleParameter('includes')) {
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
            $index[$this->normalizePublicPath($include->getName())] = $include;
        }

        return $index;
    }

    /**
     * Validate the requested (or default) includes against the allowed ones.
     *
     * @return array{array<int, string>, array<string, IncludeInterface>}|null Null when there is nothing to apply
     */
    private function resolveIncludesToApply(): ?array
    {
        $includes = $this->getEffectiveIncludes();
        $requestedIncludes = $this->getMergedRequestedIncludes();
        $usingDefaults = $this->isIncludesRequestEmpty();

        $this->validateIncludesLimit(count($requestedIncludes));

        if (empty($includes) && ! empty($requestedIncludes)) {
            $defaults = $usingDefaults ? $this->getEffectiveDefaultIncludes() : [];
            $defaultsIndex = array_flip($defaults);
            $userOnlyIncludes = array_filter(
                $requestedIncludes,
                fn ($name) => ! isset($defaultsIndex[$name])
            );

            if (! empty($userOnlyIncludes) && ! $this->getConfig()->isInvalidIncludeQueryExceptionDisabled()) {
                throw InvalidIncludeQuery::includesNotAllowed(
                    collect($userOnlyIncludes),
                    collect([])
                );
            }

            return null;
        }

        if (empty($includes)) {
            return null;
        }

        $includesIndex = $this->buildIncludesIndex($includes);

        $defaults = $usingDefaults ? $this->getEffectiveDefaultIncludes() : [];
        $defaultsIndex = array_flip($defaults);

        $allowedIncludeNames = array_keys($includesIndex);
        $validRequestedIncludes = [];
        foreach ($requestedIncludes as $includeName) {
            if (! isset($includesIndex[$includeName])) {
                if (isset($defaultsIndex[$includeName])) {
                    continue;
                }

                if (! $this->getConfig()->isInvalidIncludeQueryExceptionDisabled()) {
                    throw InvalidIncludeQuery::includesNotAllowed(
                        collect([$includeName]),
                        collect($allowedIncludeNames)
                    );
                }

                continue;
            }

            $include = $includesIndex[$includeName];

            $this->validateIncludeDepth($include);
            $validRequestedIncludes[] = $includeName;
        }

        return [$validRequestedIncludes, $includesIndex];
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

    /**
     * Apply an include without letting it discard eager-load constraints by accident.
     *
     * When the include makes Laravel replace the constraint of a relation that is
     * already eager loaded (`with('posts.comments')`, `with('posts')`,
     * `with('posts:id')`), the previous constraint keeps running before the new
     * one. A constraint closure the include passes itself still replaces it.
     */
    protected function applyIncludeKeepingEagerLoads(IncludeInterface $include, mixed $subject): mixed
    {
        if (! $subject instanceof Builder && ! $subject instanceof Relation) {
            return $include->apply($subject);
        }

        return EagerLoads::preserving($subject, static fn ($subject): mixed => $include->apply($subject));
    }

    protected function invalidateIncludeCache(): void
    {
        $this->cachedEffectiveIncludes = null;
    }
}

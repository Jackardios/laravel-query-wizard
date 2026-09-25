<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\ProvidesRuntimeAttributes;
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

    protected bool $defaultIncludesExplicitlySet = false;

    /** @var array<IncludeInterface>|null */
    protected ?array $cachedEffectiveIncludes = null;

    /**
     * Normalize a string include to an IncludeInterface instance.
     *
     * @api
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
        $defaults = $this->defaultIncludesExplicitlySet
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

        if (! $usingDefaults) {
            $this->validateIncludesLimit(count($requestedIncludes));
        }

        if (empty($includes)) {
            if (! $usingDefaults && $requestedIncludes !== [] && ! $this->getConfig()->isInvalidIncludeQueryExceptionDisabled()) {
                throw InvalidIncludeQuery::includesNotAllowed(
                    collect($requestedIncludes),
                    collect([])
                );
            }

            return null;
        }

        $includesIndex = $this->buildIncludesIndex($includes);
        $allowedIncludeNames = array_keys($includesIndex);
        $validRequestedIncludes = [];
        foreach ($requestedIncludes as $includeName) {
            if (! isset($includesIndex[$includeName])) {
                if (! $usingDefaults && ! $this->getConfig()->isInvalidIncludeQueryExceptionDisabled()) {
                    throw InvalidIncludeQuery::includesNotAllowed(
                        collect([$includeName]),
                        collect($allowedIncludeNames)
                    );
                }

                continue;
            }

            $include = $includesIndex[$includeName];

            if ($usingDefaults) {
                $this->assertDefaultWithinLimit(
                    "The depth of default include `{$includeName}`",
                    substr_count($include->getRelation(), '.') + 1,
                    $this->getConfig()->getMaxIncludeDepth(),
                    'max_include_depth'
                );
            } else {
                $this->validateIncludeDepth($include);
            }

            $validRequestedIncludes[] = $includeName;
        }

        if ($usingDefaults) {
            $this->assertDefaultWithinLimit(
                'The number of default includes',
                count($validRequestedIncludes),
                $this->getConfig()->getMaxIncludesCount(),
                'max_includes_count'
            );
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
     *
     * @api
     */
    protected function applyIncludeKeepingEagerLoads(IncludeInterface $include, mixed $subject): mixed
    {
        if (! $subject instanceof Builder && ! $subject instanceof Relation) {
            return $include->apply($subject);
        }

        return EagerLoads::preserving($subject, static fn ($subject): mixed => $include->apply($subject));
    }

    /**
     * Attributes the given includes add to the models, grouped by the relation
     * path of the models that carry them ('' for the root).
     *
     * Each group maps a requested field name to the attribute it shows: the
     * attribute itself, and for count and exists includes also the include name.
     *
     * @param  array<int, string>  $includeNames
     * @param  array<string, IncludeInterface>  $includesIndex
     * @return array<string, array<string, string>>
     *
     * @api
     */
    protected function resolveRuntimeAttributesByOwner(array $includeNames, array $includesIndex): array
    {
        $attributesByOwner = [];

        foreach ($includeNames as $includeName) {
            $include = $includesIndex[$includeName] ?? null;

            if ($include instanceof ProvidesRuntimeAttributes) {
                $relation = $include->getRelation();
                $lastDot = strrpos($relation, '.');
                $owner = $lastDot === false ? '' : substr($relation, 0, $lastDot);

                foreach ($include->runtimeAttributes() as $attribute) {
                    $attributesByOwner[$owner][$this->normalizePublicPath($attribute)] = $attribute;
                }
            } elseif ($include !== null && in_array($include->getType(), ['count', 'exists'], true)) {
                $attribute = $this->resolveRuntimeAttributeNameForInclude($include);

                $attributesByOwner[''][$this->normalizePublicPath($includeName)] = $attribute;
                $attributesByOwner[''][$this->normalizePublicPath($attribute)] = $attribute;
            }
        }

        return $attributesByOwner;
    }

    /**
     * @api
     */
    protected function resolveRuntimeAttributeNameForInclude(IncludeInterface $include): string
    {
        $relation = str_replace('.', '_', Str::snake($include->getRelation()));

        return "{$relation}_{$include->getType()}";
    }

    protected function invalidateIncludeCache(): void
    {
        $this->cachedEffectiveIncludes = null;
    }
}

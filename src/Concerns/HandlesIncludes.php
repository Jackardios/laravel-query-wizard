<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Str;
use Jackardios\QueryWizard\Contracts\EagerLoadsRelation;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\ProvidesRuntimeAttributes;
use Jackardios\QueryWizard\Eloquent\Includes\CountInclude;
use Jackardios\QueryWizard\Eloquent\Includes\ExistsInclude;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use Jackardios\QueryWizard\Exceptions\MaxIncludeDepthExceeded;
use Jackardios\QueryWizard\Exceptions\MaxIncludesCountExceeded;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;

/**
 * Shared include handling logic for query wizards.
 *
 * @internal Wiring for the wizards: the public methods and `@api` members it gives them are supported through the
 *           wizards; using the trait in another class is not.
 */
trait HandlesIncludes
{
    use RequiresWizardContext;

    /** @var array<IncludeInterface|string> */
    protected array $allowedIncludes = [];

    protected bool $allowedIncludesExplicitlySet = false;

    /** @var array<IncludeInterface|string> */
    protected array $addedAllowedIncludes = [];

    /** @var array<string> */
    protected array $disallowedIncludes = [];

    /** @var array<string> */
    protected array $defaultIncludes = [];

    protected bool $defaultIncludesExplicitlySet = false;

    /** @var array<IncludeInterface>|null */
    protected ?array $cachedEffectiveIncludes = null;

    /**
     * Public names of the allowed includes that disallowedIncludes() removed.
     *
     * @var array<string, true>
     */
    private array $disallowedIncludeNames = [];

    /**
     * Normalize a string include to an IncludeInterface instance.
     *
     * @api
     */
    abstract protected function normalizeStringToInclude(string $name): IncludeInterface;

    /**
     * Set allowed includes, replacing the schema's and any earlier call; addAllowedIncludes() adds instead.
     *
     * @param  IncludeInterface|string|array<IncludeInterface|string>  ...$includes
     */
    public function allowedIncludes(IncludeInterface|string|array ...$includes): static
    {
        $this->invalidateBuild();
        $this->allowedIncludes = $this->flattenDefinitions($includes, IncludeInterface::class);
        $this->allowedIncludesExplicitlySet = true;
        $this->addedAllowedIncludes = [];

        return $this;
    }

    /**
     * Add to the allowed includes: the list set with allowedIncludes(), or the schema's when none was set.
     *
     * @param  IncludeInterface|string|array<IncludeInterface|string>  ...$includes
     */
    public function addAllowedIncludes(IncludeInterface|string|array ...$includes): static
    {
        $this->invalidateBuild();
        $this->addedAllowedIncludes = [...$this->addedAllowedIncludes, ...$this->flattenDefinitions($includes, IncludeInterface::class)];

        return $this;
    }

    /**
     * Disallow includes, including the schema's; repeated calls add to the list.
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedIncludes(string|array ...$names): static
    {
        $this->invalidateBuild();
        $this->disallowedIncludes = [...$this->disallowedIncludes, ...$this->flattenStringArray($names)];

        return $this;
    }

    /**
     * Set default includes.
     *
     * Replaces the schema defaults; call it without arguments for no defaults.
     *
     * @param  string|array<string>  ...$names
     */
    public function defaultIncludes(string|array ...$names): static
    {
        $this->invalidateBuild();
        $this->defaultIncludes = $this->flattenStringArray($names);
        $this->defaultIncludesExplicitlySet = true;

        return $this;
    }

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

        $includes = [
            ...($this->allowedIncludesExplicitlySet ? $this->allowedIncludes : ($this->readSchema(fn (ResourceSchemaInterface $schema): array => $schema->includes($this)) ?? [])),
            ...$this->addedAllowedIncludes,
        ];

        $disallowed = $this->disallowedIncludes;
        $result = [];
        $names = [];
        $this->disallowedIncludeNames = [];

        foreach ($includes as $include) {
            if (is_string($include)) {
                $include = $this->normalizeStringToInclude($include);
            }

            $include = $this->withDefaultAggregateAlias($include);

            $name = $include->getName();

            if (! empty($disallowed) && $this->isIncludeDisallowed($include, $name, $disallowed)) {
                $this->disallowedIncludeNames[$this->normalizePublicPath($name)] = true;

                continue;
            }

            $normalizedName = $this->normalizePublicPath($name);
            $this->assertUniqueDefinitionName('include', $normalizedName, isset($names[$normalizedName]));
            $names[$normalizedName] = true;
            $result[] = $include;
        }

        return $this->cachedEffectiveIncludes = $result;
    }

    /**
     * The effective includes plus the default includes they don't define.
     *
     * Defaults come from the developer, so while the request names no
     * includes they apply without being allowed; one that disallowedIncludes()
     * denies is a configuration error.
     *
     * @return array<IncludeInterface>
     *
     * @throws \InvalidArgumentException When a default include is disallowed
     */
    protected function getIncludesInUse(): array
    {
        $includes = $this->getEffectiveIncludes();

        if (! $this->isIncludesRequestEmpty()) {
            return $includes;
        }

        $index = $this->buildIncludesIndex($includes);

        foreach ($this->getEffectiveDefaultIncludes() as $name) {
            if (isset($index[$name])) {
                continue;
            }

            $include = $this->normalizeStringToInclude($name);

            if (isset($this->disallowedIncludeNames[$name])
                || ($this->disallowedIncludes !== [] && $this->isIncludeDisallowed($include, $name, $this->disallowedIncludes))) {
                throw new \InvalidArgumentException("Default include `{$name}` is disallowed by disallowedIncludes().");
            }

            $index[$name] = $include;
            $includes[] = $include;
        }

        return $includes;
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

        return $include instanceof EagerLoadsRelation
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
            : ($this->readSchema(fn (ResourceSchemaInterface $schema): array => $schema->defaultIncludes($this)) ?? []);

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
        $includes = $this->getIncludesInUse();
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
            throw new MaxIncludesCountExceeded($count, $limit);
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
            throw new MaxIncludeDepthExceeded($include->getName(), $depth, $limit);
        }
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
            } elseif ($include instanceof CountInclude || $include instanceof ExistsInclude) {
                $attribute = $this->resolveRuntimeAttributeNameForInclude($include);

                $attributesByOwner[''][$this->normalizePublicPath($includeName)] = $attribute;
                $attributesByOwner[''][$this->normalizePublicPath($attribute)] = $attribute;
            }
        }

        return $attributesByOwner;
    }

    protected function resolveRuntimeAttributeNameForInclude(CountInclude|ExistsInclude $include): string
    {
        $relation = str_replace('.', '_', Str::snake($include->getRelation()));

        return $relation.($include instanceof CountInclude ? '_count' : '_exists');
    }

    /**
     * Name a count or exists include without an alias after its relation and
     * the configured suffix (`postsCount`), leaving the definition itself unchanged.
     */
    private function withDefaultAggregateAlias(IncludeInterface $include): IncludeInterface
    {
        if ($include->getAlias() !== null) {
            return $include;
        }

        $suffix = match (true) {
            $include instanceof CountInclude => $this->getConfig()->getCountSuffix(),
            $include instanceof ExistsInclude => $this->getConfig()->getExistsSuffix(),
            default => null,
        };

        return $suffix === null ? $include : (clone $include)->alias($include->getRelation().$suffix);
    }

    protected function invalidateIncludeCache(): void
    {
        $this->cachedEffectiveIncludes = null;
    }
}

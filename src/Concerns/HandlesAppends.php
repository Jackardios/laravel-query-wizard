<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Exceptions\MaxAppendDepthExceeded;
use Jackardios\QueryWizard\Exceptions\MaxAppendsCountExceeded;
use Jackardios\QueryWizard\Support\DotNotationTreeBuilder;
use Jackardios\QueryWizard\Support\NamePolicy;

/**
 * Shared append handling logic for query wizards.
 *
 * @internal Wiring for the wizards: the public methods and `@api` members it gives them are supported through the
 *           wizards; using the trait in another class is not.
 */
trait HandlesAppends
{
    use HandlesRelationAttributeValidation;
    use RequiresWizardContext;

    /** @var array<string> */
    protected array $allowedAppends = [];

    protected bool $allowedAppendsExplicitlySet = false;

    /** @var array<string> */
    protected array $addedAllowedAppends = [];

    /** @var array<string> */
    protected array $disallowedAppends = [];

    /** @var array<string> */
    protected array $defaultAppends = [];

    protected bool $defaultAppendsExplicitlySet = false;

    /**
     * @return array<IncludeInterface>
     */
    abstract protected function getIncludesInUse(): array;

    /**
     * Get effective requested includes (defaults only when request absent).
     *
     * @return array<string>
     */
    abstract protected function getMergedRequestedIncludes(): array;

    /**
     * Set allowed appends, replacing the schema's and any earlier call; addAllowedAppends() adds instead.
     *
     * @param  string|array<string>  ...$appends
     */
    public function allowedAppends(string|array ...$appends): static
    {
        $this->invalidateBuild();
        $this->allowedAppends = $this->flattenStringArray($appends);
        $this->allowedAppendsExplicitlySet = true;
        $this->addedAllowedAppends = [];

        return $this;
    }

    /**
     * Add to the allowed appends: the list set with allowedAppends(), or the schema's when none was set.
     *
     * @param  string|array<string>  ...$appends
     */
    public function addAllowedAppends(string|array ...$appends): static
    {
        $this->invalidateBuild();
        $this->addedAllowedAppends = [...$this->addedAllowedAppends, ...$this->flattenStringArray($appends)];

        return $this;
    }

    /**
     * Disallow appends, including the schema's; repeated calls add to the list.
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedAppends(string|array ...$names): static
    {
        $this->invalidateBuild();
        $this->disallowedAppends = [...$this->disallowedAppends, ...$this->flattenStringArray($names)];

        return $this;
    }

    /**
     * Set default appends.
     *
     * Replaces the schema defaults; call it without arguments for no defaults.
     *
     * @param  string|array<string>  ...$appends
     */
    public function defaultAppends(string|array ...$appends): static
    {
        $this->invalidateBuild();
        $this->defaultAppends = $this->flattenStringArray($appends);
        $this->defaultAppendsExplicitlySet = true;

        return $this;
    }

    /**
     * Build append tree from grouped format.
     *
     * @param  array<string, array<string>>  $grouped  Grouped appends ['relation.path' => ['append1', 'append2']]
     * @return array{appends: array<string>, relations: array<string, mixed>}
     */
    protected function buildAppendTree(array $grouped): array
    {
        /** @var array{appends: array<string>, relations: array<string, mixed>} */
        return DotNotationTreeBuilder::build($grouped, 'appends');
    }

    /**
     * Get valid requested appends as tree structure.
     *
     * @return array{appends: array<string>, relations: array<string, mixed>}
     */
    protected function getValidRequestedAppendsTree(): array
    {
        $parameters = $this->getParametersManager();
        $requestedAppends = $parameters->getAppends();

        $useDefaults = ! $parameters->hasSimpleParameter('appends');
        if ($useDefaults) {
            $grouped = $this->parseDefaultAppendsToGrouped();
        } else {
            $grouped = $requestedAppends->all();
        }

        if (empty($grouped)) {
            return ['appends' => [], 'relations' => []];
        }

        $allowed = $this->getEffectiveAppends();
        $includeNameToPathMap = $this->buildIncludeNameToPathMap($this->getIncludesInUse());
        $includedRelationPaths = $this->getIncludedRelationPaths(
            $this->getMergedRequestedIncludes(),
            $includeNameToPathMap
        );

        $maxDepth = $this->getConfig()->getMaxAppendDepth();
        $exceptionsDisabled = $this->getConfig()->isInvalidAppendQueryExceptionDisabled();
        $validGrouped = [];

        foreach ($grouped as $key => $attributes) {
            $key = (string) $key;

            if ($key === '') {
                $valid = $useDefaults
                    ? $this->trustedDefaultAppends($key, $attributes)
                    : $this->filterValidAttributes($key, $attributes, $allowed, true, $exceptionsDisabled, '');
                if (! empty($valid)) {
                    $validGrouped[''] = $valid;
                }

                continue;
            }

            if (empty($attributes)) {
                continue;
            }

            // Requested relation appends are validated even when the relation is
            // not loaded; they only apply to a loaded relation.
            $relationPath = $includeNameToPathMap[$key] ?? null;
            $loaded = $relationPath !== null && isset($includedRelationPaths[$relationPath]);

            if ($useDefaults && ! $loaded) {
                continue;
            }

            // Validate depth (based on relation path, not alias)
            $depth = substr_count($relationPath ?? $key, '.') + 2;
            if ($useDefaults) {
                $this->assertDefaultWithinLimit(
                    "The depth of default append `{$relationPath}.{$attributes[0]}`",
                    $depth,
                    $maxDepth,
                    'max_append_depth'
                );
            } elseif ($maxDepth !== null && $depth > $maxDepth) {
                throw new MaxAppendDepthExceeded(($relationPath ?? $key).".{$attributes[0]}", $depth, $maxDepth);
            }

            // Validate using the request key (include name/alias), not the relation path
            // This ensures allowedAppends(['related.formattedName']) works when include has alias 'related'
            $valid = $useDefaults
                ? $this->trustedDefaultAppends($key, $attributes)
                : $this->filterValidAttributes($key, $attributes, $allowed, true, $exceptionsDisabled, $relationPath);

            if ($loaded && ! empty($valid)) {
                $validGrouped[$relationPath] = array_values(array_unique(array_merge($validGrouped[$relationPath] ?? [], $valid)));
            }
        }

        $appendsCount = array_sum(array_map('count', $validGrouped));
        if ($useDefaults) {
            $this->assertDefaultWithinLimit(
                'The number of default appends',
                $appendsCount,
                $this->getConfig()->getMaxAppendsCount(),
                'max_appends_count'
            );
        } else {
            $this->validateAppendsLimit($appendsCount);
        }

        return $this->buildAppendTree($validGrouped);
    }

    /**
     * Default appends come from the developer, so they apply without being allowed.
     *
     * @param  string  $path  Include name ('' for the root)
     * @param  array<string>  $attributes
     * @return array<string>
     *
     * @throws \InvalidArgumentException When a default append is a pattern or is disallowed
     */
    private function trustedDefaultAppends(string $path, array $attributes): array
    {
        $denyPolicy = $this->disallowedAppends === [] ? null : $this->denyPolicyFor($this->disallowedAppends);

        foreach ($attributes as $attr) {
            $name = $path !== '' ? "{$path}.{$attr}" : $attr;

            if (str_contains($attr, '*')) {
                throw new \InvalidArgumentException("Default append `{$name}` must name an attribute, not a pattern.");
            }

            if ($denyPolicy !== null && $denyPolicy->denies($this->normalizePublicPath($name))) {
                throw new \InvalidArgumentException("Default append `{$name}` is disallowed by disallowedAppends().");
            }
        }

        return array_values($attributes);
    }

    /**
     * Filter attributes by allowed list.
     *
     * @param  string  $path  Relation path ('' for root)
     * @param  array<string>  $attributes
     * @param  array<string>  $allowed
     * @param  bool  $canThrow  Whether to throw exceptions for invalid attributes
     * @param  string|null  $modelPath  Relation path of the model carrying the attributes ('' for the
     *                                  root), whose accessors back attributes a wildcard allows; null skips that check
     * @return array<string>
     */
    protected function filterValidAttributes(
        string $path,
        array $attributes,
        array $allowed,
        bool $canThrow,
        bool $exceptionsDisabled,
        ?string $modelPath = null
    ): array {
        $policy = NamePolicy::allowing($allowed);
        $denyPolicy = $this->disallowedAppends === [] ? null : $this->denyPolicyFor($this->disallowedAppends);
        $valid = [];
        $invalid = [];
        $disallowedFound = false;
        $model = false;

        foreach ($attributes as $attr) {
            $name = $path !== '' ? "{$path}.{$attr}" : $attr;

            if (str_contains($attr, '*') || ! mb_check_encoding($attr, 'UTF-8')) {
                if ($canThrow) {
                    $invalid[] = $name;
                }

                continue;
            }

            if ($modelPath !== null && $policy->allowsAttribute($path, $attr) && ! $policy->allowsAttributeByName($path, $attr)) {
                $model = $model === false ? $this->resolveAppendAccessorModel($modelPath) : $model;

                if ($model !== null && ! $model->hasGetMutator($attr) && ! $model->hasAttributeGetMutator($attr)) {
                    if ($canThrow) {
                        $invalid[] = $name;
                    }

                    continue;
                }
            }

            if (! $policy->allowsAttribute($path, $attr)) {
                if ($canThrow) {
                    $invalid[] = $name;
                }
            } elseif ($denyPolicy !== null && $denyPolicy->denies($this->normalizePublicPath($name))) {
                if ($canThrow) {
                    $invalid[] = $name;
                    $disallowedFound = true;
                }
            } else {
                $valid[] = $attr;
            }
        }

        if (! empty($invalid) && ! $exceptionsDisabled) {
            if (! $disallowedFound) {
                throw InvalidAppendQuery::appendsNotAllowed(collect($invalid), collect($allowed));
            }

            $joinedAppends = implode(', ', $invalid);

            throw new InvalidAppendQuery(collect($invalid), collect($allowed), "Requested append(s) `{$joinedAppends}` are not allowed.");
        }

        return $valid;
    }

    /**
     * The model whose accessors back appends at a relation path ('' for the root).
     *
     * An append that only a wildcard allows must name an accessor of this model,
     * so `allowedAppends('*')` can't reach columns or unknown names, and a field
     * only a wildcard allows must not name one of its hidden attributes in
     * another letter case. Null skips both checks.
     *
     * @api
     */
    protected function resolveAppendAccessorModel(string $relationPath): ?Model
    {
        return null;
    }

    /**
     * Parse default appends (dot notation) to grouped format.
     *
     * @return array<string, array<string>>
     */
    protected function parseDefaultAppendsToGrouped(): array
    {
        $grouped = [];

        foreach ($this->getEffectiveDefaultAppends() as $append) {
            $lastDot = strrpos($append, '.');
            $path = $lastDot !== false ? substr($append, 0, $lastDot) : '';
            $name = $lastDot !== false ? substr($append, $lastDot + 1) : $append;
            $grouped[$path][] = $name;
        }

        return $grouped;
    }

    /**
     * Validate appends count limit.
     */
    protected function validateAppendsLimit(int $count): void
    {
        $limit = $this->getConfig()->getMaxAppendsCount();
        if ($limit !== null && $count > $limit) {
            throw new MaxAppendsCountExceeded($count, $limit);
        }
    }

    /**
     * Get effective appends.
     *
     * If allowedAppends() was called explicitly, use those (even if empty).
     * Otherwise, fall back to schema appends (if any).
     * Empty result means all appends are forbidden.
     *
     * @return array<string>
     */
    protected function getEffectiveAppends(): array
    {
        $appends = [
            ...($this->allowedAppendsExplicitlySet ? $this->allowedAppends : ($this->getSchema()?->appends($this) ?? [])),
            ...$this->addedAllowedAppends,
        ];

        return $this->removeDisallowedStrings(
            array_values(array_unique($this->normalizePublicPaths($appends))),
            $this->disallowedAppends
        );
    }

    /**
     * Get effective default appends.
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultAppends(): array
    {
        $defaults = $this->defaultAppendsExplicitlySet
            ? $this->defaultAppends
            : ($this->getSchema()?->defaultAppends($this) ?? []);

        return $this->normalizePublicPaths($defaults);
    }
}

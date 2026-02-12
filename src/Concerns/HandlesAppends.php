<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Exceptions\MaxAppendDepthExceeded;
use Jackardios\QueryWizard\Exceptions\MaxAppendsCountExceeded;
use Jackardios\QueryWizard\Support\DotNotationTreeBuilder;

/**
 * Shared append handling logic for query wizards.
 */
trait HandlesAppends
{
    use HandlesRelationAttributeValidation;
    use RequiresWizardContext;

    /** @var array<string> */
    protected array $allowedAppends = [];

    protected bool $allowedAppendsExplicitlySet = false;

    /** @var array<string> */
    protected array $disallowedAppends = [];

    /** @var array<string> */
    protected array $defaultAppends = [];

    /**
     * @return array<IncludeInterface>
     */
    abstract protected function getEffectiveIncludes(): array;

    /**
     * Get effective requested includes (defaults only when request absent).
     *
     * @return array<string>
     */
    abstract protected function getMergedRequestedIncludes(): array;

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
        $requestedAppends = $this->getParametersManager()->getAppends();

        $useDefaults = $requestedAppends->isEmpty();
        if ($useDefaults) {
            $grouped = $this->parseDefaultAppendsToGrouped();
        } else {
            $grouped = $requestedAppends->all();
        }

        if (empty($grouped)) {
            return ['appends' => [], 'relations' => []];
        }

        $allowed = $this->getEffectiveAppends();
        $effectiveIncludes = $this->getEffectiveIncludes();
        $includeNameToPathMap = $this->buildIncludeNameToPathMap($effectiveIncludes);
        $includedRelationPaths = $this->getIncludedRelationPaths(
            $this->getMergedRequestedIncludes(),
            $includeNameToPathMap
        );

        $maxDepth = $this->getConfig()->getMaxAppendDepth();
        $exceptionsDisabled = $this->getConfig()->isInvalidAppendQueryExceptionDisabled();
        $allowedRelationAppendList = $this->extractRelationAttributes($allowed);

        $validGrouped = [];

        foreach ($grouped as $key => $attributes) {
            $key = (string) $key;

            if ($key === '') {
                $valid = $this->filterValidAttributes(
                    $key,
                    $attributes,
                    $allowed,
                    ! $useDefaults,
                    $exceptionsDisabled
                );
                if (! empty($valid)) {
                    $validGrouped[''] = $valid;
                }

                continue;
            }

            $relationPath = $includeNameToPathMap[$key] ?? null;
            if ($relationPath === null) {
                if (
                    ! $useDefaults
                    && ! $exceptionsDisabled
                    && ! empty($includeNameToPathMap)
                ) {
                    throw InvalidAppendQuery::appendsNotAllowed(
                        collect($this->prefixGroupAttributes($key, $attributes)),
                        collect($allowedRelationAppendList)
                    );
                }

                continue;
            }

            if (! isset($includedRelationPaths[$relationPath])) {
                continue;
            }

            // Validate depth (based on relation path, not alias)
            $depth = substr_count($relationPath, '.') + 2;
            if ($maxDepth !== null && $depth > $maxDepth) {
                throw MaxAppendDepthExceeded::create("{$relationPath}.{$attributes[0]}", $depth, $maxDepth);
            }

            // Validate using the request key (include name/alias), not the relation path
            // This ensures allowedAppends(['related.formattedName']) works when include has alias 'related'
            $valid = $this->filterValidAttributes(
                $key,
                $attributes,
                $allowed,
                ! $useDefaults,
                $exceptionsDisabled
            );
            if (! empty($valid)) {
                $validGrouped[$relationPath] = $valid;
            }
        }

        $this->validateAppendsLimit(array_sum(array_map('count', $validGrouped)));

        return $this->buildAppendTree($validGrouped);
    }

    /**
     * Filter attributes by allowed list.
     *
     * @param  string  $path  Relation path ('' for root)
     * @param  array<string>  $attributes
     * @param  array<string>  $allowed
     * @param  bool  $canThrow  Whether to throw exceptions for invalid attributes
     * @return array<string>
     */
    protected function filterValidAttributes(
        string $path,
        array $attributes,
        array $allowed,
        bool $canThrow,
        bool $exceptionsDisabled
    ): array {
        $valid = [];
        $invalid = [];

        foreach ($attributes as $attr) {
            if ($this->isAttributeAllowed($path, $attr, $allowed)) {
                $valid[] = $attr;
            } elseif ($canThrow) {
                $invalid[] = $path !== '' ? "{$path}.{$attr}" : $attr;
            }
        }

        if (! empty($invalid) && ! $exceptionsDisabled) {
            throw InvalidAppendQuery::appendsNotAllowed(collect($invalid), collect($allowed));
        }

        return $valid;
    }

    /**
     * Extract relation-level attributes from allowed list (for error messages).
     *
     * @param  array<string>  $allowed
     * @return array<string>
     */
    protected function extractRelationAttributes(array $allowed): array
    {
        return array_values(array_filter(
            $allowed,
            static fn (string $value): bool => str_contains($value, '.')
        ));
    }

    /**
     * @param  array<string>  $attributes
     * @return array<string>
     */
    protected function prefixGroupAttributes(string $group, array $attributes): array
    {
        return array_map(
            static fn (string $attribute): string => $group.'.'.$attribute,
            $attributes
        );
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
            throw MaxAppendsCountExceeded::create($count, $limit);
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
        $appends = $this->allowedAppendsExplicitlySet
            ? $this->allowedAppends
            : ($this->getSchema()?->appends($this) ?? []);

        return $this->removeDisallowedStrings($appends, $this->disallowedAppends);
    }

    /**
     * Get effective default appends.
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultAppends(): array
    {
        return ! empty($this->defaultAppends)
            ? $this->defaultAppends
            : ($this->getSchema()?->defaultAppends($this) ?? []);
    }
}

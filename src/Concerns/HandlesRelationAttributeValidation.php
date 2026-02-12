<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Jackardios\QueryWizard\Contracts\IncludeInterface;

/**
 * Shared validation logic for relation attributes (appends, fields).
 */
trait HandlesRelationAttributeValidation
{
    /**
     * Build map of include names to relation paths.
     *
     * For nested includes like 'posts.comments', this also adds intermediate
     * segments ('posts' => 'posts') so that fields/appends can be specified
     * for any level of the include path.
     *
     * When an include has an alias (e.g., 'posts.comments' aliased to 'pc'),
     * intermediate segments are still added by their relation path names,
     * because Laravel loads intermediate relations regardless of aliasing.
     *
     * @param  array<IncludeInterface>  $effectiveIncludes
     * @return array<string, string> ['includeName' => 'relation.path']
     */
    protected function buildIncludeNameToPathMap(array $effectiveIncludes): array
    {
        $map = [];
        foreach ($effectiveIncludes as $include) {
            if ($include->getType() !== 'relationship') {
                continue;
            }

            $name = $include->getName();
            $relation = $include->getRelation();

            $map[$name] = $relation;

            $relationParts = explode('.', $relation);
            if (count($relationParts) > 1) {
                $intermediatePath = '';
                foreach (array_slice($relationParts, 0, -1) as $part) {
                    $intermediatePath = $intermediatePath !== '' ? "{$intermediatePath}.{$part}" : $part;
                    if (! isset($map[$intermediatePath])) {
                        $map[$intermediatePath] = $intermediatePath;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Check if attribute is allowed using non-recursive wildcard logic.
     *
     * @param  string  $path  Relation path ('' for root)
     * @param  string  $attribute  Attribute name
     * @param  array<string>  $allowed  Allowed list with wildcards
     */
    protected function isAttributeAllowed(string $path, string $attribute, array $allowed): bool
    {
        $fullPath = $path !== '' ? "{$path}.{$attribute}" : $attribute;
        if (in_array($fullPath, $allowed, true)) {
            return true;
        }

        // Check wildcard for this level ONLY (non-recursive)
        $wildcardPattern = $path !== '' ? "{$path}.*" : '*';

        return in_array($wildcardPattern, $allowed, true);
    }

    /**
     * Get set of relation paths that are actually being included.
     *
     * For nested includes, this also includes intermediate segments.
     * e.g., for 'posts.comments' being included, both 'posts.comments' and 'posts'
     * are considered "included" for the purpose of appends/fields.
     *
     * @param  array<string>  $requestedIncludeNames  Names from request (merged with defaults)
     * @param  array<string, string>  $includeNameToPathMap
     * @return array<string, true>
     */
    protected function getIncludedRelationPaths(
        array $requestedIncludeNames,
        array $includeNameToPathMap
    ): array {
        $paths = [];
        foreach ($requestedIncludeNames as $name) {
            if (isset($includeNameToPathMap[$name])) {
                $relationPath = $includeNameToPathMap[$name];
                $paths[$relationPath] = true;

                $parts = explode('.', $relationPath);
                if (count($parts) > 1) {
                    $intermediatePath = '';
                    foreach (array_slice($parts, 0, -1) as $part) {
                        $intermediatePath = $intermediatePath !== '' ? "{$intermediatePath}.{$part}" : $part;
                        $paths[$intermediatePath] = true;
                    }
                }
            }
        }

        return $paths;
    }
}

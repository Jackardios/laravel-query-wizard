<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

/**
 * Builds nested tree structures from grouped dot-notation data.
 *
 * @internal
 */
final class DotNotationTreeBuilder
{
    /**
     * Build nested tree from grouped dot-notation data.
     *
     * @param  array<string, array<string>>  $grouped  ['relation.path' => ['value1', 'value2']]
     * @param  string  $leafKey  Key name for leaf values ('fields', 'appends')
     * @return array<string, mixed>
     */
    public static function build(array $grouped, string $leafKey = 'values'): array
    {
        $rootValues = [];
        $relations = [];

        foreach ($grouped as $path => $values) {
            if ($path === '') {
                $rootValues = array_merge($rootValues, $values);

                continue;
            }

            $relations = self::insert($relations, explode('.', (string) $path), $values, $leafKey);
        }

        return [$leafKey => $rootValues, 'relations' => $relations];
    }

    /**
     * @param  array<string, mixed>  $relations
     * @param  list<string>  $segments
     * @param  array<string>  $values
     * @return array<string, mixed>
     */
    private static function insert(array $relations, array $segments, array $values, string $leafKey): array
    {
        $segment = array_shift($segments);

        if ($segment === null) {
            return $relations;
        }

        $node = $relations[$segment] ?? null;
        $nodeValues = is_array($node) && is_array($node[$leafKey] ?? null) ? $node[$leafKey] : [];
        $nodeRelations = is_array($node) && is_array($node['relations'] ?? null) ? $node['relations'] : [];

        if ($segments === []) {
            $nodeValues = self::mergeValues($nodeValues, $values);
        } else {
            $nodeRelations = self::insert($nodeRelations, $segments, $values, $leafKey);
        }

        $relations[$segment] = [$leafKey => $nodeValues, 'relations' => $nodeRelations];

        return $relations;
    }

    /**
     * @param  array<mixed>  $existingValues
     * @param  array<string>  $values
     * @return array<mixed>
     */
    private static function mergeValues(array $existingValues, array $values): array
    {
        if (in_array('*', $values, true)) {
            return ['*'];
        }

        if (in_array('*', $existingValues, true)) {
            return $existingValues;
        }

        $existingIndex = [];
        foreach ($existingValues as $value) {
            if (is_string($value)) {
                $existingIndex[$value] = true;
            }
        }

        foreach ($values as $value) {
            if (! isset($existingIndex[$value])) {
                $existingValues[] = $value;
                $existingIndex[$value] = true;
            }
        }

        return $existingValues;
    }
}

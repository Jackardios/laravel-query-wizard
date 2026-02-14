<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

/**
 * Builds nested tree structures from grouped dot-notation data.
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
        /** @var array<string, mixed> $tree */
        $tree = [$leafKey => [], 'relations' => []];

        foreach ($grouped as $path => $values) {
            if ($path === '') {
                $tree[$leafKey] = array_merge($tree[$leafKey], $values);

                continue;
            }

            $segments = explode('.', (string) $path);

            /** @var array<string, mixed> $node */
            $node = &$tree;

            foreach ($segments as $segment) {
                if (! isset($node['relations'][$segment])) {
                    $node['relations'][$segment] = [$leafKey => [], 'relations' => []];
                }
                /** @var array<string, mixed> $node */
                $node = &$node['relations'][$segment];
            }

            /** @var array<string> $existingValues */
            $existingValues = $node[$leafKey] ?? [];

            if (in_array('*', $values, true)) {
                $node[$leafKey] = ['*'];
            } elseif (! in_array('*', $existingValues, true)) {
                $existingIndex = array_flip($existingValues);
                foreach ($values as $value) {
                    if (! isset($existingIndex[$value])) {
                        $existingValues[] = $value;
                        $existingIndex[$value] = true;
                    }
                }
                $node[$leafKey] = $existingValues;
            }

            unset($node);
        }

        return $tree;
    }
}

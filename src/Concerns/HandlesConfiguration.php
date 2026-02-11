<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

/**
 * Shared configuration handling methods for query wizards.
 *
 * This trait contains utility methods used by both BaseQueryWizard
 * and ModelQueryWizard for handling configuration arrays and definitions.
 */
trait HandlesConfiguration
{
    use RequiresWizardContext;

    /**
     * Flatten definitions array (handle variadic with nested arrays).
     *
     * @template T
     *
     * @param  array<array-key, T|array<array-key, T>>  $items
     * @return array<int, T>
     */
    protected function flattenDefinitions(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                foreach ($item as $i) {
                    if ($i !== null && $i !== '' && $i !== []) {
                        $result[] = $i;
                    }
                }
            } elseif ($item !== null && $item !== '') {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Flatten string array (handle variadic with nested arrays).
     *
     * @param  array<string|array<string>>  $items
     * @return array<string>
     */
    protected function flattenStringArray(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                foreach ($item as $i) {
                    if (is_string($i)) {
                        $result[] = $i;
                    }
                }
            } elseif (is_string($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Remove disallowed strings from array.
     *
     * @param  array<string>  $items
     * @param  array<string>  $disallowed
     * @return array<string>
     */
    protected function removeDisallowedStrings(array $items, array $disallowed): array
    {
        if (empty($disallowed)) {
            return $items;
        }

        return array_values(array_filter($items, function (string $item) use ($disallowed) {
            return ! $this->isNameDisallowed($item, $disallowed);
        }));
    }

    /**
     * Check if a name is disallowed.
     *
     * Supports wildcards:
     * - '*' blocks everything
     * - 'relation.*' blocks direct children (non-recursive)
     * - 'relation' blocks relation and all descendants (prefix match)
     *
     * @param  array<string>  $disallowed
     */
    protected function isNameDisallowed(string $name, array $disallowed): bool
    {
        if (in_array('*', $disallowed, true)) {
            return true;
        }

        foreach ($disallowed as $d) {
            if ($name === $d) {
                return true;
            }

            if (str_starts_with($name, $d.'.')) {
                return true;
            }

            if (str_ends_with($d, '.*')) {
                $prefix = substr($d, 0, -2);
                if (str_starts_with($name, $prefix.'.')) {
                    $suffix = substr($name, strlen($prefix) + 1);
                    if (! str_contains($suffix, '.')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if array is associative.
     *
     * @param  array<mixed>  $array
     */
    protected function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}

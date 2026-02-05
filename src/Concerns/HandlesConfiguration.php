<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Jackardios\QueryWizard\Config\QueryWizardConfig;

/**
 * Shared configuration handling methods for query wizards.
 *
 * This trait contains utility methods used by both BaseQueryWizard
 * and ModelQueryWizard for handling configuration arrays and definitions.
 */
trait HandlesConfiguration
{
    /**
     * Get the configuration instance.
     */
    abstract protected function getConfig(): QueryWizardConfig;

    /**
     * Flatten definitions array (handle variadic with nested arrays).
     *
     * @template T
     * @param array<array-key, T|array<array-key, T>> $items
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
     * @param array<string|array<string>> $items
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
     * @param array<string> $items
     * @param array<string> $disallowed
     * @return array<string>
     */
    protected function removeDisallowedStrings(array $items, array $disallowed): array
    {
        if (empty($disallowed)) {
            return $items;
        }

        return array_values(array_filter($items, function (string $item) use ($disallowed) {
            return !$this->isNameDisallowed($item, $disallowed);
        }));
    }

    /**
     * Remove disallowed items from array by name.
     *
     * @template T
     * @param array<T> $items
     * @param array<string> $disallowed
     * @param callable(T): string $getName
     * @param string|null $countSuffix Optional suffix for count variants (e.g., 'Count')
     * @return array<T>
     */
    protected function removeDisallowedByName(
        array $items,
        array $disallowed,
        callable $getName,
        ?string $countSuffix = null
    ): array {
        if (empty($disallowed)) {
            return $items;
        }

        return array_values(array_filter($items, function ($item) use ($disallowed, $getName, $countSuffix) {
            $name = $getName($item);
            return !$this->isNameDisallowed($name, $disallowed, $countSuffix);
        }));
    }

    /**
     * Check if a name is disallowed.
     *
     * @param array<string> $disallowed
     */
    protected function isNameDisallowed(string $name, array $disallowed, ?string $countSuffix = null): bool
    {
        foreach ($disallowed as $d) {
            if ($name === $d || str_starts_with($name, $d . '.')) {
                return true;
            }
            if ($countSuffix !== null && $name === $d . $countSuffix) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if array is associative.
     *
     * @param array<mixed> $array
     */
    protected function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }
}

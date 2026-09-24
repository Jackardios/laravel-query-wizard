<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Str;
use Jackardios\QueryWizard\Support\NameConverter;
use Jackardios\QueryWizard\Support\NamePolicy;

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
     * Resolve the default sparse-fieldset resource key.
     *
     * The schema type wins when a schema is set, otherwise the model's short class
     * name is used. The result is always normalized so it lines up with the public
     * parameter naming convention.
     *
     * @param  object|class-string  $model
     */
    protected function resolveDefaultResourceKey(object|string $model): string
    {
        $type = $this->getSchema()?->type();

        return $this->normalizePublicName($type ?? Str::camel(class_basename($model)));
    }

    protected function normalizePublicName(string $name): string
    {
        if ($name === '' || $name === '*' || ! $this->shouldNormalizePublicInput()) {
            return $name;
        }

        return NameConverter::toSnakeCase($name);
    }

    protected function normalizePublicPath(string $path): string
    {
        if ($path === '' || $path === '*' || ! $this->shouldNormalizePublicInput()) {
            return $path;
        }

        $prefix = '';
        if (str_starts_with($path, '-')) {
            $prefix = '-';
            $path = substr($path, 1);
        }

        return $prefix.NameConverter::pathToSnakeCase($path);
    }

    protected function shouldNormalizePublicInput(): bool
    {
        try {
            return $this->getConfig()->shouldConvertParametersToSnakeCase();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string>  $names
     * @return array<string>
     */
    protected function normalizePublicNames(array $names): array
    {
        return array_values(array_map(
            fn (string $name): string => $this->normalizePublicName($name),
            $names
        ));
    }

    /**
     * @param  array<string>  $paths
     * @return list<string>
     */
    protected function normalizePublicPaths(array $paths): array
    {
        return array_values(array_map(
            fn (string $path): string => $this->normalizePublicPath($path),
            $paths
        ));
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

        $policy = NamePolicy::denying($this->normalizePublicPaths($disallowed));

        return array_values(array_filter(
            $items,
            fn (string $item): bool => ! $policy->denies($this->normalizePublicPath($item))
        ));
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
        return NamePolicy::denying($this->normalizePublicPaths($disallowed))
            ->denies($this->normalizePublicPath($name));
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

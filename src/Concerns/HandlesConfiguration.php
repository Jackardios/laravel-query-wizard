<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Str;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;
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

    private ?bool $normalizePublicInputMemo = null;

    private ?QueryWizardConfig $configSnapshot = null;

    /** @var list<array{array<string>, bool, NamePolicy}> */
    private array $denyPolicyMemo = [];

    /**
     * Set the resource schema for configuration.
     *
     * The schema provides default filters, sorts, includes, fields, and appends.
     * Explicit calls to allowed*() methods override schema definitions.
     *
     * @param  class-string<ResourceSchemaInterface>|ResourceSchemaInterface  $schema
     */
    public function schema(string|ResourceSchemaInterface $schema): static
    {
        $schema = is_string($schema) ? app($schema) : $schema;
        $this->invalidateBuild();
        $this->schema = $schema;

        return $this;
    }

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
        return $this->normalizePublicInputMemo ??= $this->getConfig()->shouldConvertParametersToSnakeCase();
    }

    /**
     * The configuration as of the current build: config() is read once, when
     * the build first needs a setting.
     */
    private function configSnapshot(QueryWizardConfig $config): QueryWizardConfig
    {
        return $this->configSnapshot ??= $config->snapshot();
    }

    /**
     * Re-read memoized configuration on the next use.
     *
     * Called when a build starts and whenever the wizard is reconfigured, so a
     * runtime config change is picked up by the next build.
     */
    private function forgetConfigurationMemo(): void
    {
        $this->configSnapshot = null;
        $this->normalizePublicInputMemo = null;
        $this->denyPolicyMemo = [];
    }

    /**
     * Deny policy for a raw deny list, memoized by the list itself.
     *
     * Keying on the list (not on an invalidation hook) keeps the memo correct
     * even when a subclass reassigns a deny list without invalidating the build.
     *
     * @param  array<string>  $disallowed
     */
    private function denyPolicyFor(array $disallowed): NamePolicy
    {
        $normalize = $this->shouldNormalizePublicInput();

        foreach ($this->denyPolicyMemo as [$list, $listNormalize, $policy]) {
            if ($listNormalize === $normalize && $list === $disallowed) {
                return $policy;
            }
        }

        $policy = NamePolicy::denying($this->normalizePublicPaths($disallowed));

        if (count($this->denyPolicyMemo) >= 8) {
            array_shift($this->denyPolicyMemo);
        }

        $this->denyPolicyMemo[] = [$disallowed, $normalize, $policy];

        return $policy;
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

        $policy = $this->denyPolicyFor($disallowed);

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
        return $this->denyPolicyFor($disallowed)->denies($this->normalizePublicPath($name));
    }

    /**
     * Limits guard client input; a developer default over one is a configuration error.
     */
    private function assertDefaultWithinLimit(string $subject, int $value, ?int $limit, string $limitKey): void
    {
        if ($limit !== null && $value > $limit) {
            throw new \InvalidArgumentException(
                "{$subject} ({$value}) exceeds the `limits.{$limitKey}` limit ({$limit}) for client input. "
                .'Raise the limit or reduce the defaults.'
            );
        }
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

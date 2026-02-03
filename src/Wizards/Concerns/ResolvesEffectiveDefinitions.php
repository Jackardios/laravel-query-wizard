<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Wizards\Concerns;

/**
 * Helper trait for resolving effective definitions from instance, schema, and context.
 *
 * This trait provides reusable methods for the common pattern of:
 * 1. Using instance-level definitions if set
 * 2. Falling back to schema definitions
 * 3. Applying context overrides (allowed/disallowed)
 */
trait ResolvesEffectiveDefinitions
{
    /**
     * Resolve effective definitions by applying the standard precedence:
     * 1. Instance-level allowed definitions (if not empty)
     * 2. Schema definitions (fallback)
     * 3. Context overrides (if context exists):
     *    - Replace with context allowed (if not null)
     *    - Remove context disallowed items
     *
     * @template T
     * @param array<T> $instanceAllowed Instance-level allowed definitions
     * @param callable|null $schemaGetter Callable that returns schema definitions, or null
     * @param callable|null $contextAllowedGetter Callable that returns context allowed, or null
     * @param callable|null $contextDisallowedGetter Callable that returns context disallowed, or null
     * @param callable(T): string $nameExtractor Callable to extract name from definition
     * @return array<T>
     */
    protected function resolveAllowedDefinitions(
        array $instanceAllowed,
        ?callable $schemaGetter,
        ?callable $contextAllowedGetter,
        ?callable $contextDisallowedGetter,
        callable $nameExtractor
    ): array {
        // Start with instance definitions or schema definitions
        $definitions = !empty($instanceAllowed)
            ? $instanceAllowed
            : ($schemaGetter !== null ? $schemaGetter() : []);

        // Apply context overrides
        if ($contextAllowedGetter !== null) {
            $contextAllowed = $contextAllowedGetter();
            if ($contextAllowed !== null) {
                $definitions = $contextAllowed;
            }
        }

        if ($contextDisallowedGetter !== null) {
            $disallowed = $contextDisallowedGetter();
            if (!empty($disallowed)) {
                $definitions = $this->removeDisallowed($definitions, $disallowed, $nameExtractor);
            }
        }

        return $definitions;
    }

    /**
     * Resolve effective defaults by applying the standard precedence:
     * 1. Context defaults (if not null)
     * 2. Instance defaults (if not empty)
     * 3. Schema defaults (fallback)
     *
     * @template T
     * @param array<T> $instanceDefaults Instance-level defaults
     * @param callable|null $contextDefaultsGetter Callable that returns context defaults, or null
     * @param callable|null $schemaDefaultsGetter Callable that returns schema defaults, or null
     * @return array<T>
     */
    protected function resolveEffectiveDefaults(
        array $instanceDefaults,
        ?callable $contextDefaultsGetter,
        ?callable $schemaDefaultsGetter
    ): array {
        // Context defaults take highest priority
        if ($contextDefaultsGetter !== null) {
            $contextDefaults = $contextDefaultsGetter();
            if ($contextDefaults !== null) {
                return $contextDefaults;
            }
        }

        // Then instance defaults
        if (!empty($instanceDefaults)) {
            return $instanceDefaults;
        }

        // Finally schema defaults
        return $schemaDefaultsGetter !== null ? $schemaDefaultsGetter() : [];
    }
}

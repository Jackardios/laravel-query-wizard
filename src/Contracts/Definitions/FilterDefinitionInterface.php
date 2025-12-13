<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts\Definitions;

use Closure;

interface FilterDefinitionInterface
{
    /**
     * Get the filter name (alias or property name)
     */
    public function getName(): string;

    /**
     * Get the property/column name
     */
    public function getProperty(): string;

    /**
     * Get the filter type (exact, partial, scope, etc.)
     */
    public function getType(): string;

    /**
     * Get the default value
     */
    public function getDefault(): mixed;

    /**
     * Prepare/transform the value before applying
     */
    public function prepareValue(mixed $value): mixed;

    /**
     * @return (Closure(mixed $query, mixed $value, string $property): void)|null
     */
    public function getCallback(): ?Closure;

    /**
     * Get the custom strategy class
     *
     * @return class-string|null
     */
    public function getStrategyClass(): ?string;

    /**
     * Get additional options
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array;

    /**
     * Get a specific option
     */
    public function getOption(string $key, mixed $default = null): mixed;
}

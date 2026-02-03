<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts\Definitions;

use Closure;

interface SortDefinitionInterface
{
    /**
     * Get the sort name (alias or property name)
     */
    public function getName(): string;

    /**
     * Get the alias (null if not set)
     */
    public function getAlias(): ?string;

    /**
     * Get the property/column name
     */
    public function getProperty(): string;

    /**
     * Get the sort type (field, callback, etc.)
     */
    public function getType(): string;

    /**
     * @return (Closure(mixed $query, string $direction, string $property): void)|null
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

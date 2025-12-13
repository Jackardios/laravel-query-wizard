<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts\Definitions;

use Closure;

interface IncludeDefinitionInterface
{
    /**
     * Get the include name (alias or relation name)
     */
    public function getName(): string;

    /**
     * Get the relation name
     */
    public function getRelation(): string;

    /**
     * Get the include type (relationship, count, callback, etc.)
     */
    public function getType(): string;

    /**
     * @return (Closure(mixed $query, string $relation, array<string> $fields): void)|null
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

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

interface FilterInterface
{
    /**
     * Get the filter name (alias or property name)
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
     * Apply filter to subject
     */
    public function apply(mixed $subject, mixed $value): mixed;
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

interface SortInterface
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
     * Apply sort to subject
     *
     * @param 'asc'|'desc' $direction
     */
    public function apply(mixed $subject, string $direction): mixed;
}

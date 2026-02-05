<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

/**
 * Interface for sort implementations.
 *
 * Sorts apply ordering to query subjects.
 */
interface SortInterface
{
    /**
     * Get the name used in URL parameters.
     * Returns alias if set, otherwise property name.
     */
    public function getName(): string;

    /**
     * Get the alias (URL parameter name override).
     */
    public function getAlias(): ?string;

    /**
     * Get the property name to sort on.
     */
    public function getProperty(): string;

    /**
     * Set an alias for URL parameter name.
     */
    public function alias(string $alias): static;

    /**
     * Get the sort type identifier.
     */
    public function getType(): string;

    /**
     * Apply the sort to the subject.
     *
     * @param  mixed  $subject  The query builder or similar
     * @param  'asc'|'desc'  $direction  The sort direction
     * @return mixed The modified subject
     */
    public function apply(mixed $subject, string $direction): mixed;
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

/**
 * Interface for filter implementations.
 *
 * Filters transform values and apply conditions to query subjects.
 */
interface FilterInterface
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
     * Get the property/column name to filter on.
     */
    public function getProperty(): string;

    /**
     * Set an alias for URL parameter name.
     */
    public function alias(string $alias): static;

    /**
     * Get the filter type identifier.
     */
    public function getType(): string;

    /**
     * Get the default value when filter is not in request.
     */
    public function getDefault(): mixed;

    /**
     * Prepare the filter value before applying.
     *
     * Use this to transform, validate, or normalize the value
     * from the request before it's used in the filter logic.
     *
     * @param mixed $value The raw value from the request
     * @return mixed The prepared value to use in apply()
     */
    public function prepareValue(mixed $value): mixed;

    /**
     * Apply the filter to the subject.
     *
     * @param mixed $subject The query builder or similar
     * @param mixed $value The prepared filter value
     * @return mixed The modified subject
     */
    public function apply(mixed $subject, mixed $value): mixed;
}

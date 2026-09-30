<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

/**
 * Interface for filter implementations.
 *
 * Filters transform values and apply conditions to query subjects.
 *
 * @api
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
     * Get the default value when filter is not in request.
     */
    public function getDefault(): mixed;

    /**
     * Whether string request values are split by the filters separator (`a,b` → ['a', 'b']).
     */
    public function shouldSplitValues(): bool;

    /**
     * Whether the raw request value may be a nested structure that only prepareValue() makes readable.
     *
     * When false, validateValueShape() checks the raw value before prepareValue();
     * the prepared value is checked either way when preparation changed it.
     */
    public function allowsStructuredInput(): bool;

    /**
     * Check the shape of a request value.
     *
     * @return string|null Null when the shape is acceptable, otherwise the details for InvalidFilterQuery::invalidFormat() (a 400)
     */
    public function validateValueShape(mixed $value): ?string;

    /**
     * Prepare the filter value before applying.
     *
     * Use this to transform, validate, or normalize the value
     * from the request before it's used in the filter logic.
     *
     * @param  mixed  $value  The raw value from the request
     * @return mixed The prepared value to use in apply()
     */
    public function prepareValue(mixed $value): mixed;

    /**
     * Apply the filter to the subject.
     *
     * @param  mixed  $subject  The query builder or similar
     * @param  mixed  $value  The prepared filter value
     * @return mixed The modified subject
     */
    public function apply(mixed $subject, mixed $value): mixed;
}

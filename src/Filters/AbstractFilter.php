<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Filters;

use Closure;
use Jackardios\QueryWizard\Contracts\FilterInterface;

/**
 * Base class for filter implementations.
 *
 * Provides common functionality for all filters including:
 * - Property/alias management
 * - Default values
 * - Value preparation callbacks
 * - Conditional application via when()
 *
 * All modifier methods mutate and return the same instance (fluent pattern).
 */
abstract class AbstractFilter implements FilterInterface
{
    protected mixed $default = null;

    /** @var (Closure(mixed): mixed)|null */
    protected ?Closure $prepareValueCallback = null;

    /** @var (Closure(mixed): bool)|null */
    protected ?Closure $whenCallback = null;

    protected bool $structuredInputAllowed = false;

    protected function __construct(
        protected string $property,
        protected ?string $alias = null,
    ) {
        if (trim($property) === '') {
            throw new \InvalidArgumentException('Filter property name cannot be empty.');
        }
    }

    /**
     * Set an alias for URL parameter name.
     */
    public function alias(string $alias): static
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * Set a default value when filter is not in request.
     */
    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    /**
     * Set a callback to transform the value before applying.
     *
     * @param  Closure(mixed): mixed  $callback
     */
    public function prepareValueWith(Closure $callback): static
    {
        $this->prepareValueCallback = $callback;

        return $this;
    }

    /**
     * Only apply the filter when the condition is true.
     *
     * If the callback returns false, the filter value becomes null (filter skipped).
     *
     * Example:
     *   ->when(fn($value) => auth()->check())
     *   ->when(fn($value) => $value !== 'all')
     *
     * @param  Closure(mixed): bool  $callback
     */
    public function when(Closure $callback): static
    {
        $this->whenCallback = $callback;

        return $this;
    }

    /**
     * Get the name used in URL parameters.
     * Returns alias if set, otherwise property name.
     */
    public function getName(): string
    {
        return $this->alias ?? $this->property;
    }

    /**
     * Get the alias.
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * Get the property name.
     */
    public function getProperty(): string
    {
        return $this->property;
    }

    /**
     * Get the default value.
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Prepare the filter value before applying.
     *
     * Checks the when() condition first - returns null if condition is false.
     * Then applies prepareValueWith() callback if set.
     */
    public function prepareValue(mixed $value): mixed
    {
        if ($this->whenCallback !== null && ! ($this->whenCallback)($value)) {
            return null;
        }

        return $this->prepareValueCallback !== null
            ? ($this->prepareValueCallback)($value)
            : $value;
    }

    /**
     * Allow structured raw input that will be normalized by prepareValueWith().
     *
     * Built-in filters still validate the prepared value shape before apply().
     */
    public function allowStructuredInput(): static
    {
        $this->structuredInputAllowed = true;

        return $this;
    }

    /**
     * Restore the default behavior and validate raw input shape before preparation.
     */
    public function disallowStructuredInput(): static
    {
        $this->structuredInputAllowed = false;

        return $this;
    }

    /**
     * Validate the raw filter value shape before prepareValue().
     *
     * Return null when the value shape is acceptable, otherwise return a
     * human-readable details string for InvalidFilterQuery::invalidFormat().
     */
    public function validateIncomingValueShape(mixed $value): ?string
    {
        if ($this->structuredInputAllowed) {
            return null;
        }

        return $this->validatePreparedValueShape($value);
    }

    /**
     * Validate the prepared filter value shape before apply().
     *
     * Return null when the value shape is acceptable, otherwise return a
     * human-readable details string for InvalidFilterQuery::invalidFormat().
     */
    public function validatePreparedValueShape(mixed $value): ?string
    {
        return $this->validateValueShape($value);
    }

    /**
     * Backward-compatible single-stage validation hook.
     *
     * Override validatePreparedValueShape() for new implementations when the
     * prepared value contract differs from the raw input contract.
     */
    public function validateValueShape(mixed $value): ?string
    {
        return null;
    }

    /**
     * Treat the filter value as boolean.
     *
     * Converts string values like 'true', 'false', '1', '0', 'yes', 'no'
     * to PHP booleans using filter_var(). Useful for boolean database columns.
     *
     * Non-boolean values become null (filter skipped).
     */
    public function asBoolean(): static
    {
        return $this->prepareValueWith(
            static fn (mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
        );
    }

    protected function validateScalarOnlyValueShape(mixed $value): ?string
    {
        if ($value === null || is_scalar($value)) {
            return null;
        }

        return "Filter `{$this->getName()}` expects a scalar value.";
    }

    protected function validateScalarOrFlatListValueShape(mixed $value): ?string
    {
        if ($value === null || is_scalar($value)) {
            return null;
        }

        if (is_array($value) && $this->isFlatScalarList($value)) {
            return null;
        }

        return "Filter `{$this->getName()}` expects a scalar value or a flat list of scalar values.";
    }

    protected function validateNonArrayOrFlatListOfNonArraysValueShape(mixed $value): ?string
    {
        if (! is_array($value)) {
            return null;
        }

        if ($this->isFlatListWithoutNestedArrays($value)) {
            return null;
        }

        return "Filter `{$this->getName()}` expects a single value or a flat list without nested arrays.";
    }

    /**
     * @param  array<mixed>  $value
     */
    protected function isFlatScalarList(array $value): bool
    {
        if (! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($item !== null && ! is_scalar($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<mixed>  $value
     */
    protected function isFlatListWithoutNestedArrays(array $value): bool
    {
        if (! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (is_array($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the filter type identifier.
     */
    abstract public function getType(): string;

    /**
     * Apply the filter to the subject.
     *
     * @param  mixed  $subject  The query builder or similar
     * @param  mixed  $value  The prepared filter value
     * @return mixed The modified subject
     */
    abstract public function apply(mixed $subject, mixed $value): mixed;
}

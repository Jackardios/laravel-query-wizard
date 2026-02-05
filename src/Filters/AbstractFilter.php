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
 *
 * All modifier methods mutate and return the same instance (fluent pattern).
 *
 * @phpstan-consistent-constructor
 */
abstract class AbstractFilter implements FilterInterface
{
    protected mixed $default = null;
    protected ?Closure $prepareValueCallback = null;

    protected function __construct(
        protected string $property,
        protected ?string $alias = null,
    ) {}

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
     * @param Closure(mixed): mixed $callback
     */
    public function prepareValueWith(Closure $callback): static
    {
        $this->prepareValueCallback = $callback;
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
     */
    public function prepareValue(mixed $value): mixed
    {
        return $this->prepareValueCallback !== null
            ? ($this->prepareValueCallback)($value)
            : $value;
    }

    /**
     * Get the filter type identifier.
     */
    abstract public function getType(): string;

    /**
     * Apply the filter to the subject.
     *
     * @param mixed $subject The query builder or similar
     * @param mixed $value The prepared filter value
     * @return mixed The modified subject
     */
    abstract public function apply(mixed $subject, mixed $value): mixed;
}

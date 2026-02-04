<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Filters;

use Closure;
use Jackardios\QueryWizard\Contracts\FilterInterface;

/**
 * Base class for all filters.
 *
 * Provides common functionality for filters including aliasing, default values,
 * and value transformation. All filter types extend this class.
 *
 * ## Common Methods
 *
 * | Method | Description | Example |
 * |--------|-------------|---------|
 * | `alias(string)` | Use different name in URL | `->alias('user')` |
 * | `default(mixed)` | Default value when not in request | `->default('active')` |
 * | `prepareValueWith(Closure)` | Transform value before applying | `->prepareValueWith(fn($v) => strtolower($v))` |
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
     * Set an alias for the filter name in URL parameters.
     *
     * Use this when you want the URL parameter name to differ from the property name.
     * This is useful for user-friendly URLs or when the column name is verbose.
     *
     * Note: Filters are immutable. This method returns a new instance.
     *
     * @example
     * ```php
     * FilterDefinition::exact('user_id')->alias('user')
     * // Request: ?filter[user]=5 → filters by user_id column
     * ```
     *
     * @param string $alias The URL parameter name to use
     * @return static New filter instance with the alias set
     */
    public function alias(string $alias): static
    {
        $clone = clone $this;
        $clone->alias = $alias;
        return $clone;
    }

    /**
     * Set a default value for the filter when not present in the request.
     *
     * The default value is applied when the filter is not included in the request.
     * This allows you to have "always-on" filters without requiring URL parameters.
     *
     * Note: Filters are immutable. This method returns a new instance.
     *
     * @example Apply default status filter
     * ```php
     * FilterDefinition::exact('status')->default('active')
     * // Without ?filter[status]=... → filters by status='active'
     * ```
     *
     * @example Default array value
     * ```php
     * FilterDefinition::exact('type')->default(['post', 'article'])
     * ```
     *
     * @param mixed $value The default value to use
     * @return static New filter instance with the default set
     */
    public function default(mixed $value): static
    {
        $clone = clone $this;
        $clone->default = $value;
        return $clone;
    }

    /**
     * Set a callback to transform the filter value before applying.
     *
     * The callback receives the raw value from the request and should return
     * the transformed value. This runs before the filter logic is applied.
     *
     * Note: Filters are immutable. This method returns a new instance.
     *
     * @example Normalize email to lowercase
     * ```php
     * FilterDefinition::exact('email')->prepareValueWith(fn($v) => strtolower($v))
     * ```
     *
     * @example Parse comma-separated string to array
     * ```php
     * FilterDefinition::exact('tags')->prepareValueWith(fn($v) => explode(',', $v))
     * ```
     *
     * @example Type casting
     * ```php
     * FilterDefinition::exact('active')->prepareValueWith(fn($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN))
     * ```
     *
     * @param Closure(mixed): mixed $callback Function to transform the value
     * @return static New filter instance with the callback set
     */
    public function prepareValueWith(Closure $callback): static
    {
        $clone = clone $this;
        $clone->prepareValueCallback = $callback;
        return $clone;
    }

    /**
     * Get the filter name used in URL parameters.
     *
     * Returns the alias if set, otherwise returns the property name.
     */
    public function getName(): string
    {
        return $this->alias ?? $this->property;
    }

    /**
     * Get the alias if one was set.
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * Get the property/column name this filter operates on.
     */
    public function getProperty(): string
    {
        return $this->property;
    }

    /**
     * Get the default value for this filter.
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Transform the value using the prepare callback if set.
     *
     * @param mixed $value The raw value from the request
     * @return mixed The transformed value (or original if no callback)
     */
    public function prepareValue(mixed $value): mixed
    {
        if ($this->prepareValueCallback === null) {
            return $value;
        }
        return ($this->prepareValueCallback)($value);
    }

    /**
     * Get the filter type identifier.
     *
     * Used by drivers to determine if they support this filter type.
     */
    abstract public function getType(): string;

    /**
     * Apply the filter to the query subject.
     *
     * @param mixed $subject The query builder (type depends on driver)
     * @param mixed $value The filter value from the request
     * @return mixed The modified subject
     */
    abstract public function apply(mixed $subject, mixed $value): mixed;
}

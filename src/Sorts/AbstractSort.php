<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Sorts;

use Jackardios\QueryWizard\Contracts\SortInterface;

/**
 * Base class for all sorts.
 *
 * Provides common functionality for sorts including aliasing.
 * All sort types extend this class.
 *
 * ## Common Methods
 *
 * | Method | Description | Example |
 * |--------|-------------|---------|
 * | `alias(string)` | Use different name in URL | `->alias('date')` |
 *
 * ## Sort Direction
 *
 * - Ascending: `?sort=name` (no prefix)
 * - Descending: `?sort=-name` (hyphen prefix)
 *
 * @phpstan-consistent-constructor
 */
abstract class AbstractSort implements SortInterface
{
    protected function __construct(
        protected string $property,
        protected ?string $alias = null,
    ) {}

    /**
     * Set an alias for the sort name in URL parameters.
     *
     * Use this when you want the URL parameter name to differ from the property name.
     * This is useful for user-friendly URLs or when the column name is verbose.
     *
     * Note: Sorts are immutable. This method returns a new instance.
     *
     * @example
     * ```php
     * SortDefinition::field('created_at')->alias('date')
     * // Request: ?sort=-date → sorts by created_at DESC
     * ```
     *
     * @param string $alias The URL parameter name to use
     * @return static New sort instance with the alias set
     */
    public function alias(string $alias): static
    {
        $clone = clone $this;
        $clone->alias = $alias;
        return $clone;
    }

    /**
     * Get the sort name used in URL parameters.
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
     * Get the property/column name this sort operates on.
     */
    public function getProperty(): string
    {
        return $this->property;
    }

    /**
     * Get the sort type identifier.
     *
     * Used by drivers to determine if they support this sort type.
     */
    abstract public function getType(): string;

    /**
     * Apply the sort to the query subject.
     *
     * @param mixed $subject The query builder (type depends on driver)
     * @param string $direction The sort direction ('asc' or 'desc')
     * @return mixed The modified subject
     */
    abstract public function apply(mixed $subject, string $direction): mixed;
}

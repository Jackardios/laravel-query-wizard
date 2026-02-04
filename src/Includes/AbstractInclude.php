<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Includes;

use Jackardios\QueryWizard\Contracts\IncludeInterface;

/**
 * Base class for all includes.
 *
 * Provides common functionality for includes including aliasing.
 * All include types extend this class.
 *
 * ## Common Methods
 *
 * | Method | Description | Example |
 * |--------|-------------|---------|
 * | `alias(string)` | Use different name in URL | `->alias('articles')` |
 *
 * @phpstan-consistent-constructor
 */
abstract class AbstractInclude implements IncludeInterface
{
    protected function __construct(
        protected string $relation,
        protected ?string $alias = null,
    ) {}

    /**
     * Set an alias for the include name in URL parameters.
     *
     * Use this when you want the URL parameter name to differ from the relation name.
     * This is useful for user-friendly URLs or API versioning.
     *
     * Note: Includes are immutable. This method returns a new instance.
     *
     * @example
     * ```php
     * IncludeDefinition::relationship('posts')->alias('articles')
     * // Request: ?include=articles → loads posts relationship
     * ```
     *
     * @param string $alias The URL parameter name to use
     * @return static New include instance with the alias set
     */
    public function alias(string $alias): static
    {
        $clone = clone $this;
        $clone->alias = $alias;
        return $clone;
    }

    /**
     * Get the include name used in URL parameters.
     *
     * Returns the alias if set, otherwise returns the relation name.
     */
    public function getName(): string
    {
        return $this->alias ?? $this->relation;
    }

    /**
     * Get the alias if one was set.
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * Get the relation name this include operates on.
     */
    public function getRelation(): string
    {
        return $this->relation;
    }

    /**
     * Get the include type identifier.
     *
     * Used by drivers to determine if they support this include type.
     */
    abstract public function getType(): string;

    /**
     * Apply the include to the query subject.
     *
     * @param mixed $subject The query builder (type depends on driver)
     * @param array<string> $fields Selected fields for the relation (sparse fieldsets)
     * @return mixed The modified subject
     */
    abstract public function apply(mixed $subject, array $fields = []): mixed;
}

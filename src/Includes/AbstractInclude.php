<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Includes;

use Jackardios\QueryWizard\Contracts\IncludeInterface;

/**
 * Base class for include implementations.
 *
 * Provides common functionality for all includes including:
 * - Relation/alias management
 *
 * All modifier methods mutate and return the same instance (fluent pattern).
 *
 * @phpstan-consistent-constructor
 */
abstract class AbstractInclude implements IncludeInterface
{
    protected function __construct(
        protected string $relation,
        protected ?string $alias = null,
    ) {
        if (trim($relation) === '') {
            throw new \InvalidArgumentException('Include relation name cannot be empty.');
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
     * Get the name used in URL parameters.
     * Returns alias if set, otherwise relation name.
     */
    public function getName(): string
    {
        return $this->alias ?? $this->relation;
    }

    /**
     * Get the alias.
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * Get the relation name.
     */
    public function getRelation(): string
    {
        return $this->relation;
    }

    /**
     * Get the include type identifier.
     */
    abstract public function getType(): string;

    /**
     * Apply the include to the subject.
     *
     * @param  mixed  $subject  The query builder or other subject
     * @return mixed The modified subject
     */
    abstract public function apply(mixed $subject): mixed;

    /**
     * Get the default alias suffix for this include type.
     *
     * Override in subclasses to provide type-specific suffix (e.g., 'Count', 'Exists').
     * Returns null if no default suffix should be applied.
     */
    public function getDefaultAliasSuffix(): ?string
    {
        return null;
    }

    /**
     * Get the config key for this include type's suffix.
     *
     * Override in subclasses to enable configurable suffixes.
     * Example: 'count_suffix' for CountInclude.
     */
    public function getSuffixConfigKey(): ?string
    {
        return null;
    }

    /**
     * Apply default alias if not already set.
     *
     * @param  string|null  $suffix  Custom suffix to use (overrides getDefaultAliasSuffix())
     */
    public function withDefaultAlias(?string $suffix = null): static
    {
        if ($this->alias !== null) {
            return $this;
        }

        $suffix ??= $this->getDefaultAliasSuffix();
        if ($suffix === null) {
            return $this;
        }

        return (clone $this)->alias($this->relation.$suffix);
    }
}

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
 * @api
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

        if ($alias !== null) {
            self::assertValidAlias($alias);
        }
    }

    /**
     * @throws \InvalidArgumentException When the alias is blank
     */
    private static function assertValidAlias(string $alias): void
    {
        if (trim($alias) === '') {
            throw new \InvalidArgumentException('Include alias cannot be empty.');
        }
    }

    /**
     * Set an alias for URL parameter name.
     */
    public function alias(string $alias): static
    {
        self::assertValidAlias($alias);
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
     * Apply the include to the subject.
     *
     * @param  mixed  $subject  The query builder or other subject
     * @return mixed The modified subject
     */
    abstract public function apply(mixed $subject): mixed;
}

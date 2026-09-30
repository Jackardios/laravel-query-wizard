<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Sorts;

use Jackardios\QueryWizard\Contracts\SortInterface;

/**
 * Base class for sort implementations.
 *
 * Provides common functionality for all sorts including:
 * - Property/alias management
 *
 * All modifier methods mutate and return the same instance (fluent pattern).
 *
 * @api
 */
abstract class AbstractSort implements SortInterface
{
    protected function __construct(
        protected string $property,
        protected ?string $alias = null,
    ) {
        if (trim($property) === '') {
            throw new \InvalidArgumentException('Sort property name cannot be empty.');
        }

        self::assertNotDescending($property, 'property');

        if ($alias !== null) {
            self::assertValidAlias($alias);
        }
    }

    /**
     * @throws \InvalidArgumentException When the alias is blank or starts with `-`
     */
    private static function assertValidAlias(string $alias): void
    {
        if (trim($alias) === '') {
            throw new \InvalidArgumentException('Sort alias cannot be empty.');
        }

        self::assertNotDescending($alias, 'alias');
    }

    /**
     * A leading `-` marks a descending sort in the request, so no sort name can start with one.
     *
     * @throws \InvalidArgumentException When the name starts with `-`
     */
    private static function assertNotDescending(string $name, string $what): void
    {
        if (str_starts_with($name, '-')) {
            throw new \InvalidArgumentException(
                "Sort {$what} `{$name}` cannot start with `-`, which requests a descending sort. Use defaultSorts('{$name}') for a descending default."
            );
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
     * Get the sort type identifier.
     */
    abstract public function getType(): string;

    /**
     * Apply the sort to the subject.
     *
     * @param  mixed  $subject  The query builder or similar
     * @param  'asc'|'desc'  $direction  The sort direction
     * @return mixed The modified subject
     */
    abstract public function apply(mixed $subject, string $direction): mixed;
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

/**
 * Interface for include implementations.
 */
interface IncludeInterface
{
    /**
     * Get the name used in URL parameters.
     * Returns alias if set, otherwise relation name.
     */
    public function getName(): string;

    /**
     * Get the alias (URL parameter name override).
     */
    public function getAlias(): ?string;

    /**
     * Get the relation name.
     */
    public function getRelation(): string;

    /**
     * Get the include type identifier.
     */
    public function getType(): string;

    /**
     * Set an alias for URL parameter name.
     */
    public function alias(string $alias): static;

    /**
     * Apply the include to the subject.
     *
     * @param  mixed  $subject  The query builder or other subject
     * @return mixed The modified subject
     */
    public function apply(mixed $subject): mixed;

    /**
     * Get the default alias suffix for this include type.
     */
    public function getDefaultAliasSuffix(): ?string;

    /**
     * Get the config key for this include type's suffix.
     */
    public function getSuffixConfigKey(): ?string;

    /**
     * Apply default alias if not already set.
     *
     * @param  string|null  $suffix  Custom suffix to use
     */
    public function withDefaultAlias(?string $suffix = null): static;
}

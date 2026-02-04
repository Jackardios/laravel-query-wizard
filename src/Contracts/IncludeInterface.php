<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

interface IncludeInterface
{
    /**
     * Get the include name (alias or relation name)
     */
    public function getName(): string;

    /**
     * Get the alias (null if not set)
     */
    public function getAlias(): ?string;

    /**
     * Get the relation name
     */
    public function getRelation(): string;

    /**
     * Get the include type (relationship, count, callback, etc.)
     */
    public function getType(): string;

    /**
     * Apply include to subject
     *
     * @param array<string> $fields
     */
    public function apply(mixed $subject, array $fields = []): mixed;
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;

interface IncludeStrategyInterface
{
    /**
     * Apply include to subject
     *
     * @param array<string> $fields
     */
    public function apply(mixed $subject, IncludeDefinitionInterface $include, array $fields = []): mixed;
}

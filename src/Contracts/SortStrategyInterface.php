<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;

interface SortStrategyInterface
{
    /**
     * Apply sort to subject
     */
    public function apply(mixed $subject, SortDefinitionInterface $sort, string $direction): mixed;
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;

interface FilterStrategyInterface
{
    /**
     * Apply filter to subject
     */
    public function apply(mixed $subject, FilterDefinitionInterface $filter, mixed $value): mixed;
}

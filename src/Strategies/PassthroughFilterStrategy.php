<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Strategies;

use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\FilterStrategyInterface;

/**
 * Strategy that captures filter value without applying to query.
 * Used for passthrough filters that will be handled by user code.
 */
class PassthroughFilterStrategy implements FilterStrategyInterface
{
    public function apply(mixed $subject, FilterDefinitionInterface $filter, mixed $value): mixed
    {
        // Intentionally do nothing - just return subject unchanged
        return $subject;
    }
}

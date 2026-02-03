<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Strategies;

use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\FilterStrategyInterface;

/**
 * Generic callback filter strategy that works with any query type.
 *
 * The callback receives: ($subject, $value, $property)
 */
class CallbackFilterStrategy implements FilterStrategyInterface
{
    /**
     * Apply filter using the callback defined in the filter definition.
     */
    public function apply(mixed $subject, FilterDefinitionInterface $filter, mixed $value): mixed
    {
        $callback = $filter->getCallback();

        if ($callback !== null) {
            $callback($subject, $value, $filter->getProperty());
        }

        return $subject;
    }
}

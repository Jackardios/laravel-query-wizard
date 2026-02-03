<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Strategies;

use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;
use Jackardios\QueryWizard\Contracts\SortStrategyInterface;

/**
 * Generic callback sort strategy that works with any query type.
 *
 * The callback receives: ($subject, $direction, $property)
 */
class CallbackSortStrategy implements SortStrategyInterface
{
    /**
     * Apply sort using the callback defined in the sort definition.
     *
     * @param 'asc'|'desc' $direction
     */
    public function apply(mixed $subject, SortDefinitionInterface $sort, string $direction): mixed
    {
        $callback = $sort->getCallback();

        if ($callback !== null) {
            $callback($subject, $direction, $sort->getProperty());
        }

        return $subject;
    }
}

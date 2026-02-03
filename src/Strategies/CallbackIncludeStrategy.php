<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Strategies;

use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;
use Jackardios\QueryWizard\Contracts\IncludeStrategyInterface;

/**
 * Generic callback include strategy that works with any query type.
 *
 * The callback receives: ($subject, $relation, $fields)
 */
class CallbackIncludeStrategy implements IncludeStrategyInterface
{
    /**
     * Apply include using the callback defined in the include definition.
     *
     * @param array<string> $fields
     */
    public function apply(mixed $subject, IncludeDefinitionInterface $include, array $fields = []): mixed
    {
        $callback = $include->getCallback();

        if ($callback !== null) {
            $callback($subject, $include->getRelation(), $fields);
        }

        return $subject;
    }
}

<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;
use Jackardios\QueryWizard\Contracts\SortStrategyInterface;

class CallbackSortStrategy implements SortStrategyInterface
{
    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @param 'asc'|'desc' $direction
     * @return Builder<\Illuminate\Database\Eloquent\Model>
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

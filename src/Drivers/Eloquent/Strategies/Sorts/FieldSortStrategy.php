<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;
use Jackardios\QueryWizard\Contracts\SortStrategyInterface;

class FieldSortStrategy implements SortStrategyInterface
{
    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @param 'asc'|'desc' $direction
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, SortDefinitionInterface $sort, string $direction): mixed
    {
        $column = $subject->qualifyColumn($sort->getProperty());
        $subject->orderBy($column, $direction);

        return $subject;
    }
}

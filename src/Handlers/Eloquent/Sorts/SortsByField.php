<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Sorts;

class SortsByField extends AbstractEloquentSort
{
    public function handle($queryHandler, $queryBuilder, string $direction): void
    {
        $queryBuilder->orderBy($this->getPropertyName(), $direction);
    }
}

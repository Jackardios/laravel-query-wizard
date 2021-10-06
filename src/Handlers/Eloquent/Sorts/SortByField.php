<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Sorts;

class SortByField extends AbstractEloquentSort
{
    /** {@inheritdoc} */
    public function handle($queryHandler, $queryBuilder, string $direction): void
    {
        $queryBuilder->orderBy($this->getPropertyName(), $direction);
    }
}

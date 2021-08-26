<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Sorts;

class SortsByField extends AbstractEloquentSort
{
    public function handle($queryHandler, $query, string $direction): void
    {
        $query->orderBy($this->getPropertyName(), $direction);
    }
}

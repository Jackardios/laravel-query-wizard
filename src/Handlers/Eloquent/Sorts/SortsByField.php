<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Sorts;

class SortsByField extends AbstractEloquentSort
{
    public function handle($query, string $direction, $queryHandler): void
    {
        $query->orderBy($this->getPropertyName(), $direction);
    }
}

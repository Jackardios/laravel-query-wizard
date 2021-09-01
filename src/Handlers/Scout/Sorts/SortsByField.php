<?php

namespace Jackardios\QueryWizard\Handlers\Scout\Sorts;

class SortsByField extends AbstractScoutSort
{
    public function handle($queryHandler, $query, string $direction): void
    {
        $query->orderBy($this->getPropertyName(), $direction);
    }
}

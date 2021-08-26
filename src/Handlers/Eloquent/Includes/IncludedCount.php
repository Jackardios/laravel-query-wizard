<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Includes;

class IncludedCount extends AbstractEloquentInclude
{
    public function handle($query, $queryHandler): void
    {
        $query->withCount($this->getRelationship());
    }
}

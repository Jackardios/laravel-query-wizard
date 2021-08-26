<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Includes;

class IncludedCount extends AbstractEloquentInclude
{
    public function handle($queryHandler, $query): void
    {
        $query->withCount($this->getRelationship());
    }
}

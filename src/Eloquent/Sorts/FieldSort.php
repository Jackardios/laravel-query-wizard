<?php

namespace Jackardios\QueryWizard\Eloquent\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Eloquent\EloquentSort;

class FieldSort extends EloquentSort
{
    /** {@inheritdoc} */
    public function handle($queryWizard, Builder $queryBuilder, string $direction): void
    {
        $queryBuilder->orderBy($this->getPropertyName(), $direction);
    }
}

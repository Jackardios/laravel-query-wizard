<?php

namespace Jackardios\QueryWizard\Handlers\Scout\Filters;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\FiltersScope as EloquentFiltersScope;

class FiltersScope extends AbstractScoutFilter
{
    public function handle($queryHandler, $query, $value): void
    {
        $eloquentFilter = EloquentFiltersScope::makeFromOther($this);
        $queryHandler->addEloquentQueryCallback(function(EloquentBuilder $query) use ($eloquentFilter, $queryHandler, $value) {
            $eloquentFilter->handle($queryHandler, $query, $value);
        });
    }
}

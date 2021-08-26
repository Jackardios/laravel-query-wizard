<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Filters;

class FiltersTrashed extends AbstractEloquentFilter
{
    public function handle($queryHandler, $query, $value): void
    {
        if ($value === 'with') {
            $query->withTrashed();

            return;
        }

        if ($value === 'only') {
            $query->onlyTrashed();

            return;
        }

        $query->withoutTrashed();
    }
}

<?php

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\SoftDeletes;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;

class TrashedFilter extends EloquentFilter
{
    public function __construct(string $propertyName = "trashed", ?string $alias = null, $default = null)
    {
        parent::__construct($propertyName, $alias, $default);
    }

    /**
     * @param SoftDeletes $queryBuilder
     */
    public function handle($queryWizard, $queryBuilder, $value): void
    {
        if ($value === 'with') {
            $queryBuilder->withTrashed();

            return;
        }

        if ($value === 'only') {
            $queryBuilder->onlyTrashed();

            return;
        }

        $queryBuilder->withoutTrashed();
    }
}

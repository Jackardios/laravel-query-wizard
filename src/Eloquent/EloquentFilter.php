<?php

namespace Jackardios\QueryWizard\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\AbstractFilter;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesFilters;

abstract class EloquentFilter extends AbstractFilter
{
    /**
     * @param AbstractQueryWizard&HandlesFilters $queryWizard
     * @param Builder $queryBuilder
     * @param $value
     * @return void
     */
    abstract public function handle(AbstractQueryWizard $queryWizard, Builder $queryBuilder, $value): void;
}

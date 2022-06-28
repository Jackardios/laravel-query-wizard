<?php

namespace Jackardios\QueryWizard\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Abstracts\AbstractSort;
use Jackardios\QueryWizard\Concerns\HandlesSorts;

abstract class EloquentSort extends AbstractSort
{
    /**
     * @param AbstractQueryWizard&HandlesSorts $queryWizard
     * @param Builder $queryBuilder
     * @param string $direction
     * @return void
     */
    abstract public function handle(AbstractQueryWizard $queryWizard, Builder $queryBuilder, string $direction): void;
}

<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\Handlers\Sorts\AbstractSort;
use Jackardios\QueryWizard\Handlers\Eloquent\EloquentQueryHandler;

abstract class AbstractEloquentSort extends AbstractSort
{
    /**
     * @param EloquentQueryHandler $queryHandler
     * @param Builder $queryBuilder
     * @param string $direction
     */
    abstract public function handle($queryHandler, $queryBuilder, string $direction): void;
}

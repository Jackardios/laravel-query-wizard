<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Sorts;

use Jackardios\QueryWizard\Abstracts\Handlers\Sorts\AbstractSort;

abstract class AbstractEloquentSort extends AbstractSort
{
    /**
     * @param \Jackardios\QueryWizard\Handlers\Eloquent\EloquentQueryHandler $queryHandler
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $direction
     */
    abstract public function handle($queryHandler, $query, string $direction): void;
}

<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Sorts;

use Jackardios\QueryWizard\Abstracts\Handlers\Sorts\AbstractSort;

abstract class AbstractEloquentSort extends AbstractSort
{
    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $direction
     * @param \Jackardios\QueryWizard\Handlers\Eloquent\EloquentQueryHandler $queryHandler
     */
    abstract public function handle($query, string $direction, $queryHandler): void;
}

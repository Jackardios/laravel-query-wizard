<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Filters;

use Jackardios\QueryWizard\Abstracts\Handlers\Filters\AbstractFilter;

abstract class AbstractEloquentFilter extends AbstractFilter
{
    /**
     * @param \Jackardios\QueryWizard\Handlers\Eloquent\EloquentQueryHandler $queryHandler
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $value
     */
    abstract public function handle($queryHandler, $query, $value): void;
}

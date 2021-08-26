<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Filters;

use Jackardios\QueryWizard\Abstracts\Handlers\Filters\AbstractFilter;

abstract class AbstractEloquentFilter extends AbstractFilter
{
    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $value
     * @param \Jackardios\QueryWizard\Handlers\Eloquent\EloquentQueryHandler $queryHandler
     */
    abstract public function handle($query, $value, $queryHandler): void;
}

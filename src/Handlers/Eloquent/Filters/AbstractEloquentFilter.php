<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\Handlers\Filters\AbstractFilter;
use Jackardios\QueryWizard\Handlers\Eloquent\EloquentQueryHandler;

abstract class AbstractEloquentFilter extends AbstractFilter
{
    /**
     * @param EloquentQueryHandler $queryHandler
     * @param Builder $query
     * @param mixed $value
     */
    abstract public function handle($queryHandler, $query, $value): void;
}

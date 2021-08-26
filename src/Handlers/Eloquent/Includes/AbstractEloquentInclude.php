<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Includes;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\Handlers\Includes\AbstractInclude;
use Jackardios\QueryWizard\Handlers\Eloquent\EloquentQueryHandler;

abstract class AbstractEloquentInclude extends AbstractInclude
{
    /**
     * @param EloquentQueryHandler $queryHandler
     * @param Builder $query
     */
    abstract public function handle($queryHandler, $query): void;
}

<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Includes;

use Jackardios\QueryWizard\Abstracts\Handlers\Includes\AbstractInclude;

abstract class AbstractEloquentInclude extends AbstractInclude
{
    /**
     * @param \Jackardios\QueryWizard\Handlers\Eloquent\EloquentQueryHandler $queryHandler
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    abstract public function handle($queryHandler, $query): void;
}

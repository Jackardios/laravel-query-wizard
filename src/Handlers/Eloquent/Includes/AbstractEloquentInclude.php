<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Includes;

use Jackardios\QueryWizard\Abstracts\Handlers\Includes\AbstractInclude;

abstract class AbstractEloquentInclude extends AbstractInclude
{
    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Jackardios\QueryWizard\Handlers\Eloquent\EloquentQueryHandler $queryHandler
     */
    abstract public function handle($query, $queryHandler): void;
}

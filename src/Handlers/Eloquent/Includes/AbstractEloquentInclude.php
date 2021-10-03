<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Includes;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;
use Jackardios\QueryWizard\Abstracts\Handlers\Includes\AbstractInclude;

abstract class AbstractEloquentInclude extends AbstractInclude
{
    /**
     * @param AbstractQueryHandler $queryHandler
     * @param Builder $queryBuilder
     */
    abstract public function handle(AbstractQueryHandler $queryHandler, $queryBuilder): void;
}

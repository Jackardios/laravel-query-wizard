<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;
use Jackardios\QueryWizard\Abstracts\Handlers\Filters\AbstractFilter;

abstract class AbstractEloquentFilter extends AbstractFilter
{
    /**
     * @param AbstractQueryHandler $queryHandler
     * @param Builder $queryBuilder
     * @param mixed $value
     */
    abstract public function handle(AbstractQueryHandler $queryHandler, $queryBuilder, $value): void;
}

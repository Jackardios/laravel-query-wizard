<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;
use Jackardios\QueryWizard\Abstracts\Handlers\Sorts\AbstractSort;

abstract class AbstractEloquentSort extends AbstractSort
{
    /**
     * @param AbstractQueryHandler $queryHandler
     * @param Builder $queryBuilder
     * @param string $direction
     */
    abstract public function handle(AbstractQueryHandler $queryHandler, $queryBuilder, string $direction): void;
}

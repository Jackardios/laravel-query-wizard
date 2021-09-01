<?php

namespace Jackardios\QueryWizard\Handlers\Scout\Filters;

use Laravel\Scout\Builder;
use Jackardios\QueryWizard\Abstracts\Handlers\Filters\AbstractFilter;
use Jackardios\QueryWizard\Handlers\Scout\ScoutQueryHandler;

abstract class AbstractScoutFilter extends AbstractFilter
{
    /**
     * @param ScoutQueryHandler $queryHandler
     * @param Builder $query
     * @param mixed $value
     */
    abstract public function handle($queryHandler, $query, $value): void;
}

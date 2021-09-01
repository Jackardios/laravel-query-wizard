<?php

namespace Jackardios\QueryWizard\Handlers\Scout\Sorts;

use Laravel\Scout\Builder;
use Jackardios\QueryWizard\Abstracts\Handlers\Sorts\AbstractSort;
use Jackardios\QueryWizard\Handlers\Scout\ScoutQueryHandler;

abstract class AbstractScoutSort extends AbstractSort
{
    /**
     * @param ScoutQueryHandler $queryHandler
     * @param Builder $query
     * @param string $direction
     */
    abstract public function handle($queryHandler, $query, string $direction): void;
}

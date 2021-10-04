<?php

namespace Jackardios\QueryWizard\Handlers\Model\Includes;

use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;
use Jackardios\QueryWizard\Abstracts\Handlers\Includes\AbstractInclude;

abstract class AbstractModelInclude extends AbstractInclude
{
    /**
     * @param AbstractQueryHandler $queryHandler
     * @param Model $model
     */
    abstract public function handle($queryHandler, $model): void;
}

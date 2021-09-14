<?php

namespace Jackardios\QueryWizard\Handlers\Model\Includes;

use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Abstracts\Handlers\Includes\AbstractInclude;
use Jackardios\QueryWizard\Handlers\Eloquent\EloquentQueryHandler;

abstract class AbstractModelInclude extends AbstractInclude
{
    /**
     * @param EloquentQueryHandler $queryHandler
     * @param Model $model
     */
    abstract public function handle($queryHandler, $model): void;
}

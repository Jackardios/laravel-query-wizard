<?php

namespace Jackardios\QueryWizard;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Handlers\Eloquent\EloquentQueryHandler;

/**
 * @mixin Builder
 * @property EloquentQueryHandler $queryHandler
 */
class EloquentQueryWizard extends AbstractQueryWizard
{
    protected string $queryHandlerClass = EloquentQueryHandler::class;

    public function defaultFieldKey(): string
    {
        return $this->queryHandler->getSubject()->getModel()->getTable();
    }
}

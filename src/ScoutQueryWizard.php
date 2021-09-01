<?php

namespace Jackardios\QueryWizard;

use Laravel\Scout\Builder;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Handlers\Scout\ScoutQueryHandler;

/**
 * @mixin Builder
 * @property ScoutQueryHandler $queryHandler
 */
class ScoutQueryWizard extends AbstractQueryWizard
{
    protected string $queryHandlerClass = ScoutQueryHandler::class;

    protected function defaultFieldsKey(): string
    {
        return $this->queryHandler->getSubject()->model->getTable();
    }

    /**
     * Set the callback that should have an opportunity to modify the database query.
     * This method overrides the Scout Query Builder method
     *
     * @param  callable  $callback
     * @return $this
     */
    public function query(callable $callback): self
    {
        $this->queryHandler->addEloquentQueryCallback($callback);

        return $this;
    }
}

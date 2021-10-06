<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Sorts;

use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;

class FieldSort extends AbstractEloquentSort
{
    /** {@inheritdoc} */
    public function handle(AbstractQueryHandler $queryHandler, $queryBuilder, string $direction): void
    {
        $queryBuilder->orderBy($this->getPropertyName(), $direction);
    }
}

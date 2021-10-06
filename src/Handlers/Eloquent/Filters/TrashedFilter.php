<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Filters;

use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;

class TrashedFilter extends AbstractEloquentFilter
{
    public function __construct(string $propertyName = "trashed", ?string $alias = null, $default = null)
    {
        parent::__construct($propertyName, $alias, $default);
    }

    /** {@inheritdoc} */
    public function handle(AbstractQueryHandler $queryHandler, $queryBuilder, $value): void
    {
        if ($value === 'with') {
            $queryBuilder->withTrashed();

            return;
        }

        if ($value === 'only') {
            $queryBuilder->onlyTrashed();

            return;
        }

        $queryBuilder->withoutTrashed();
    }
}

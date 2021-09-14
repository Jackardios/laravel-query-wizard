<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Includes;

use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;

class IncludedCount extends AbstractEloquentInclude
{
    public function __construct(string $include, ?string $alias = null)
    {
        if (empty($alias)) {
            $alias = $include.config('query-wizard.count_suffix');
        }
        parent::__construct($include, $alias);
    }

    public function handle(AbstractQueryHandler $queryHandler, $query): void
    {
        $query->withCount($this->getInclude());
    }
}

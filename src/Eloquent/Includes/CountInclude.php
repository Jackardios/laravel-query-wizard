<?php

namespace Jackardios\QueryWizard\Eloquent\Includes;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;

class CountInclude extends EloquentInclude
{
    public function __construct(string $include, ?string $alias = null)
    {
        if (empty($alias)) {
            $alias = $include.config('query-wizard.count_suffix');
        }
        parent::__construct($include, $alias);
    }

    /** {@inheritdoc} */
    public function handle($queryWizard, Builder $queryBuilder): void
    {
        $queryBuilder->withCount($this->getInclude());
    }
}

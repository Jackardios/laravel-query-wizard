<?php

namespace Jackardios\QueryWizard\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\AbstractInclude;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;

abstract class EloquentInclude extends AbstractInclude
{
    /**
     * @param AbstractQueryWizard&HandlesIncludes&HandlesFields $queryWizard
     * @param Builder $queryBuilder
     * @return void
     */
    abstract public function handle(AbstractQueryWizard $queryWizard, Builder $queryBuilder): void;
}

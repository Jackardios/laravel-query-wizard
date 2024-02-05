<?php

namespace Jackardios\QueryWizard\Model;

use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Abstracts\AbstractInclude;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;

abstract class ModelInclude extends AbstractInclude
{
    /**
     * @param AbstractQueryWizard&HandlesIncludes&HandlesFields $queryWizard
     * @param Model $model
     * @return void
     */
    abstract public function handle(AbstractQueryWizard $queryWizard, Model $model): void;
}

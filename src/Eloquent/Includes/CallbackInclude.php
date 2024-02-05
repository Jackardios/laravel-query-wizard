<?php

namespace Jackardios\QueryWizard\Eloquent\Includes;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;

class CallbackInclude extends EloquentInclude
{
    /**
     * @var callable(AbstractQueryWizard&HandlesIncludes&HandlesFields, Builder, string):mixed
     */
    private $callback;

    /**
     * @param string $include
     * @param callable(AbstractQueryWizard&HandlesIncludes&HandlesFields, Builder, string):mixed $callback
     * @param string|null $alias
     */
    public function __construct(string $include, callable $callback, ?string $alias = null)
    {
        parent::__construct($include, $alias);

        $this->callback = $callback;
    }

    /** {@inheritdoc} */
    public function handle($queryWizard, Builder $queryBuilder): void
    {
        call_user_func($this->callback, $queryWizard, $queryBuilder, $this->getInclude());
    }
}

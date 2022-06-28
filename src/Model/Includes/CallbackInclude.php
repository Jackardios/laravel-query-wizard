<?php

namespace Jackardios\QueryWizard\Model\Includes;

use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;
use Jackardios\QueryWizard\Model\ModelInclude;

class CallbackInclude extends ModelInclude
{
    /**
     * @var callable(AbstractQueryWizard&HandlesIncludes, Model, string):mixed
     */
    private $callback;

    /**
     * @param string $include
     * @param callable(AbstractQueryWizard&HandlesIncludes, Model, string):mixed $callback
     * @param string|null $alias
     */
    public function __construct(string $include, callable $callback, ?string $alias = null)
    {
        parent::__construct($include, $alias);

        $this->callback = $callback;
    }

    public function handle($queryWizard, Model $model): void
    {
        call_user_func($this->callback, $queryWizard, $model, $this->getInclude());
    }
}

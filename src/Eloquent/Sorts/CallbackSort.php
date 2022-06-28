<?php

namespace Jackardios\QueryWizard\Eloquent\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesSorts;
use Jackardios\QueryWizard\Eloquent\EloquentSort;

class CallbackSort extends EloquentSort
{
    /**
     * @var callable(AbstractQueryWizard&HandlesSorts, Builder, string, string):mixed
     */
    private $callback;

    /**
     * @param string $propertyName
     * @param callable(AbstractQueryWizard&HandlesSorts, Builder, string, string):mixed $callback
     * @param string|null $alias
     */
    public function __construct(string $propertyName, callable $callback, ?string $alias = null)
    {
        parent::__construct($propertyName, $alias);

        $this->callback = $callback;
    }

    public function handle($queryWizard, Builder $queryBuilder, string $direction): void
    {
        call_user_func($this->callback, $queryWizard, $queryBuilder, $direction, $this->getPropertyName());
    }
}
